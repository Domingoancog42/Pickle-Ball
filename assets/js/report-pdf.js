/* Local PDF assets load only when an administrator exports a report. */
(() => {
  const A=window.Admin;
  let assets;
  const script=src=>new Promise((resolve,reject)=>{
    const el=document.createElement('script');el.src=src;el.onload=resolve;
    el.onerror=()=>{el.remove();reject(Error('PDF export files could not be loaded. Refresh and try again.'));};
    document.head.append(el);
  });
  const file=async path=>{
    const response=await fetch(path);if(!response.ok)throw Error('A PDF font or logo could not be loaded. Refresh and try again.');
    return new Promise((resolve,reject)=>{
      const reader=new FileReader();reader.onload=()=>resolve(reader.result.split(',')[1]);reader.onerror=()=>reject(Error('PDF export files could not be read. Try again.'));
      response.blob().then(blob=>reader.readAsDataURL(blob),reject);
    });
  };
  const load=()=>assets??=(async()=>{
    if(!window.jspdf?.jsPDF)await script('../assets/vendor/jspdf.umd.min.js?v=4.2.1');
    if(!window.jspdf.jsPDF.API.autoTable)await script('../assets/vendor/jspdf.plugin.autotable.min.js?v=5.0.9');
    return Promise.all([file('../assets/vendor/noto-sans-regular.ttf'),file('../assets/vendor/noto-sans-bold.ttf'),file('../assets/images/logo.png')]);
  })().catch(error=>{assets=null;throw error;});
  A.actions['export-pdf-report']=async button=>{
    const report=A.cache.report;if(!report)return;
    const search=A.$('.report-results .local-table-filters input[type="search"]')?.value.trim()||'';
    const keys=Object.keys(report.columns),body=report.items.map(item=>keys.map(key=>A.reportValue(key,item[key])))
      .filter(row=>!search||row.join(' ').toLocaleLowerCase().includes(search.toLocaleLowerCase()));
    const brand=A.$('#club-brand-name').textContent.trim(),author=A.config.user.full_name;
    const label=button.innerHTML;button.disabled=true;button.setAttribute('aria-busy','true');button.textContent='Preparing PDF…';
    try{
      const [regular,bold,logo]=await load();
      const doc=new window.jspdf.jsPDF({orientation:'landscape',unit:'pt',format:'a4',compress:true,putOnlyUsedFonts:true});
      doc.addFileToVFS('NotoSans-Regular.ttf',regular);doc.addFont('NotoSans-Regular.ttf','NotoSans','normal');
      doc.addFileToVFS('NotoSans-Bold.ttf',bold);doc.addFont('NotoSans-Bold.ttf','NotoSans','bold');doc.setFont('NotoSans');
      doc.setProperties({title:report.title,subject:`${report.from} to ${report.to} · ${report.courtLabel}`,author,creator:brand+' Reports & Analytics'});
      const width=doc.internal.pageSize.getWidth(),height=doc.internal.pageSize.getHeight(),margin=36;
      const generated=new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Manila'}).format(new Date());
      doc.setFillColor(27,37,42);doc.circle(margin+20,44,22,'F');doc.addImage(logo,'PNG',margin,24,40,40);
      doc.setFont('NotoSans','bold');doc.setFontSize(14);doc.setTextColor(22,72,79);doc.text(brand,margin+52,49);
      doc.setFont('NotoSans','normal');doc.setFontSize(8);doc.setTextColor(76,91,100);
      doc.text(`Generated: ${generated} (Asia/Manila)`,width-margin,34,{align:'right'});
      doc.text(doc.splitTextToSize('Prepared by: '+author,310),width-margin,48,{align:'right'});
      doc.setFont('NotoSans','bold');doc.setFontSize(20);doc.setTextColor(26,38,48);doc.text(report.title,margin,92);
      doc.setDrawColor(22,72,79);doc.setLineWidth(1.2);doc.line(margin,103,width-margin,103);
      doc.setFont('NotoSans','normal');doc.setFontSize(9);doc.setTextColor(56,72,82);
      const filters=[`Reporting period: ${report.from} to ${report.to}`,`Court: ${report.courtLabel}`,`Records: ${body.length}`];
      if(search)filters.push('Table search: '+search);
      const filterLines=doc.splitTextToSize(filters.join('   |   '),width-margin*2);doc.text(filterLines,margin,123);
      let y=123+filterLines.length*12;
      doc.setFontSize(8);doc.setTextColor(76,91,100);
      const note=doc.splitTextToSize(report.note,width-margin*2);doc.text(note,margin,y+6);y+=note.length*11+18;
      const columnStyles={};keys.forEach((key,index)=>{
        if(['amount','gross','refund','net','total_bookings','completed_bookings'].includes(key))columnStyles[index]={halign:'right'};
        if(key==='reason')columnStyles[index]={cellWidth:185};
      });
      doc.autoTable({
        head:[Object.values(report.columns)],
        body:body.length?body:[[{content:'No records match this report’s filters.',colSpan:keys.length,styles:{halign:'center',cellPadding:18}}]],
        startY:y,margin:{top:64,right:margin,bottom:38,left:margin},theme:'grid',showHead:'everyPage',rowPageBreak:'avoid',
        styles:{font:'NotoSans',fontSize:8,cellPadding:7,overflow:'linebreak',valign:'top',textColor:[26,38,48],lineColor:[213,221,224],lineWidth:.4,minCellWidth:38},
        headStyles:{fillColor:[22,72,79],textColor:[255,255,255],fontStyle:'bold',lineColor:[22,72,79],cellPadding:8},
        alternateRowStyles:{fillColor:[246,249,250]},columnStyles,
        willDrawPage:({pageNumber})=>{
          if(pageNumber===1)return;
          doc.setFont('NotoSans','bold');doc.setFontSize(10);doc.setTextColor(22,72,79);doc.text(brand,margin,29);
          doc.setFont('NotoSans','normal');doc.setFontSize(9);doc.setTextColor(56,72,82);doc.text(report.title,margin,46);
          doc.setFontSize(8);doc.text(`${report.from} to ${report.to}`,width-margin,29,{align:'right'});
        }
      });
      const pages=doc.getNumberOfPages();
      for(let page=1;page<=pages;page++){
        doc.setPage(page);doc.setDrawColor(213,221,224);doc.setLineWidth(.5);doc.line(margin,height-29,width-margin,height-29);
        doc.setFont('NotoSans','normal');doc.setFontSize(7);doc.setTextColor(76,91,100);
        doc.text(brand+' · '+report.title,margin,height-16);doc.text(`Page ${page} of ${pages}`,width-margin,height-16,{align:'right'});
      }
      await doc.save(`pikol-${report.type}-${report.from}-${report.to}.pdf`,{returnPromise:true});
    }finally{button.disabled=false;button.removeAttribute('aria-busy');button.innerHTML=label;A.icons();}
  };
})();
