/* Additional settings stay inside the existing administrator workspace. */
(() => {
  const A=window.Admin,E=A.e,original=A.views.settings;
  let tab='general',revision=0;
  A.actions.profile=async()=>{tab='general';await A.navigate('settings');A.$('#profile-settings')?.scrollIntoView({behavior:A.reduced()?'instant':'smooth',block:'center'});};
  const filters={audit:{per_page:10},backups:{per_page:10}};
  const tabs=[['general','settings-2','General','Business, booking and administrator settings'],['payment','wallet','Payments','Payment recipient names and account numbers'],['security','shield-check','Security','Password, session and sign-in protection'],['audit','list-checks','Audit Logs','Account activity and system changes'],['backups','database-backup','Backup Data','Create and download database backups']];
  const stamp=v=>v?E(v)+' <small>Asia/Manila</small>':'Never';
  const badge=v=>`<span class="status-badge ${['Completed','success'].includes(v)?'status-confirmed':['Failed','failure'].includes(v)?'status-cancelled':'status-pending'}">${E(v)}</span>`;
  const footer=label=>`<div class="settings-form-footer"><p class="muted">Changes apply to future relevant operations.</p><button class="primary-button" type="submit">${A.icon('save')}${E(label)}</button></div>`;
  const row=(name,title,copy,control)=>`<div class="policy-row"><div><label for="setting-${name}">${E(title)}</label><p>${E(copy)}</p></div><div class="policy-control">${control}<small class="setting-error" data-error="${name}" role="alert"></small></div></div>`;
  const number=(s,k,title,copy,min,max)=>row(k,title,copy,`<input id="setting-${k}" name="${k}" type="number" min="${min}" max="${max}" step="1" value="${E(s[k])}" required aria-describedby="error-${k}">`);
  const check=(s,k,title,copy)=>row(k,title,copy,`<input id="setting-${k}" name="${k}" type="checkbox" role="switch" value="1" ${Number(s[k])?'checked':''}>`);
  const options=(values,current)=>values.map(([value,label])=>A.option(value,label,current)).join('');
  const auditLocation=r=>`<span data-audit-location data-audit-id="${Number(r.id)}" data-audit-ip="${E(r.ip_address||'')}" ${r.location?.status==='pending'?'data-location-pending':''} title="Approximate location of the recorded IP address">${E(r.location?.label||'Not available')}</span>`;
  async function fillAuditLocations(root){
    const groups=new Map();
    root.querySelectorAll('[data-location-pending]').forEach(el=>{const ip=el.dataset.auditIp;if(!groups.has(ip))groups.set(ip,[]);groups.get(ip).push(el);});
    const queue=[...groups.values()];
    const worker=async()=>{
      while(queue.length&&root.isConnected){
        const elements=queue.shift();let label='Location unavailable';
        try{const result=await A.api('audit-location',{id:elements[0].dataset.auditId});label=result.location?.label||label;}catch{}
        elements.forEach(el=>{if(el.isConnected){el.textContent=label;el.removeAttribute('data-location-pending');}});
      }
    };
    await Promise.all([worker(),worker()]);
  }
  function pager(data){return `<div class="pagination table-pagination"><span>${data.total} records · Page ${data.page} of ${data.pages}</span><label>Rows <select data-settings-size aria-label="Records per page">${options([10,25,50].map(n=>[n,n]),data.per_page)}</select></label><div class="button-row"><button type="button" class="secondary-button" data-action="settings-page" data-page-number="${data.page-1}" ${data.page<=1?'disabled':''}>Previous</button><button class="secondary-button" data-action="settings-page" data-page-number="${data.page+1}" ${data.page>=data.pages?'disabled':''}>Next</button></div></div>`;}
  const shell=()=>`<nav class="settings-tabs" aria-label="Settings sections">${tabs.map(([key,icon,title,description])=>`<button type="button" data-action="settings-tab" data-tab="${key}" class="${tab===key?'active':''}" aria-current="${tab===key?'page':'false'}" title="${E(description)}">${A.icon(icon)}${title}</button>`).join('')}</nav>`;
  A.views.settings=async()=>{
    const version=++revision;
    if(tab==='general'){
      const currentHeading=A.$('#admin-main > .page-heading'),currentTabs=A.$('#admin-main > .settings-tabs');
      await original(()=>version===revision);if(A.page!=='settings'||version!==revision)return;
      if(currentHeading&&currentTabs){A.$('#admin-main > .page-heading').replaceWith(currentHeading);currentHeading.after(currentTabs);}
      const heading=A.$('#admin-main > .page-heading');if(!currentTabs)heading.insertAdjacentHTML('afterend',shell());
      const content=document.createElement('div');content.id='settings-content';content.className='settings-content';
      [...A.$('#admin-main').children].filter(el=>el!==heading&&!el.matches('.settings-tabs')).forEach(el=>content.append(el));
      A.$('#admin-main').append(content);
      const profile=A.$('#profile-settings');if(profile){const policy=(await A.api('security-settings')).settings;profile.querySelector('[name=new_password]').minLength=policy.minimum_password_length;profile.querySelector('[name=new_password]').maxLength=policy.maximum_password_length;profile.querySelector('[name=new_password]').placeholder=policy.minimum_password_length+' to '+policy.maximum_password_length+' characters';}
      A.icons();
      return;
    }
    if(!A.$('#admin-main > .settings-tabs'))A.$('#admin-main').innerHTML=A.heading('', 'Settings','Manage account security, review activity and protect your booking records.').replace('<span class="eyebrow"></span>','')+shell()+'<div id="settings-content"></div>';
    const panel=A.$('#settings-content');panel.className='page-loading';panel.setAttribute('role','status');panel.innerHTML='<span class="spinner"></span>Loading settings...';
    A.icons();
    try{
      let markup;
      if(tab==='security')markup=security((await A.api('security-settings')).settings);
      else if(tab==='payment')markup=paymentAccounts(await A.api('payment-settings'));
      else if(tab==='audit')markup=audit(await A.api('audit-logs',filters.audit));
      else markup=backups(await A.api('backups',filters.backups));
      if(A.page!=='settings'||version!==revision)return;
      const content=A.$('#settings-content');content.className='settings-content';content.removeAttribute('role');content.innerHTML=markup;A.icons();A.animate(content,{y:6,duration:.22});
      if(tab==='audit')void fillAuditLocations(content);
      if(tab==='security')void refreshLockedAccounts();
    }catch(error){if(version!==revision)return;const content=A.$('#settings-content');if(content){content.className='error-state';content.innerHTML=`<h2>Settings could not be loaded</h2><p>${E(error.message)}</p>${A.button('reload','Try again','refresh-cw','secondary-button')}`;A.icons();}}
  };
  A.liveSettingsRefresh=async changes=>{
    if(tab==='security'&&changes.includes('locks')){await refreshLockedAccounts();return;}
    if(tab==='audit'&&changes.includes('audit')){
      const data=await A.api('audit-logs',filters.audit);
      if(A.page!=='settings'||tab!=='audit')return;
      const content=A.$('#settings-content');content.innerHTML=audit(data);A.icons();void fillAuditLocations(content);return;
    }
    if(tab!=='backups'||!changes.includes('backups'))return;
    const data=await A.api('backups',filters.backups);
    if(A.page!=='settings'||tab!=='backups')return;
    const template=document.createElement('template');template.innerHTML=backups(data);
    const content=A.$('#settings-content'),panels=content?.querySelectorAll(':scope > section.panel');
    if(!panels||panels.length!==2)return;
    const updated=template.content.querySelectorAll('section.settings-panel');
    panels[0].querySelector('.backup-overview')?.replaceWith(updated[0].querySelector('.backup-overview'));
    panels[1].replaceWith(updated[1]);A.icons();
  };
  function audit(data){
    const f=filters.audit;
    return `<section class="panel settings-panel"><header class="panel-heading"><div><h2>Audit Logs</h2><p>Monitor account activity and important actions performed within the pickleball booking system.</p></div>${A.button('reload','Refresh','refresh-cw','secondary-button')}</header><form id="settings-filter-form" class="settings-filter-grid"><label>Search user<input name="search" type="search" placeholder="Name or email" value="${E(f.search||'')}"></label><label>Search action<input name="action" type="search" placeholder="Action or description" value="${E(f.action||'')}"></label><label>Date range<select name="range">${options([['','Any date'],['today','Today'],['7','Last 7 days'],['30','Last 30 days'],['custom','Custom date range']],f.range)}</select></label><label>Role<select name="role">${options([['','All roles'],['admin','Admin'],['customer','Customer'],['system','System']],f.role)}</select></label><label>Action type<select name="entity">${options([['','All actions'],['authentication','Authentication'],['user','Accounts'],['booking','Bookings'],['court','Courts'],['payment','Payments'],['settings','Business settings'],['security','Security settings'],['backup','Backups'],['backup_settings','Backup settings']],f.entity)}</select></label><label>Result<select name="result">${options([['','All results'],['success','Success'],['failure','Failure']],f.result)}</select></label>${f.range==='custom'?`<label>From<input name="from" type="date" value="${E(f.from||'')}"></label><label>To<input name="to" type="date" value="${E(f.to||'')}"></label>`:''}</form>${data.items.length?`<div class="table-scroll"><table class="admin-table settings-audit-table"><thead><tr>${['User','Time','Action','Details','IP Address','Location','Device','Browser','OS'].map(title=>`<th>${title}</th>`).join('')}</tr></thead><tbody>${data.items.map(r=>`<tr><td><button class="text-button" data-action="audit-detail" data-id="${r.id}">${E(r.actor_name)}</button><small class="cell-secondary">${E(r.actor_email)} · ${E(r.actor_role)}</small></td><td>${stamp(r.created_at)}</td><td><button class="text-button" data-action="audit-detail" data-id="${r.id}">${E(r.action)}</button></td><td class="audit-description">${E(r.description||'—')}</td><td>${E(r.ip_address||'Not available')}</td><td>${auditLocation(r)}</td><td>${E(r.device_type)}</td><td>${E(r.browser)}</td><td>${E(r.operating_system)}</td></tr>`).join('')}</tbody></table></div>`:A.empty('No audit records match','Change your filters or wait for new account and booking activity.','list-checks')}${pager(data)}<p class="settings-footnote">Read-only records. Locations are approximate. Local or private addresses have no public location.</p></section>`;
  }
  function paymentMethodForm(method={}){
    const enabled=method.customer_enabled===undefined||Number(method.customer_enabled),isNew=!method.payment_method_id;
    return `<form class="payment-account-form" data-payment-id="${E(method.payment_method_id||'new')}"><input type="hidden" name="payment_method_id" value="${E(method.payment_method_id||'')}"><div class="payment-method-heading"><h3>${E(method.name||'New payment method')}</h3><label class="payment-enable"><input type="checkbox" name="customer_enabled" value="1" ${enabled?'checked':''}>Available at checkout</label></div><div class="form-error section-spacer" role="alert" hidden></div><div class="form-grid section-spacer"><label>Bank or wallet name<input name="name" value="${E(method.name||'')}" maxlength="50" autocomplete="off" placeholder="For example, MariBank" required></label><label>Account name<input name="account_name" value="${E(method.account_name||'')}" maxlength="100" autocomplete="off" placeholder="Registered account holder" ${enabled?'required':''}></label><label>Account number<input name="account_number" type="text" value="${E(method.account_number||'')}" maxlength="50" autocomplete="off" placeholder="Enter account number" ${enabled?'required':''}><small>Leading zeroes and spaces are preserved.</small></label><label>Payment instructions (optional)<textarea name="instructions" maxlength="2000" placeholder="Instructions customers should follow">${E(method.instructions||'')}</textarea></label><div class="payment-qr-editor full">${method.qr_image?`<a href="../${E(method.qr_image)}" target="_blank" rel="noopener" aria-label="Open ${E(method.name||'payment')} QR image"><img src="../${E(method.qr_image)}" alt="${E(method.name||'Payment')} QR code"></a>`:''}<label>${method.qr_image?'Replace QR image':'Upload QR image'}<input name="qr_image" type="file" accept="image/jpeg,image/png,image/webp" ${enabled&&!method.qr_image?'required':''} data-has-qr="${method.qr_image?'1':'0'}"><small>QR code images only (JPEG, PNG or WebP, up to 3 MB). Other pictures are rejected. Include the complete, readable QR code.</small></label></div></div><div class="settings-form-footer"><p class="muted">Staff verification is still required after customers submit a receipt.</p><div class="button-row">${isNew?'<button type="button" class="secondary-button" data-action="cancel-payment-method">Cancel</button>':''}<button type="submit" class="primary-button">${A.icon('save')}${isNew?'Add payment method':'Save payment method'}</button>${!isNew?`<button type="button" class="danger-button" data-action="remove-payment-method">${A.icon('trash-2')}Remove</button>`:''}</div></div></form>`;
  }
  function paymentAccounts(data){
    return `<section class="panel settings-panel"><header class="panel-heading"><div class="settings-section-title">${A.icon('wallet')}<div><h2>Payment methods</h2><p>Add banks and wallets, set recipient details, and upload their payment QR codes.</p></div></div>${A.button('add-payment-method','Add payment method','plus','primary-button')}</header><div class="panel-body" id="payment-method-list">${data.methods.map(paymentMethodForm).join('')||'<p class="muted" data-payment-empty>No payment methods yet. Add a bank or wallet to get started.</p>'}</div><p class="settings-footnote">Disable a method to hide it from new checkouts. Existing payment records are preserved.</p></section>`;
  }
  A.actions['add-payment-method']=()=>{
    const list=A.$('#payment-method-list');if(!list)return;
    if(!list.querySelector('[data-payment-id="new"]'))list.insertAdjacentHTML('afterbegin',paymentMethodForm());
    list.querySelector('[data-payment-id="new"] [name=name]')?.focus();A.icons();
  };
  A.actions['remove-payment-method']=async button=>{
    const form=button.closest('.payment-account-form');button.disabled=true;
    try{await A.api('payment-settings',{action:'remove',payment_method_id:form.dataset.paymentId},'POST');form.remove();const list=A.$('#payment-method-list');if(list&&!list.querySelector('.payment-account-form'))list.innerHTML='<p class="muted" data-payment-empty>No payment methods yet. Add a bank or wallet to get started.</p>';A.toast('Payment method removed.');}
    finally{if(button.isConnected)button.disabled=false;}
  };
  A.actions['cancel-payment-method']=button=>button.closest('.payment-account-form')?.remove();
  document.addEventListener('change',async event=>{
    const form=event.target.closest('.payment-account-form');if(!form)return;
    if(event.target.name==='customer_enabled'){
      const enabled=event.target.checked;form.elements.account_name.required=enabled;form.elements.account_number.required=enabled;
      form.elements.qr_image.required=enabled&&form.elements.qr_image.dataset.hasQr!=='1';
    }
    if(event.target.name==='qr_image'&&event.target.files[0]){
      const input=event.target,file=input.files[0],button=form.querySelector('[type=submit]'),error=form.querySelector('.form-error');
      const hint=input.nextElementSibling,help=hint.textContent;hint.textContent='Checking QR code...';
      const data=new FormData();data.set('action','validate-qr');data.set('qr_image',file);
      input.disabled=true;button.disabled=true;form.dataset.checkingQr='true';form.setAttribute('aria-busy','true');error.hidden=true;
      try{await A.api('payment-settings',data,'POST');}
      catch(e){input.value='';error.textContent=e.message;error.hidden=false;}
      finally{input.disabled=false;button.disabled=false;hint.textContent=help;delete form.dataset.checkingQr;form.removeAttribute('aria-busy');}
    }
  });
  const lockedAccounts=data=>`<section class="panel settings-panel section-spacer locked-accounts-panel" id="locked-accounts"><header class="panel-heading"><div class="settings-section-title">${A.icon('lock-keyhole')}<div><h2>Locked Accounts</h2><p>Accounts appear here only while failed sign-in attempts have locked them.</p></div></div><div class="button-row"><span class="status-badge">${data?data.total+' locked':'Loading...'}</span>${A.button('refresh-locked-accounts','Refresh','refresh-cw','secondary-button')}</div></header><p class="locked-accounts-error" role="alert" hidden></p><div class="table-scroll"><table class="admin-table"><thead><tr>${['Account','Role','Failed Logins','Locked Until','Actions'].map(label=>`<th scope="col">${label}</th>`).join('')}</tr></thead><tbody>${data?.items.length?data.items.map(user=>`<tr><td><strong>${E(user.full_name)}</strong><small class="cell-secondary">${E(user.email)}</small></td><td>${E(user.role==='admin'?'Admin':'Customer / Player')}</td><td>${E(user.failed_attempts)}</td><td>${stamp(user.locked_until)}</td><td data-inline-actions>${A.button('unlock-account','Unlock','lock-open','secondary-button',`data-user-id="${user.user_id}"`)}</td></tr>`).join(''):`<tr><td colspan="5" class="locked-accounts-empty">${data?'No accounts are currently locked from failed sign-ins.':'Loading locked accounts...'}</td></tr>`}</tbody></table></div></section>`;
  let lockRequest=0;
  async function refreshLockedAccounts(){
    const root=A.$('#locked-accounts');if(!root||A.page!=='settings'||tab!=='security')return;const request=++lockRequest;
    try{const data=await A.api('locked-accounts');if(request!==lockRequest||!root.isConnected)return;root.outerHTML=lockedAccounts(data);A.icons();}
    catch(error){if(request!==lockRequest||!root.isConnected)return;const message=root.querySelector('.locked-accounts-error');message.hidden=false;message.textContent=error.message;}
  }
  A.actions['refresh-locked-accounts']=refreshLockedAccounts;
  A.actions['unlock-account']=async button=>{button.disabled=true;try{await A.api('locked-accounts',{action:'unlock',user_id:button.dataset.userId},'POST');A.toast('Account unlocked. They can try signing in again.');await refreshLockedAccounts();}finally{if(button.isConnected)button.disabled=false;}};
  setInterval(()=>{if(!document.hidden)void refreshLockedAccounts();},15000);
  function security(s){
    return `<section class="panel settings-panel settings-reference"><header class="panel-heading"><div class="settings-section-title">${A.icon('lock-keyhole')}<div><h2>Password &amp; Session Policy</h2><p>Configure password strength, session timeout, and account lockout rules.</p></div></div></header><form id="security-policy-form"><div class="form-error settings-inline-error" role="alert" hidden></div><div class="policy-rows">${number(s,'maximum_password_length','Maximum Password Length','Default 64. Allowed range: '+s.minimum_password_length+' to 256 characters.',s.minimum_password_length,256)}${number(s,'session_timeout_minutes','Player Session Timeout (minutes)','Default 30. Minimum 1. Inactive customers/players are signed out. Admin accounts are exempt.',1,1440)}${number(s,'lockout_failed_attempts','Lockout After Failed Attempts','Default 5. Only the individual account is locked after this many consecutive failed attempts.',3,20)}${number(s,'lockout_duration_minutes','Lockout Duration (minutes)','Default 15. Each locked account can sign in again after this duration. Other accounts are unaffected.',1,1440)}</div>${footer('Save Settings')}</form></section>${lockedAccounts(null)}`;
  }
  function backups(data){
    if(data.direct_download_only)return `<section class="panel settings-panel"><header class="panel-heading"><div class="settings-section-title">${A.icon('database')}<div><h2>Database backup</h2><p>Download an SQL copy directly to your device.</p></div></div>${A.button('manual-backup','Download database backup','download','primary-button')}</header><div class="panel-body"><p>No backup folder or schedule is needed. Keep your downloaded file somewhere safe. Uploaded photos and receipts need a separate file backup.</p></div></section>`;
    const f=filters.backups,s=data.settings;
    const scheduled=String(s.scheduled_at||'').replace(' ','T').slice(0,16);
    return `<section class="panel settings-panel settings-reference"><header class="panel-heading"><div class="settings-section-title">${A.icon('database')}<div><h2>Backup Schedule</h2><p>Schedule server backups where supported, or download a database backup directly.</p></div></div>${A.button('reload','Refresh','refresh-cw','secondary-button')}</header><div class="backup-overview"><div><span>Automation</span><strong>${Number(s.automatic_backup_enabled)?(data.scheduler_operational?'Enabled':'Awaiting scheduler'):'Manual only'}</strong></div><div><span>Frequency</span><strong>${E(s.backup_frequency)}</strong></div><div><span>Last automatic run</span><strong>${data.last_automatic?stamp(data.last_automatic):'Never'}</strong></div></div><form id="backup-policy-form"><div class="form-error settings-inline-error" role="alert" hidden></div><div class="policy-rows">${check(s,'automatic_backup_enabled','Automatic backup','Run backups on the schedule below without manual action.')}</div><div class="backup-schedule-fields"><label>Backup schedule <span aria-hidden="true">*</span><select name="backup_frequency" required>${options(['daily','weekly','monthly'].map(value=>[value,value[0].toUpperCase()+value.slice(1)]),s.backup_frequency)}</select></label><label>Backup date/time <span aria-hidden="true">*</span><input name="scheduled_at" type="datetime-local" required min="2000-01-01T00:00" max="2100-12-31T23:59" value="${E(scheduled)}"><small>Asia/Manila</small></label></div>${Number(s.automatic_backup_enabled)&&!data.scheduler_operational?'<p class="settings-note settings-warning">Schedule saved. The scheduler has not reported a recent run; automatic execution is not yet confirmed.</p>':''}${footer('Save Settings')}</form></section><section class="panel settings-panel settings-reference section-spacer"><header class="panel-heading"><div class="settings-section-title">${A.icon('archive')}<div><h2>Backup History</h2><p>Previously saved server backups. Direct downloads are recorded in Audit Logs.</p></div></div>${A.button('manual-backup','Download database backup','download','primary-button',`data-database="${E(data.database)}"`)}</header><form id="settings-filter-form" class="settings-filter-grid"><label>Search reference<input name="search" type="search" value="${E(f.search||'')}" placeholder="BKP-..."></label><label>Backup type<select name="type">${options([['','All types'],['Manual','Manual'],['Automatic','Automatic']],f.type)}</select></label><label>Status<select name="status">${options([['','All statuses'],['Pending','Pending'],['Completed','Completed'],['Failed','Failed']],f.status)}</select></label></form>${data.items.length?`<div class="table-scroll"><table class="admin-table settings-backup-table"><thead><tr>${['Backup ID','Backup date/time','Backup type','File name','File size','Status','Actions'].map(title=>`<th>${title}</th>`).join('')}</tr></thead><tbody>${data.items.map(r=>`<tr><td>${E(r.backup_reference)}</td><td>${stamp(r.created_at)}</td><td>${E(r.backup_type)}</td><td class="backup-filename">${E(r.file_name)}</td><td>${(Number(r.file_size)/1024).toFixed(2)} KB</td><td>${badge(r.status)}</td><td><div class="button-row">${A.button('backup-detail','View details','eye','text-button',`data-id="${r.id}"`)}${r.status==='Completed'?`<a class="text-button" href="../api/admin/backup-download.php?id=${r.id}">${A.icon('download')}Download backup</a>`:''}</div></td></tr>`).join('')}</tbody></table></div>`:A.empty('No saved server backups','Use Download database backup to save an SQL copy on your device.','database-backup')}${pager(data)}<p class="settings-footnote">Direct downloads are saved by your browser and are not retained on the server. Uploaded images and payment screenshots require separate file backups. On Windows, the scheduled task must be configured for signed-out execution to run while its user is signed out.</p></section>`;
  }
  async function savePaymentMethod(form){
    if(form.dataset.checkingQr==='true')return;
    const button=form.querySelector('[type=submit]'),error=form.querySelector('.form-error');button.disabled=true;error.hidden=true;
    try{
      const result=await A.api('payment-settings',new FormData(form),'POST');
      if(form.isConnected){form.outerHTML=paymentMethodForm(result.method);A.$('[data-payment-empty]')?.remove();A.icons();}
      A.toast('Payment method saved.');
    }catch(e){if(form.isConnected){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}}
    finally{if(button.isConnected)button.disabled=false;}
  }
  async function saveForm(form,endpoint,data,reload=true){
    const button=form.querySelector('[type=submit]'),error=form.querySelector('.form-error');button.disabled=true;error.hidden=true;
    form.querySelectorAll('[data-error]').forEach(el=>el.textContent='');form.querySelectorAll('[aria-invalid]').forEach(el=>el.removeAttribute('aria-invalid'));
    try{const result=await A.api(endpoint,data,'POST');A.toast(result.warning||'Settings saved.',result.warning?'error':'success');if(reload)await A.reload();}
    catch(e){error.textContent=e.message;error.hidden=false;Object.entries(e.fields||{}).forEach(([key,message])=>{const el=form.querySelector('[data-error="'+key+'"]');if(el){el.id='error-'+key;el.textContent=message;}form.elements.namedItem(key)?.setAttribute('aria-invalid','true');});error.scrollIntoView({block:'nearest'});}
    finally{button.disabled=false;}
  }
  document.addEventListener('submit',event=>{
    const form=event.target;
    if(form.id==='security-policy-form'){event.preventDefault();saveForm(form,'security-settings',new FormData(form));}
    if(form.matches('.payment-account-form')){event.preventDefault();savePaymentMethod(form);}
    if(form.id==='backup-policy-form'){event.preventDefault();const data=new FormData(form);data.set('action','settings');saveForm(form,'backups',data);}
    if(form.id==='settings-filter-form'){event.preventDefault();applyFilters(form);}
  });
  let recordsRequest=0;
  async function refreshSettingsRecords(){
    if(A.page!=='settings'||!['audit','backups'].includes(tab))return;
    const selected=tab,version=revision,request=++recordsRequest,content=A.$('#settings-content');
    const panel=content?.querySelector('section.panel:last-child');if(!panel)return;
    const scroll=window.scrollY,horizontal=panel.querySelector('.table-scroll')?.scrollLeft||0;
    const focused=document.activeElement?.closest('[data-action="settings-page"]')?.textContent.trim();
    const buttons=[...panel.querySelectorAll('[data-action="settings-page"]')].map(button=>[button,button.disabled]);
    buttons.forEach(([button])=>button.disabled=true);panel.setAttribute('aria-busy','true');
    try{
      const data=await A.api(selected==='audit'?'audit-logs':'backups',{...filters[selected]});
      if(request!==recordsRequest||version!==revision||tab!==selected||A.page!=='settings'||!panel.isConnected)return;
      const template=document.createElement('template');template.innerHTML=selected==='audit'?audit(data):backups(data);
      const next=template.content.querySelector('section.panel:last-child');panel.replaceWith(next);A.icons();
      const table=next.querySelector('.table-scroll');if(table)table.scrollLeft=horizontal;
      if(focused){const candidates=[...next.querySelectorAll('[data-action="settings-page"]:not(:disabled)')];(candidates.find(button=>button.textContent.trim()===focused)||candidates[0])?.focus({preventScroll:true});}
      window.scrollTo({top:scroll,behavior:'instant'});if(selected==='audit')void fillAuditLocations(next);
    }finally{if(panel.isConnected){panel.removeAttribute('aria-busy');buttons.forEach(([button,disabled])=>button.disabled=disabled);}}
  }
  function applyFilters(form){const old=filters[tab]||{},f={...Object.fromEntries(new FormData(form)),per_page:old.per_page||10,page:1};if(tab==='audit'&&f.range!=='custom'){delete f.from;delete f.to;if(f.range){const date=new Date(A.today()+'T12:00:00Z');f.to=date.toISOString().slice(0,10);date.setUTCDate(date.getUTCDate()-(f.range==='today'?0:Number(f.range)-1));f.from=date.toISOString().slice(0,10);}}filters[tab]=f;void refreshSettingsRecords().catch(error=>A.toast(error.message,'error'));}
  document.addEventListener('change',event=>{if(event.target.closest('#settings-filter-form'))applyFilters(event.target.form);if(event.target.matches('[data-settings-size]')){filters[tab].per_page=Number(event.target.value);filters[tab].page=1;void refreshSettingsRecords().catch(error=>A.toast(error.message,'error'));}});
  A.actions['settings-tab']=async button=>{
    const next=button.dataset.tab;
    if(A.page!=='settings'||next===tab||!tabs.some(([key])=>key===next))return;
    tab=next;
    A.$$('.settings-tabs [data-tab]').forEach(item=>{const active=item.dataset.tab===tab;item.classList.toggle('active',active);item.setAttribute('aria-current',active?'page':'false');});
    await A.views.settings();
  };
  A.actions['settings-page']=async button=>{filters[tab].page=Number(button.dataset.pageNumber);await refreshSettingsRecords();};
  A.actions['audit-detail']=async button=>{const r=await A.api('audit-logs',{id:button.dataset.id});const fields={'Audit ID':r.id,'User ID':r.user_id||'Not authenticated','User name':r.actor_name,'Email':r.actor_email||'Not available','Role at time':r.actor_role,'Action':r.action,'Timestamp':r.created_at+' Asia/Manila','Description':r.description||'—','IP address':r.ip_address||'Not available','Location':r.location?.label||'Not available','Browser':r.browser,'Operating system':r.operating_system,'Device':r.device_type,'Entity type':r.entity_type||'—','Entity ID':r.entity_id||'—','Result':r.result_status};A.modal('Audit record',`<div class="dialog-body"><dl class="detail-grid">${Object.entries(fields).map(([k,v])=>`<div><dt>${E(k)}</dt><dd>${k==='Location'?auditLocation(r):E(v)}</dd></div>`).join('')}</dl>${r.changes?`<h3 class="section-spacer">Changed values</h3><pre class="settings-json">${E(JSON.stringify(r.changes,null,2))}</pre>`:''}</div>`,{wide:true});void fillAuditLocations(A.$('#modal-root'));};
  const backupInfo=r=>`<dl class="detail-grid">${Object.entries({'Backup ID':r.backup_reference,'Type':r.backup_type,'Created':r.created_at,'Completed':r.completed_at||'Not completed','Status':r.status,'File':r.file_name,'Size':Number(r.file_size).toLocaleString()+' bytes','Storage':'Private server storage','SHA-256':r.checksum||'Not available','Error':r.error_summary||'None'}).map(([k,v])=>`<div><dt>${E(k)}</dt><dd>${E(v)}</dd></div>`).join('')}</dl>`;
  A.actions['backup-detail']=async button=>{const r=await A.api('backups',{id:button.dataset.id});A.modal('Backup details',`<div class="dialog-body">${backupInfo(r)}</div>`,{wide:true});};
  A.actions['manual-backup']=async button=>{
    const original=button.innerHTML;button.disabled=true;button.textContent='Preparing download...';
    try{
      const response=await fetch('../api/admin/database-export.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':A.config.csrf}});
      if(!response.ok){let message='The database backup could not be downloaded. Please try again.';try{message=(await response.json()).error||message;}catch{}throw Error(message);}
      const file=await response.blob();if(!file.size)throw Error('The database download is empty. Please try again.');
      const filename=response.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1]||'pikol-database.sql';
      const url=URL.createObjectURL(file),link=document.createElement('a');link.href=url;link.download=filename;document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);A.toast('Database backup download started.');
    }finally{if(button.isConnected){button.disabled=false;button.innerHTML=original;A.icons();}}
  };
  A.actions['backup-delete']=async button=>{const r=await A.api('backups',{id:button.dataset.id});A.formModal('Delete backup',`<p>The last remaining verified backup cannot be deleted.</p><p class="section-spacer">Type <strong>${E(r.backup_reference)}</strong> to delete this backup file.</p><label class="section-spacer">Backup reference<input name="confirm" required autocomplete="off"></label>`,async data=>{data.set('action','delete');data.set('id',r.id);await A.api('backups',data,'POST');A.toast('Backup deleted.');await A.reload();},{danger:true,label:'Delete backup'});};
  A.actions['backup-restore']=async button=>{const r=await A.api('backups',{id:button.dataset.id});A.formModal('Restore backup — verify and preview',`${backupInfo(r)}<div class="helper-note section-spacer">Restoration can replace current records. This preview does not change the database. A server operator must use the documented recovery procedure.</div><label class="section-spacer">Current administrator password<input name="current_password" type="password" autocomplete="current-password" maxlength="4096" required></label><label class="section-spacer">Type RESTORE PREVIEW<input name="confirm" autocomplete="off" required></label>`,async data=>{data.set('action','restore-preview');data.set('id',r.id);const result=await A.api('backups',data,'POST');A.closeModal();A.modal('Restore preview verified',`<div class="dialog-body"><p>${E(result.message)}</p><dl class="detail-grid section-spacer"><div><dt>Reference</dt><dd>${E(result.reference)}</dd></div><div><dt>Checksum</dt><dd>${E(result.checksum)}</dd></div></dl><h3 class="section-spacer">Operator recovery steps</h3><ol class="settings-recovery"><li>Create and verify a new recovery backup.</li><li>Enable server maintenance mode and stop background jobs.</li><li>Use tools/verify-backup.php to import into a disposable database first.</li><li>Follow docs/BACKUP-RESTORE.md to restore from the verified artifact.</li><li>Run the installation checks and invalidate old sessions before reopening.</li></ol></div>`,{wide:true});},{label:'Verify restore preview',wide:true,keepOpen:true});};
})();
