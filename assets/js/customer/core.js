/* Player workspace: all account, court, reservation and payment data comes from PHP. */
(() => {
  const config=JSON.parse(document.querySelector('#player-config').textContent);
  const P=window.Player={config,page:config.page,views:{},actions:{},filters:{},cache:{},cleanup:[],revision:0,routeParams:Object.fromEntries(new URLSearchParams(location.search))};
  P.$=(selector,root=document)=>root.querySelector(selector);P.$$=(selector,root=document)=>[...root.querySelectorAll(selector)];
  P.e=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  P.money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',minimumFractionDigits:0,maximumFractionDigits:2}).format(Number(value)||0);
  P.today=()=>luxon.DateTime.now().setZone('Asia/Manila').toISODate();
  P.date=value=>luxon.DateTime.fromISO(String(value).slice(0,10),{zone:'Asia/Manila'}).toFormat('dd LLL yyyy');
  P.datetime=value=>luxon.DateTime.fromSQL(String(value),{zone:'Asia/Manila'}).toFormat('dd LLL yyyy · h:mm a');
  P.time=value=>luxon.DateTime.fromISO('2000-01-01T'+value,{zone:'Asia/Manila'}).toFormat('h:mm a');
  P.icon=name=>`<i data-lucide="${P.e(name)}"></i>`;P.icons=()=>lucide.createIcons({attrs:{'stroke-width':1.7}});
  P.reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
  P.animate=(targets,vars={})=>{if(window.gsap&&!P.reduced())gsap.from(targets,{y:12,opacity:0,duration:.4,clearProps:'transform,opacity',...vars});};
  P.api=async(endpoint,data={},method='GET')=>{
    let url=config.guest?'../api/guest-checkout.php?action='+encodeURIComponent(endpoint):'../api/'+(endpoint==='auth'?'auth':'customer/'+endpoint)+'.php';const options={method,credentials:'same-origin',headers:{Accept:'application/json'}};
    if(method==='GET'){const query=new URLSearchParams();Object.entries(data).forEach(([k,v])=>{if(v!==null&&v!==undefined&&v!=='')query.set(k,v);});url+=(url.includes('?')?'&':'?')+query;}
    else{options.headers['X-CSRF-Token']=P.config.csrf;if(data instanceof FormData)options.body=data;else{options.headers['Content-Type']='application/json';options.body=JSON.stringify(data);}}
    const liveGuard=method==='GET'&&P.liveRefreshing&&P.liveCanRefresh?.()?P.liveCanRefresh:null;
    if(liveGuard)options.headers['X-Pikol-Background']='1';
    const response=await fetch(url,options);let result;try{result=await response.json();}catch{throw Error('We could not read the server response. Please try again.');}
    if(response.status===401&&!config.guest){if(config.embedded)parent.postMessage({type:'pikol-account-expired'},location.origin);else location.assign('../index.html#login');throw Error('Please sign in again.');}if(!response.ok)throw Error(result.error||'The request could not be completed.');if(liveGuard&&!liveGuard())throw Error('Live refresh deferred.');if(result.csrf)P.config.csrf=result.csrf;if(config.embedded&&endpoint==='notifications'&&method==='POST')parent.postMessage({type:'pikol-notifications-updated'},location.origin);if(config.embedded&&endpoint==='profile'&&method==='POST')parent.postMessage({type:'pikol-account-updated'},location.origin);return result;
  };
  P.badge=status=>`<span class="status-badge status-${P.e(String(status).toLowerCase().replaceAll(' ','-'))}">${P.e(status)}</span>`;
  P.option=(value,label,selected)=>`<option value="${P.e(value)}" ${String(value)===String(selected)?'selected':''}>${P.e(label)}</option>`;
  P.button=(action,text,icon='arrow-up-right',style='primary-button',attrs='')=>`<button type="button" class="${style}" data-action="${action}" ${attrs}>${P.icon(icon)}${P.e(text)}</button>`;
  P.empty=(title,copy,icon='calendar-days',button='')=>`<div class="empty-state"><div class="empty-icon">${P.icon(icon)}</div><h3>${P.e(title)}</h3><p>${P.e(copy)}</p>${button}</div>`;
  P.heading=(eyebrow,title,copy,buttons='')=>`<div class="page-heading"><div><span class="eyebrow">${P.e(eyebrow)}</span><h1>${P.e(title)}</h1><p>${P.e(copy)}</p></div><div class="heading-actions">${buttons}</div></div>`;
  P.toast=(message,type='success')=>{const el=document.createElement('div');el.className='admin-toast '+(type==='error'?'error':'');el.textContent=message;P.$('#toast-region').append(el);P.animate(el,{x:15,y:0});setTimeout(()=>el.remove(),5500);};
  P.pagination=data=>P.tablePagination(data);
  P.loading=()=>'<div class="player-skeleton" role="status" aria-label="Loading your workspace"><div></div><div></div><div></div></div>';
  P.catalog=async(refresh=false)=>{if(!P.cache.catalog||refresh)P.cache.catalog=await P.api('available-courts');return P.cache.catalog;};
  P.go=async(page,params={},push=true)=>{
    if(config.guest&&page!=='book')return;
    if(page==='book'&&!params.reschedule&&!(config.embedded&&params.checkout==='1')){P.actions.book();return;}
    if(!P.views[page])return;P.revision++;P.page=page;P.routeParams=params;P.cleanup.splice(0).forEach(fn=>fn());P.motion?.clear();
    document.body.classList.remove('sidebar-open');if(P.$('.drawer-backdrop'))P.$('.drawer-backdrop').hidden=true;P.$('#profile-menu').hidden=true;P.$('#notification-menu').hidden=true;
    P.$$('.nav-link[data-page]').forEach(link=>{const active=link.dataset.page===page;link.classList.toggle('active',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
    P.$('#player-title').textContent=config.navigation[page].title;document.title=config.navigation[page].title+' · '+config.business.venue_name;
    if(push)history.pushState({page},'',config.navigation[page].file+(Object.keys(params).length||config.embedded?'?'+new URLSearchParams({...params,...(config.embedded?{embedded:1}:{})}):''));
    P.$('#player-main').innerHTML=P.loading();
    try{await P.views[page]();if(P.page!==page)return;P.icons();P.motion?.reveal();}
    catch(error){if(P.page!==page)return;P.$('#player-main').innerHTML=`<div class="error-state"><h2>Let’s try that again.</h2><p>${P.e(error.message)}</p>${P.button('refresh','Reload this page','refresh-cw','secondary-button')}</div>`;P.icons();}
  };
  P.reload=()=>P.go(P.page,P.routeParams,false);P.actions.refresh=P.reload;
  P.actions.book=()=>{
    if(config.embedded)parent.postMessage({type:'pikol-open-slot-picker'},location.origin);
    else location.assign('../index.html?book-slots=1#booking');
  };
  P.actions.page=button=>{P.filters[P.page]={...(P.filters[P.page]||{}),page:Number(button.dataset.number)};return P.reload();};
  P.actions.sidebar=()=>{if(innerWidth<=767){const open=document.body.classList.toggle('sidebar-open');P.$('.drawer-backdrop').hidden=!open;}else{document.body.classList.toggle('sidebar-collapsed');}P.$('[aria-controls=sidebar]').setAttribute('aria-expanded',String(innerWidth<=767?document.body.classList.contains('sidebar-open'):!document.body.classList.contains('sidebar-collapsed')));P.animate('#sidebar .nav-link',{x:-8,y:0,stagger:.018,duration:.22});setTimeout(()=>P.calendar?.updateSize(),260);};
  P.actions['profile-menu']=button=>{const menu=P.$('#profile-menu');menu.hidden=!menu.hidden;button.setAttribute('aria-expanded',String(!menu.hidden));if(!menu.hidden)P.animate(menu,{y:-6,duration:.18});};
  P.actions['account-settings']=async()=>{await P.go('profile');P.$('#account-security')?.scrollIntoView({behavior:P.reduced()?'instant':'smooth',block:'center'});};
  P.actions.logout=()=>P.confirm('Log out?','You can sign in again any time to manage your games.',async()=>{await P.api('auth',{action:'logout'},'POST');if(config.embedded)parent.postMessage({type:'pikol-account-logout'},location.origin);else location.assign('../index.html');},{label:'Log out'});
  P.pollNotifications=async()=>{if(config.guest)return {items:[],unread:0};const data=await P.api('notifications');P.cache.notifications=data;const badge=P.$('#notification-count');badge.textContent=data.unread>99?'99+':data.unread;badge.hidden=!data.unread;return data;};
  P.actions['notification-menu']=async button=>{const menu=P.$('#notification-menu');menu.hidden=!menu.hidden;button.setAttribute('aria-expanded',String(!menu.hidden));if(menu.hidden)return;const data=await P.pollNotifications();menu.innerHTML='<div class="notification-heading"><strong>Booking reminders</strong><button class="text-button" data-page="notifications">View all</button></div>'+P.notificationItems(data.items.slice(0,5));P.icons();P.animate(menu,{duration:.2,y:-5});};
  P.notificationItems=items=>items.length?items.map(n=>`<button class="notification-item ${n.read_at?'':'unread'}" data-action="read-notification" data-id="${n.notification_id}" data-booking="${n.entity_id||''}"><strong>${P.e(n.title)}</strong><span>${P.e(n.message)}</span><small>${P.datetime(n.created_at)} ? ${n.read_at?'Read':'Unread'}</small></button>`).join(''):P.empty('You’re all caught up','Reminders for your upcoming confirmed bookings will appear here.','bell');
  P.actions['read-notification']=async button=>{await P.api('notifications',{notification_id:button.dataset.id},'POST');P.$('#notification-menu').hidden=true;await P.pollNotifications();if(button.dataset.booking)await P.showReservation(button.dataset.booking);else await P.reload();};
  document.addEventListener('click',async event=>{
    // Check the original click before an async action replaces its Lucide icon.
    if(!event.target.closest('.dropdown-anchor')){P.$('#profile-menu').hidden=true;P.$('#notification-menu').hidden=true;P.$$('.dropdown-anchor > [aria-expanded="true"]').forEach(button=>button.setAttribute('aria-expanded','false'));}
    const link=event.target.closest('[data-page]');if(link){event.preventDefault();await P.go(link.dataset.page);return;}
    const button=event.target.closest('[data-action]');if(button&&!button.disabled&&P.actions[button.dataset.action]){event.preventDefault();try{await P.actions[button.dataset.action](button,event);}catch(e){P.toast(e.message,'error');}}
  });
  window.addEventListener('popstate',()=>{const file=location.pathname.split('/').pop();const page=Object.keys(config.navigation).find(k=>config.navigation[k].file===file)||'dashboard';P.go(page,Object.fromEntries(new URLSearchParams(location.search)),false);});
  document.addEventListener('DOMContentLoaded',async()=>{P.$('[aria-controls=sidebar]')?.setAttribute('aria-expanded',String(innerWidth>767));P.icons();await P.go(config.page,P.routeParams,false);P.pollNotifications().catch(()=>{});if(!window.PikolLive)setInterval(()=>{if(!document.hidden)P.pollNotifications().catch(()=>{});},30000);});
})();
