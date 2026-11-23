/* Homepage notification popover and full-list floating card. */
(() => {
  let config,trigger,view,version=0,previousFocus;
  const e=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const svg=name=>`<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${({check:'<path d="m5 12 4 4L19 6"/>',bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',close:'<path d="m6 6 12 12M18 6 6 18"/>',refresh:'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 8a7 7 0 0 1 12-2l2 3M4 15l2 3a7 7 0 0 0 12-2"/>'})[name]}</svg>`;
  const root=()=>document.querySelector('#home-notification-popover,#home-notification-dialog');
  function close(returnFocus=false){
    version++;document.querySelector('#home-notification-popover')?.remove();document.querySelector('#home-notification-overlay')?.remove();
    if(view?.kind==='all'){const shell=document.querySelector('#page-shell');if(shell)shell.inert=false;document.body.classList.remove('notification-card-open');}
    trigger?.setAttribute('aria-expanded','false');view=null;
    if(returnFocus&&previousFocus?.isConnected)previousFocus.focus({preventScroll:true});
  }
  function date(value){const d=new Date(String(value).replace(' ','T')+'+08:00');return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('en-PH',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'}).format(d);}
  function render(){
    const target=root();if(!target||!view)return;
    const focused=target.contains(document.activeElement)?{action:document.activeElement.dataset.noticeAction,id:document.activeElement.dataset.notificationId}:null;
    const all=view.kind==='all';
    target.innerHTML=`<header class="notice-heading"><div><h2 id="notice-title">${all?'All notifications':'Notifications'}</h2><p>${view.unread||0} unread notification${view.unread===1?'':'s'}</p></div><div class="notice-header-actions"><button type="button" class="notice-mark-all" data-notice-action="read-all" ${!view.unread||view.busy?'disabled':''}>${svg('check')}Mark all as read</button>${all?`<button type="button" class="notice-icon-button" data-notice-action="close" aria-label="Close notifications">${svg('close')}</button>`:''}</div></header><div class="notice-list" aria-busy="${Boolean(view.loading)}">${view.loading&&!view.items.length?'<p class="notice-state" role="status">Loading your notifications...</p>':view.error?`<div class="notice-state" role="alert"><p>${e(view.error)}</p><button type="button" data-notice-action="refresh">Try again</button></div>`:view.items.length?view.items.map(n=>`<button type="button" class="notice-row ${n.read_at?'is-read':'is-unread'}" data-notice-action="read" data-notification-id="${n.notification_id}" ${view.busy?'disabled':''} aria-label="${n.read_at?'Read notification: ':'Mark as read: '}${e(n.title)}"><span class="notice-dot" aria-hidden="true"></span><span class="notice-content"><span class="notice-meta"><span class="notice-category">${e(n.title)}</span><span class="notice-read-status">${n.read_at?'Read':'Unread'}</span><time>${e(date(n.created_at))}</time></span><strong>${e(n.title)}</strong><span class="notice-message">${e(n.message)}</span></span></button>`).join(''):'<div class="notice-state">'+svg('bell')+'<h3>You’re all caught up</h3><p>Reminders for your upcoming confirmed bookings will appear here.</p></div>'}</div><footer class="notice-footer">${all?`<span>Showing ${view.items.length} of ${view.total||0} notifications</span><div><button type="button" class="notice-icon-button" data-notice-action="refresh" aria-label="Refresh notifications" ${view.loading?'disabled':''}>${svg('refresh')}</button>${view.page<view.pages?`<button type="button" class="notice-more" data-notice-action="more" ${view.loading?'disabled':''}>${view.loading?'Loading...':'Load more'}</button>`:''}</div>`:`<button type="button" class="notice-view-all" data-notice-action="all">${svg('bell')}View all notifications</button>`}</footer>`;
    if(focused?.action){const match=[...target.querySelectorAll('[data-notice-action]')].find(button=>button.dataset.noticeAction===focused.action&&button.dataset.notificationId===focused.id&&!button.disabled);match?.focus({preventScroll:true});}
    if(!all)position();
  }
  function position(){
    const target=document.querySelector('#home-notification-popover');if(!target||!trigger)return;
    const r=trigger.getBoundingClientRect(),width=Math.min(390,innerWidth-24);
    target.style.width=width+'px';target.style.right=Math.max(12,Math.min(innerWidth-r.right,innerWidth-width-12))+'px';
    target.style.top=Math.max(12,Math.min(r.bottom+10,innerHeight-200))+'px';target.style.maxHeight=Math.max(160,innerHeight-r.bottom-24)+'px';
  }
  async function load(reset=true){
    if(!view||!config?.getUser())return;const state=view,id=++version,userId=config.getUser().id;
    state.loading=true;state.error='';render();
    try{
      const page=reset?1:state.page+1;
      const response=await fetch('api/customer/notifications.php?'+new URLSearchParams({page,per_page:state.kind==='all'?25:5}),{credentials:'same-origin',headers:{Accept:'application/json'}});
      const data=await response.json();if(!response.ok)throw Error(data.error||'Notifications could not be loaded.');
      if(version!==id||view!==state||config.getUser()?.id!==userId)return;
      state.items=reset?data.items:[...new Map([...state.items,...data.items].map(item=>[item.notification_id,item])).values()];
      Object.assign(state,{page:data.page,pages:data.pages,total:data.total,unread:data.unread});
    }catch(error){if(version===id&&view===state)state.error=error.message;}
    finally{if(version===id&&view===state){state.loading=false;render();}}
  }
  function newView(kind){return {kind,items:[],unread:0,total:0,page:0,pages:1,loading:true,busy:false,error:''};}
  function openAll(){
    close();view=newView('all');
    const overlay=document.createElement('div');overlay.id='home-notification-overlay';overlay.className='notice-overlay';overlay.innerHTML='<section id="home-notification-dialog" class="notice-card" role="dialog" aria-modal="true" aria-labelledby="notice-title" tabindex="-1"></section>';
    document.body.append(overlay);document.querySelector('#page-shell').inert=true;document.body.classList.add('notification-card-open');render();document.querySelector('#home-notification-dialog').focus({preventScroll:true});load();
  }
  async function markRead(id){
    if(!view||view.busy)return;const state=view;state.busy=true;render();
    try{
      const csrf=await config.getCsrf();
      const response=await fetch('api/customer/notifications.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(id?{notification_id:id}:{action:'read-all'})});
      const data=await response.json();if(!response.ok)throw Error(data.error||'The notification could not be updated.');
      if(view!==state)return;
      const now=new Date().toISOString();state.items.forEach(item=>{if(!id||String(item.notification_id)===id)item.read_at=now;});
      state.unread=id?Math.max(0,state.unread-1):0;await config.onChange();
    }catch(error){if(view===state)state.error=error.message;}
    finally{if(view===state){state.busy=false;render();}}
  }
  window.PikolNotifications={configure(options){config=options;},close,toggle(button){
    if(view){close(true);return;}if(!config?.getUser())return;
    trigger=button;previousFocus=button;config.closeProfile();button.setAttribute('aria-expanded','true');button.setAttribute('aria-haspopup','dialog');view=newView('preview');
    const panel=document.createElement('section');panel.id='home-notification-popover';panel.className='notice-popover';panel.setAttribute('role','dialog');panel.setAttribute('aria-label','Notifications');document.body.append(panel);render();panel.querySelector('button:not([disabled])')?.focus({preventScroll:true});load();
  }};
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-notice-action]');
    if(button&&!button.disabled){const action=button.dataset.noticeAction;if(action==='all')openAll();if(action==='close')close(true);if(action==='refresh')load();if(action==='more')load(false);if(action==='read-all')markRead();if(action==='read'){const item=view?.items.find(n=>String(n.notification_id)===button.dataset.notificationId);if(item&&!item.read_at)markRead(button.dataset.notificationId);}return;}
    if(event.target.id==='home-notification-overlay')close(true);
    else if(view?.kind==='preview'&&!event.target.closest('#home-notification-popover,.home-notification-bell'))close();
  });
  document.addEventListener('keydown',event=>{
    if(!view)return;
    if(event.key==='Escape'){event.preventDefault();event.stopImmediatePropagation();close(true);return;}
    if(event.key==='Tab'&&view.kind==='all'){
      const items=[...root().querySelectorAll('button:not([disabled])')],first=items[0],last=items.at(-1);
      if(event.shiftKey&&(document.activeElement===first||document.activeElement===root())){event.preventDefault();last?.focus();}
      else if(!event.shiftKey&&(document.activeElement===last||document.activeElement===root())){event.preventDefault();first?.focus();}
    }
  },true);
  document.addEventListener('pikol:live-update',event=>{if(view&&!view.busy&&event.detail.changes.some(key=>['notifications','reservations'].includes(key)))load();});
  addEventListener('resize',position);addEventListener('scroll',position,{passive:true});
})();
