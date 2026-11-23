/* Archive controls share the workspace's record menus, filters and pagination. */
(() => {
  const A=window.Admin,E=A.e;
  const modules={courts:'Courts',reservations:'Reservations',customers:'Customers',payments:'Payments'};
  A.archiveButton=(module,id,name,archived=false)=>A.button(archived?'restore-record':'archive-record',archived?'Restore':'Archive',archived?'rotate-ccw':'archive','text-button',`data-module="${E(module)}" data-id="${Number(id)}" data-name="${E(name)}"`);
  A.archivedButton=module=>A.button('open-archived','Archived','archive','secondary-button',`data-module="${E(module)}"`);
  A.actions['open-archived']=async button=>{
    const module=button.dataset.module;
    if(!modules[module])return;
    await A.navigate('archived-'+module);
  };
  A.actions['archive-back']=button=>modules[button.dataset.module]?A.navigate(button.dataset.module):undefined;
  A.actions['archive-record']=button=>{
    const {module,id,name}=button.dataset;
    const detail=module==='courts'?'This court will be removed from booking choices.':module==='customers'?'This customer will be signed out and unable to sign in while archived.':'Booking history and payment totals will be preserved.';
    return A.confirm('Archive '+(name||'this record')+'?',detail+' You can restore it from Archived.',async()=>{
      await A.api('archives',{action:'archive',module,id},'POST');
      delete A.cache.courts;delete A.cache.payments;
      A.toast('Record archived. Restore it from Archived.');await A.reload();
    },{label:'Archive'});
  };
  A.actions['restore-record']=button=>{
    const {module,id,name}=button.dataset;
    return A.confirm('Restore '+(name||'this record')+'?','It will return to '+modules[module]+'. Courts and customer accounts keep the status they had before archiving.',async()=>{
      await A.api('archives',{action:'restore',module,id},'POST');
      delete A.cache.courts;delete A.cache.payments;
      A.toast('Record restored to '+modules[module]+'.');await A.reload();
    },{label:'Restore'});
  };
  const archivedView=async module=>{
    const page='archived-'+module,label=modules[module],f=A.filters[page]||{},data=await A.api('archives',{...f,module});
    if(A.page!==page)return;
    const actions=A.button('archive-back','Back to '+A.config.navigation[module].title,'arrow-left','secondary-button',`data-module="${E(module)}"`)+A.button('reload','Refresh','refresh-cw','secondary-button');
    const heading=A.heading('','Archived '+label,'Find and restore archived '+label.toLowerCase()+'.',actions).replace('<span class="eyebrow"></span>','');
    A.$('#admin-main').innerHTML=heading+`<form class="filters archive-filters" data-filters>
      <input type="search" name="search" maxlength="150" aria-label="Search archived ${E(label.toLowerCase())}" placeholder="Search name, email, or reference" value="${E(f.search||'')}">
    </form><section class="panel">${data.items.length?`<div class="table-scroll"><table class="admin-table archive-table"><thead><tr>${['Record','Status','Archived on','Archived by','Actions'].map(title=>`<th>${title}</th>`).join('')}</tr></thead><tbody>${data.items.map(r=>`<tr>
      <td><strong>${E(r.title)}</strong><small class="cell-secondary">${E(r.detail)}</small></td>
      <td>${A.badge(r.status)}</td>
      <td>${A.date(r.archived_at)}<small class="cell-secondary">${E(r.archived_at.slice(11,16))}</small></td>
      <td>${E(r.archived_by)}</td><td data-inline-actions>${A.button('restore-record','Restore','rotate-ccw','secondary-button',`data-module="${E(r.module)}" data-id="${Number(r.record_id)}" data-name="${E(r.title)}"`)}</td>
    </tr>`).join('')}</tbody></table></div>`:A.empty('No archived '+label.toLowerCase(),f.search?'Try a different search.':'Use Archive in '+A.config.navigation[module].title+' to move records here.','archive')}${A.pagination(data)}</section>`;
  };
  Object.keys(modules).forEach(module=>{A.views['archived-'+module]=()=>archivedView(module);});
})();
