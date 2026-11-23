(() => {
  const A=window.Admin,E=A.e;
  const moneyFields=['amount','gross','refund','net'];
  A.reportValue=(key,value)=>{
    if(value===null||value===undefined||value==='')return 'Not recorded';
    if(moneyFields.includes(key))return A.money(value);
    if(key==='utilization')return value+'%';
    if(['available_hours','booked_hours'].includes(key))return value+' hr';
    return String(value);
  };
  const cell=(key,value)=>{
    if(value===null||value===undefined||value==='')return '<span class="muted">Not recorded</span>';
    if(key==='status')return A.badge(value);
    return E(A.reportValue(key,value));
  };
  A.views.reports=async(isCurrent=()=>true)=>{
    const f={from:A.today().slice(0,7)+'-01',to:A.today(),type:'bookings',...(A.filters.reports||{})};
    const [data,catalog]=await Promise.all([A.api('reports',f),A.catalog()]);if(A.page!=='reports'||!isCurrent())return;
    A.filters.reports={...f,type:data.type};
    A.cache.report={...data,courtLabel:f.court_id?(catalog.items.find(c=>String(c.court_id)===String(f.court_id))?.court_name||'Selected court'):'All courts'};
    A.$('#admin-main').innerHTML=A.heading('','Reports & Analytics','Choose a report to explore your reservations, payments, courts and players.',A.button('export-selected-report','Export CSV','download','secondary-button')+A.button('export-pdf-report','Export PDF','file-down','secondary-button'))+`
      <form class="filters report-filters" data-filters>
        <label class="report-type-field">Select Report Type<select name="type">${Object.entries(data.types).map(([key,title])=>A.option(key,title,data.type)).join('')}</select></label>
        <label>From<input type="date" name="from" required value="${E(f.from)}"></label>
        <label>To<input type="date" name="to" required value="${E(f.to)}"></label>
        <label>Court<select name="court_id">${A.option('','All courts',f.court_id)}${catalog.items.map(c=>A.option(c.court_id,c.court_name,f.court_id)).join('')}</select></label>
        <button type="submit" class="primary-button">Apply filters</button>
      </form>
      <div class="report-summary">${data.metrics.map(([label,value,kind])=>`<div class="kpi-card"><div class="kpi-top">${E(label)}</div><div class="kpi-value">${E(kind==='money'?A.money(value):value)}</div><span class="muted report-range">${E(data.from)} &ndash; ${E(data.to)}</span></div>`).join('')}</div>
      <section class="panel report-chart-panel"><div class="panel-heading"><div><h2>${E(data.title)} analytics</h2><p>${E(data.chart.label)}</p></div></div><div class="panel-body">${data.chart.labels.length?'<div class="chart-wrap"><canvas id="selected-report-chart" role="img" aria-label="'+E(data.chart.label)+'"></canvas></div>':A.empty('No activity for these filters','Choose a different date range or court to see analytics.','chart-no-axes-combined')}</div></section>
      <section class="panel section-spacer report-results"><div class="panel-heading"><div><h2>${E(data.title)}</h2><p>${data.items.length} ${data.items.length===1?'record':'records'} &middot; ${E(data.from)} &ndash; ${E(data.to)}</p></div></div><p class="report-explanation">${E(data.note)}</p><div class="table-scroll"><table class="admin-table report-detail-table"><thead><tr>${Object.values(data.columns).map(label=>`<th scope="col">${E(label)}</th>`).join('')}</tr></thead><tbody>${data.items.length?data.items.map(item=>`<tr>${Object.keys(data.columns).map(key=>`<td>${cell(key,item[key])}</td>`).join('')}</tr>`).join(''):`<tr><td colspan="${Object.keys(data.columns).length}"><div class="report-empty">No records match this report's date range and court.</div></td></tr>`}</tbody></table></div></section>`;
    const form=A.$('.report-filters');form.querySelectorAll('select').forEach(select=>select.addEventListener('change',()=>form.requestSubmit()));
    if(data.chart.labels.length)A.chart('selected-report-chart','bar',data.chart.labels,data.chart.values,{currency:data.chart.currency,label:data.chart.label});
  };
  A.actions['export-selected-report']=()=>location.assign('../api/admin/export.php?'+new URLSearchParams(A.filters.reports));
})();
