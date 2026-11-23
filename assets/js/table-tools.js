/* Database lists keep filters across pages; supplementary tables page their loaded rows. */
(() => {
  const A=window.Admin||window.Player,E=A.e,sizes=[5,10,20,25,50,100];
  const controls=(total,page,pages,size,local=false)=>{
    const action=local?'data-local-page':'data-action="page" data-number';
    const button=(n,label,disabled=false,current=false)=>`<button type="button" class="secondary-button ${current?'current-page':''}" ${action}="${n}" ${disabled?'disabled':''} ${current?'aria-current="page"':''} aria-label="${E(label)}">${E(label)}</button>`;
    const first=Math.max(1,Math.min(page-2,pages-4)),last=Math.min(pages,first+4);
    const choices=[...new Set([...sizes,size])].sort((a,b)=>a-b);
    return `<div class="pagination table-pagination"><div class="table-page-summary"><span>${window.Admin?'Showing ':''}${total?((page-1)*size+1)+'–'+Math.min(page*size,total):'0'} of ${Number(total).toLocaleString()} records</span><label>Rows <select ${local?'data-local-size':'data-server-size'} aria-label="Rows per page">${choices.map(n=>`<option value="${n}" ${n===size?'selected':''}>${n}</option>`).join('')}</select></label></div><nav class="button-row" aria-label="Table pagination">${window.Admin?`${button(page-1,'Previous',page<=1)}<span class="table-page-position">Page ${page} of ${pages}</span>${button(page+1,'Next',page>=pages)}`:`${button(1,'First',page<=1)}${button(page-1,'Previous',page<=1)}${Array.from({length:last-first+1},(_,i)=>button(first+i,String(first+i),false,first+i===page)).join('')}${button(page+1,'Next',page>=pages)}${button(pages,'Last',page>=pages)}`}</nav></div>`;
  };
  A.tablePagination=data=>controls(Number(data.total),Number(data.page),Number(data.pages),Number(data.per_page)||20);
  document.addEventListener('change',event=>{
    if(!event.target.matches('[data-server-size]'))return;
    A.filters[A.page]={...(A.filters[A.page]||{}),per_page:Number(event.target.value),page:1};A.reload();
  });
  // Filter fields apply when their value changes; Enter also submits searches.
  document.addEventListener('change',event=>{
    const form=event.target.closest('form[data-filters],form#player-payment-filters,form#reservation-filters');
    if(form&&event.target.matches('input,select')&&form.checkValidity())form.requestSubmit();
  });
  document.addEventListener('submit',event=>{
    if(!event.target.matches('[data-filters],#player-payment-filters,#reservation-filters'))return;
    const form=event.target;if(!form.elements.namedItem('per_page')){const field=document.createElement('input');field.type='hidden';field.name='per_page';field.value=A.filters[A.page]?.per_page||20;form.append(field);}
  },true);
  const enhance=()=>document.querySelectorAll('.admin-table').forEach(table=>{
    if(table.dataset.tableTools)return;
    table.dataset.tableTools='true';
    if(table.closest('.panel')?.querySelector('.pagination')&&!table.closest('.admin-dialog'))return;
    const rows=[...table.tBodies[0]?.rows||[]];if(!rows.length)return;
    const wrap=table.closest('.table-scroll')||table;
    const toolbar=document.createElement('div');toolbar.className='filters local-table-filters';
    toolbar.innerHTML='<input type="search" aria-label="Filter table records" placeholder="Filter loaded records...">';
    const footer=document.createElement('div');wrap.before(toolbar);wrap.after(footer);
    let page=1,size=10;
    const render=()=>{
      const term=toolbar.querySelector('input').value.trim().toLocaleLowerCase();
      const filtered=rows.filter(row=>[...row.cells].filter((cell,index)=>!/(actions?|proof)/i.test(table.tHead?.rows[0]?.cells[index]?.textContent||'')).map(cell=>cell.textContent).join(' ').toLocaleLowerCase().includes(term));
      const pages=Math.max(1,Math.ceil(filtered.length/size));page=Math.min(page,pages);
      const visible=new Set(filtered.slice((page-1)*size,page*size));rows.forEach(row=>row.hidden=!visible.has(row));
      footer.innerHTML=controls(filtered.length,page,pages,size,true)+(!filtered.length?'<p class="table-no-results" role="status">No matching records.</p>':'');
    };
    toolbar.querySelector('input').addEventListener('input',()=>{page=1;render();});
    toolbar.addEventListener('change',event=>{if(event.target.matches('[data-local-size]')){size=Number(event.target.value);page=1;render();}});
    footer.addEventListener('click',event=>{const button=event.target.closest('[data-local-page]');if(button){page=Number(button.dataset.localPage);render();}});
    footer.addEventListener('change',event=>{if(event.target.matches('[data-local-size]')){size=Number(event.target.value);page=1;render();}});
    render();
  });
  const enhanceCourts=()=>document.querySelectorAll('.court-grid').forEach(grid=>{
    if(grid.dataset.tableTools)return;grid.dataset.tableTools='true';
    const cards=[...grid.querySelectorAll('.court-card')];if(!cards.length)return;
    const toolbar=document.createElement('div');toolbar.className='filters';toolbar.innerHTML='<input type="search" aria-label="Search courts" placeholder="Search courts..."><select aria-label="Court status"><option value="">All court statuses</option><option value="active">Active</option><option value="disabled">Disabled</option><option value="maintenance">Maintenance</option></select>';
    const footer=document.createElement('div');grid.before(toolbar);grid.after(footer);let page=1,size=10;
    const render=()=>{const term=toolbar.querySelector('input').value.trim().toLowerCase(),status=toolbar.querySelector('select').value;const filtered=cards.filter(card=>(card.querySelector('.court-content')?.textContent||'').toLowerCase().includes(term)&&(!status||card.querySelector('.status-badge')?.textContent.trim().toLowerCase()===status));const pages=Math.max(1,Math.ceil(filtered.length/size));page=Math.min(page,pages);const visible=new Set(filtered.slice((page-1)*size,page*size));cards.forEach(card=>card.hidden=!visible.has(card));footer.innerHTML=controls(filtered.length,page,pages,size,true)+(!filtered.length?'<p class="table-no-results" role="status">No matching courts.</p>':'');};
    toolbar.addEventListener('input',()=>{page=1;render();});footer.addEventListener('click',event=>{const button=event.target.closest('[data-local-page]');if(button){page=Number(button.dataset.localPage);render();}});footer.addEventListener('change',event=>{if(event.target.matches('[data-local-size]')){size=Number(event.target.value);page=1;render();}});render();
  });
  let scheduled=false;new MutationObserver(()=>{if(!scheduled){scheduled=true;queueMicrotask(()=>{scheduled=false;enhance();enhanceCourts();});}}).observe(document.body,{childList:true,subtree:true});enhance();enhanceCourts();
})();
