(() => {
  const A=window.Admin;
  A.palette={Pending:'#D5A844',Confirmed:'#18B7AE',Completed:'#69C59B',Cancelled:'#D86D79','No-show':'#8B9CA5'};
  Chart.defaults.color='#91A8B3';Chart.defaults.font.family='Inter, sans-serif';Chart.defaults.font.size=10;Chart.defaults.borderColor='#3b474f66';
  A.chart=(id,type,labels,data,options={})=>{
    const canvas=A.$('#'+id);if(!canvas)return;
    const colors=labels.map(label=>A.palette[label]||'#18b7ae');
    const chart=new Chart(canvas,{type,data:{labels,datasets:[{label:options.label||'Reservations',data,borderColor:options.color||'#18b7ae',backgroundColor:type==='doughnut'?colors:(options.color?options.color+'80':'#18b7ae22'),borderWidth:type==='doughnut'?0:2,fill:type==='line',tension:.35,pointRadius:2,pointHoverRadius:5,borderRadius:type==='bar'?4:undefined,barPercentage:.6}]},options:{responsive:true,maintainAspectRatio:false,animation:A.reduced()?false:{duration:650},cutout:type==='doughnut'?'76%':undefined,plugins:{legend:{display:false},tooltip:{backgroundColor:'#17262e',borderColor:'#4e6d7b',borderWidth:1,padding:12,callbacks:options.currency?{label:ctx=>' '+A.money(ctx.parsed.y??ctx.parsed)}:{}}},...(type!=='doughnut'?{scales:{x:{grid:{display:false},ticks:{maxTicksLimit:7,maxRotation:0},border:{display:false}},y:{beginAtZero:true,ticks:{precision:0,callback:options.currency?value=>'₱'+(value>=1000?(value/1000)+'k':value):undefined},border:{display:false},grid:{color:document.documentElement.dataset.adminTheme==='light'?'#d4dfe3':'#3b474f66'}}}}:{})}});
    A.charts.push(chart);return chart;
  };
})();
