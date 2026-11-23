/* Shared, server-backed admin workspace. No business records are stored in the browser. */
(() => {
  'use strict';
  const config=JSON.parse(document.querySelector('#admin-config').textContent);
  const A=window.Admin={config,page:config.page,views:{},actions:{},filters:{},cache:{},charts:[],calendar:null};
  A.$=(selector,root=document)=>root.querySelector(selector);
  A.$$=(selector,root=document)=>[...root.querySelectorAll(selector)];
  A.e=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  A.money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',maximumFractionDigits:2,minimumFractionDigits:0}).format(Number(value)||0);
  A.date=value=>value?new Intl.DateTimeFormat('en-PH',{day:'numeric',month:'short',year:'numeric',timeZone:'Asia/Manila'}).format(new Date(String(value).length===10?value+'T12:00:00+08:00':String(value).replace(' ','T')+'+08:00')):'—';
  A.time=value=>{if(!value)return '—';const [h,m]=value.split(':');return `${Number(h)%12||12}:${m} ${Number(h)<12?'AM':'PM'}`;};
  A.today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  A.icon=(name)=>`<i data-lucide="${A.e(name)}"></i>`;
  A.icons=()=>window.lucide?.createIcons({attrs:{'stroke-width':1.7}});
  A.reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
  A.animate=(target,vars)=>{if(window.gsap&&!A.reduced())gsap.from(target,{duration:.4,y:10,opacity:0,ease:'power2.out',clearProps:'opacity,transform',...vars});};
  A.api=async(endpoint,data={},method='GET')=>{
    let url='../api/admin/'+endpoint+'.php'; const options={method,credentials:'same-origin',headers:{Accept:'application/json'}};
    if(method==='GET'){const params=new URLSearchParams();Object.entries(data).forEach(([key,value])=>{if(value!==undefined&&value!==null&&value!=='')params.set(key,value);});url+='?'+params;}
    else {options.headers['X-CSRF-Token']=A.config.csrf;if(data instanceof FormData)options.body=data;else {options.headers['Content-Type']='application/json';options.body=JSON.stringify(data);}}
    const liveGuard=method==='GET'&&A.liveRefreshing&&A.liveCanRefresh?.()?A.liveCanRefresh:null;
    if(liveGuard)options.headers['X-Pikol-Background']='1';
    const response=await fetch(url,options);let result;
    try{result=await response.json();}catch{throw Error('The server returned an invalid response. Check PHP and MySQL are running.');}
    if(response.status===401){location.assign('../index.html#login');throw Error('Your session expired.');}
    if(!response.ok){const error=Error(result.error||'The request could not be completed.');error.fields=result.fields||{};throw error;}
    if(liveGuard&&!liveGuard())throw Error('Live refresh deferred.');
    if(result.csrf)A.config.csrf=result.csrf;
    return result;
  };
  A.toast=(message,type='success')=>{const el=document.createElement('div');el.className='admin-toast '+(type==='error'?'error':'');el.textContent=message;A.$('#toast-region').append(el);A.animate(el,{x:20,y:0});setTimeout(()=>el.remove(),5500);};
  A.heading=(eyebrow,title,description,actions='')=>`<div class="page-heading"><div><span class="eyebrow">${A.e(eyebrow)}</span><h1>${A.e(title)}</h1><p>${A.e(description)}</p></div><div class="heading-actions">${actions}</div></div>`;
  A.button=(action,label,icon='plus',style='primary-button',attrs='')=>`<button type="button" class="${style}" data-action="${action}" ${attrs}>${A.icon(icon)}${A.e(label)}</button>`;
  A.empty=(title,copy,icon='calendar-days',button='')=>`<div class="empty-state"><div class="empty-icon">${A.icon(icon)}</div><h3>${A.e(title)}</h3><p>${A.e(copy)}</p>${button}</div>`;
  A.badge=value=>`<span class="status-badge status-${A.e(String(value).toLowerCase().replaceAll(' ','-'))}">${A.e(value)}</span>`;
  A.option=(value,label,selected)=>`<option value="${A.e(value)}" ${String(value)===String(selected)?'selected':''}>${A.e(label)}</option>`;
  A.pagination=result=>A.tablePagination(result);
  A.reservationTable=(items,compact=false)=>{
    if(!items.length)return A.empty('No reservations yet','New reservations will appear here. Create a manual reservation to get started.','calendar-days',A.button('new-reservation','Create reservation'));
    return `<div class="table-scroll"><table class="admin-table"><thead><tr>${['Booking Reference','Customer','Court','Booking Date','Start Time','End Time','Amount','Booking Status','Payment Status','Actions'].map(t=>`<th>${t}</th>`).join('')}</tr></thead><tbody>${items.map(b=>`<tr><td><button class="text-button reference" data-action="reservation-detail" data-id="${b.booking_id}">${A.e(b.reference_no)}</button></td><td><div class="customer-cell"><span class="avatar reservation-avatar" aria-hidden="true">${A.e(Array.from(b.full_name||'')[0]||'')}${b.avatar_path?`<img src="../${A.e(b.avatar_path)}" alt="" loading="lazy" data-customer-avatar>`:''}</span><span>${A.e(b.full_name)}${!compact?`<small>${A.e(b.email)}</small>`:''}</span></div></td><td>${A.e(b.court_name.split('—')[0].trim())}</td><td>${A.date(b.booking_date)}</td><td>${A.time(b.start_time)}</td><td>${A.time(b.end_time)}</td><td>${A.money(b.court_fee)}</td><td>${A.badge(b.booking_status)}</td><td>${A.badge(b.payment_status)}</td><td><div class="table-actions"><button class="icon-button" title="View reservation" aria-label="View ${A.e(b.reference_no)}" data-action="reservation-detail" data-id="${b.booking_id}">${A.icon('eye')}</button>${b.booking_status==='Pending'?`<button class="icon-button" title="Confirm reservation" aria-label="Confirm ${A.e(b.reference_no)}" data-action="reservation-status" data-status="Confirmed" data-id="${b.booking_id}">${A.icon('check')}</button>`:''}${['Pending','Confirmed'].includes(b.booking_status)?`<button class="icon-button" title="Cancel reservation" aria-label="Cancel ${A.e(b.reference_no)}" data-action="reservation-status" data-status="Cancelled" data-id="${b.booking_id}">${A.icon('x')}</button>`:''}${b.booking_status==='Confirmed'?`<button class="icon-button" title="Mark completed" aria-label="Complete ${A.e(b.reference_no)}" data-action="reservation-status" data-status="Completed" data-id="${b.booking_id}">${A.icon('circle-check')}</button>`:''}${A.archiveButton('reservations',b.booking_id,b.reference_no,b.archived_at)}</div></td></tr>`).join('')}</tbody></table></div>`;
  };
  A.catalog=async(refresh=false)=>{if(!A.cache.courts||refresh)A.cache.courts=await A.api('courts');return A.cache.courts;};
  A.navigate=async(page,push=true)=>{
    if(!A.views[page])return;
    A.page=page;document.body.classList.remove('sidebar-open');A.$('.drawer-backdrop').hidden=true;
    A.$$('.nav-link[data-page]').forEach(link=>{const active=link.dataset.page===(config.navigation[page].parent||page);link.classList.toggle('active',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
    A.$('#header-title').textContent=config.navigation[page].title;A.$('#header-title').setAttribute('href',config.navigation[page].file);A.$('#header-title').dataset.page=page;document.title=config.navigation[page].title+' · Pikol Castle';
    if(push)history.pushState({page},'',config.navigation[page].file);
    A.charts.forEach(chart=>chart.destroy());A.charts=[];if(A.calendar){A.calendar.destroy();A.calendar=null;}
    A.$('#admin-main').innerHTML='<div class="page-loading" role="status"><span class="spinner"></span>Loading your workspace…</div>';
    try {await A.views[page]();if(A.page!==page)return;A.icons();A.animate('#admin-main > *',{stagger:.035});A.animate('.kpi-card',{stagger:.045,y:8});A.animate('.chart-wrap',{delay:.1,y:5});}
    catch(error){if(A.page!==page)return;A.$('#admin-main').innerHTML=`<div class="error-state"><h2>We couldn’t load this page.</h2><p>${A.e(error.message)}</p>${A.button('reload','Try again','refresh-cw','secondary-button')}</div>`;A.icons();}
  };
  A.reload=()=>A.navigate(A.page,false);
  A.actions.reload=A.reload;
  A.actions.page=button=>{A.filters[A.page]={...(A.filters[A.page]||{}),page:button.dataset.number};return A.reload();};
  A.actions.sidebar=()=>{if(innerWidth<=767){const open=document.body.classList.toggle('sidebar-open');A.$('.drawer-backdrop').hidden=!open;}else {const collapsed=document.body.classList.toggle('sidebar-collapsed');localStorage.setItem('pikol.admin.sidebar',collapsed?'collapsed':'expanded');}A.$('[aria-controls="sidebar"]').setAttribute('aria-expanded',String(innerWidth<=767?document.body.classList.contains('sidebar-open'):!document.body.classList.contains('sidebar-collapsed')));setTimeout(()=>A.calendar?.updateSize(),260);};
  A.actions['profile-menu']=button=>{const menu=A.$('#profile-menu');menu.hidden=!menu.hidden;button.setAttribute('aria-expanded',String(!menu.hidden));if(!menu.hidden)A.animate(menu,{y:-6,duration:.18});};
  A.actions.profile=async()=>{await A.navigate('settings');A.$('#profile-settings')?.scrollIntoView({behavior:A.reduced()?'instant':'smooth',block:'center'});};
  A.actions.logout=()=>A.confirm('Log out?','You can sign in again to continue managing the castle.',async()=>{await A.api('auth',{action:'logout'},'POST');location.assign('../index.html');},{label:'Log out'});
  A.loadNotifications=async()=>{
    const data=await A.api('notifications');A.cache.notifications=data;
    for(const [selector,count,label] of [['#notification-count',data.unread,'unread notifications'],['#nav-pending',data.pending_reservations,'pending reservations'],['#nav-pending-payments',data.pending_payments,'payments awaiting verification']]){
      const badge=A.$(selector);badge.textContent=count>99?'99+':count;badge.hidden=!count;
      badge.setAttribute('aria-label',`${count} ${label}`);badge.title=`${count} ${label}`;
    }
    return data;
  };
  A.actions.notifications=async button=>{const menu=A.$('#notification-menu');menu.hidden=!menu.hidden;button.setAttribute('aria-expanded',String(!menu.hidden));if(menu.hidden)return;const data=await A.loadNotifications();menu.innerHTML=`<div class="notification-heading"><strong>Notifications</strong><button class="text-button" data-action="read-notifications">Mark all read</button></div>${data.items.length?data.items.map(n=>`<button class="notification-item ${n.read_at?'':'unread'}" data-action="read-notification" data-id="${n.notification_id}" data-entity="${A.e(n.entity_type)}" data-record="${n.entity_id||''}"><strong>${A.e(n.title)}</strong><span>${A.e(n.message)}</span><small>${A.date(n.created_at)}</small></button>`).join(''):A.empty('You’re all caught up','New activity will appear here.','bell')}`;A.icons();A.animate(menu,{duration:.2,y:-6});};
  A.actions['read-notifications']=async()=>{await A.api('notifications',{action:'read-all'},'POST');A.$('#notification-menu').hidden=true;await A.loadNotifications();};
  A.actions['read-notification']=async button=>{await A.api('notifications',{notification_id:button.dataset.id},'POST');A.$('#notification-menu').hidden=true;await A.loadNotifications();if(button.dataset.entity==='reservations'&&button.dataset.record)await A.actions['reservation-detail']({dataset:{id:button.dataset.record}});else await A.navigate('courts');};
  document.addEventListener('click',async event=>{
    // Check the original click before an async action replaces its Lucide icon.
    if(!event.target.closest('.dropdown-anchor')){A.$('#profile-menu').hidden=true;A.$('#notification-menu').hidden=true;A.$$('.dropdown-anchor > [aria-expanded="true"]').forEach(button=>button.setAttribute('aria-expanded','false'));}
    const link=event.target.closest('[data-page]');if(link){event.preventDefault();if(link.dataset.pendingLink)A.filters.reservations={status:'Pending'};A.$('#profile-menu').hidden=true;await A.navigate(link.dataset.page);return;}
    const button=event.target.closest('[data-action]');if(button&&!button.disabled&&A.actions[button.dataset.action]){event.preventDefault();try{await A.actions[button.dataset.action](button,event);}catch(error){A.toast(error.message,'error');}}
  });
  document.addEventListener('submit',event=>{
    if(event.target.matches('[data-filters]')){event.preventDefault();A.filters[A.page]=Object.fromEntries(new FormData(event.target));A.filters[A.page].page=1;A.reload();}
    if(event.target.id==='global-search'){event.preventDefault();A.filters.reservations={search:new FormData(event.target).get('search')};A.navigate('reservations');}
  });
  window.addEventListener('popstate',()=>{const current=new URL(location.href),file=current.pathname.split('/').pop();const page=Object.keys(config.navigation).find(key=>{const route=new URL(config.navigation[key].file,current);return route.pathname===current.pathname&&[...route.searchParams].every(([name,value])=>current.searchParams.get(name)===value);})||(file==='dashboard.php'?'dashboard':config.page);A.navigate(page,false);});
  window.addEventListener('resize',()=>{if(innerWidth>767){document.body.classList.remove('sidebar-open');A.$('.drawer-backdrop').hidden=true;}});
  document.addEventListener('DOMContentLoaded',async()=>{try{if(localStorage.getItem('pikol.admin.sidebar')==='collapsed'&&innerWidth>767)document.body.classList.add('sidebar-collapsed');}catch{}A.icons();await A.navigate(config.page,false);A.loadNotifications().catch(()=>{});if(!window.PikolLive)setInterval(()=>{if(!document.hidden)A.loadNotifications().catch(()=>{});},30000);});
})();
