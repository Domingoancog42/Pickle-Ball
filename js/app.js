/* Pikol Castle — public court selection and server-backed account/guest checkout. */
(async () => {
  'use strict';

  if (!window.PikolIncludes || !await window.PikolIncludes.ready) return;
  const motion = window.PikolMotion;
  const $ = (selector, parent = document) => parent.querySelector(selector);
  const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
  const money = value => `₱${Number(value).toLocaleString('en-PH')}`;
  const pad = n => String(n).padStart(2, '0');
  const dateKey = date => `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
  const manilaNow = () => new Date(Date.now() + 8 * 60 * 60 * 1000);
  const today = dateKey(manilaNow());
  const lastDay = new Date(`${today}T00:00:00Z`);
  lastDay.setUTCDate(lastDay.getUTCDate() + 60);
  let lastDate = dateKey(lastDay);
  let advanceDays=60;
  let gridRevision=0;
  const dateLabel = key => new Intl.DateTimeFormat('en-GB', {weekday:'short',day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).format(new Date(`${key}T00:00:00Z`));
  const timeLabel = hour => `${hour % 12 || 12}:00 ${hour < 12 ? 'AM' : 'PM'}`;
  const hours = Array.from({length:16}, (_, i) => i + 6);
  const icons = {
    down: '<path d="m6 9 6 6 6-6"/>',
    billing: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    check: '<path d="m20 6-11 11-5-5"/>',
    x: '<path d="m18 6-12 12M6 6l12 12"/>',
    left: '<path d="m15 18-6-6 6-6"/>',
    right: '<path d="m9 18 6-6-6-6"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    calendar: '<path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    login: '<path d="m10 17 5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
    logout: '<path d="m16 17 5-5-5-5M21 12H9M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A12 12 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 4M6.5 6.5A18 18 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.5-1.5"/>'
  };
  const icon = (name, cls = 'w-4 h-4') => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${cls}" aria-hidden="true">${icons[name] || icons.info}</svg>`;
  let user = null;
  let authCsrf = '';
  let selectedDate = today;
  let viewMonth = new Date(`${today.slice(0,7)}-01T00:00:00Z`);
  let selection = [];
  let pendingSlot = null;
  let pendingPickerChoice=null;
  let previousFocus = null;
  let toastTimer;
  let resetEmail = '';
  let modalRevision = 0;
  let modalClosing = false;
  const main = $('body > div.min-h-screen');
  const modal = $('#modal-root');
  const loginMarkup = new Map($$('nav [data-auth-control]').map(el => [el, {html:el.innerHTML,className:el.className}]));

  function toast(title, text = '') {
    clearTimeout(toastTimer);
    $('#toast').innerHTML = `<strong>${escape(title)}</strong>${text ? `<span>${escape(text)}</span>` : ''}`;
    $('#toast').hidden = false;
    motion?.notice($('#toast'), true);
    toastTimer = setTimeout(() => {
      if (motion) motion.notice($('#toast'), false); else $('#toast').hidden = true;
    }, 4500);
  }

  function setUser(value) {user=value;updateAccount();renderGrid();document.dispatchEvent(new Event('pikol-auth-changed'));}

  function updateAccount() {
    closeAccountMenu();
    loginMarkup.forEach((original, button) => {
      button.innerHTML = user ? `<span class="home-profile-avatar">${escape(initials())}</span><span class="home-profile-name">${escape(user.name)}</span>${icon('down','home-profile-chevron')}` : original.html;
      button.className = user ? 'home-profile-trigger' : original.className;
      button.dataset.action = user ? 'account' : 'login';
      if(user)button.title=user.name;
      button.setAttribute('aria-label', user ? 'Account menu' : 'Log in');
      if (user) { button.setAttribute('aria-haspopup','menu');button.setAttribute('aria-expanded','false'); }
      else { button.removeAttribute('aria-haspopup');button.removeAttribute('aria-expanded'); }
    });
    renderMobileMenu();updateNotificationBells();
  }
  function initials() { return user.name.trim().split(/\s+/).map(part=>part[0]).slice(0,2).join('').toUpperCase(); }
  function renderMobileMenu() {
    const menu = $('#mobile-menu');
    menu.className = 'lg:hidden bg-background/95 backdrop-blur-md border-b border-border px-4 pt-2 pb-4 space-y-1';
    const links = $$('#site-header nav a[href^="#"]').slice(1).map(a=>`<a href="${a.getAttribute('href')}" class="block px-3 py-3 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-secondary/70 transition-colors">${escape(a.textContent)}</a>`).join('');
    const account = user ? `<div class="home-mobile-account"><div class="home-account-identity"><span class="home-profile-avatar">${escape(initials())}</span><div><strong>${escape(user.name)}</strong><small>${escape(user.email)}</small></div></div>${accountOptions(false)}</div>` : `<button type="button" data-action="login" class="btn btn-outline w-full mt-2 text-primary">${icon('login')}Log in</button>`;
    menu.innerHTML = `${links}${account}<button type="button" data-action="book" class="btn btn-primary w-full mt-2">${icon('calendar')}Book Now</button>`;
  }
  const customerPages={profile:['profile.php','My profile','user'],calendar:['calendar.php','My schedule','calendar'],reservations:['my-reservations.php','My reservations','calendar'],book:['book-court.php','Book a court','plus'],payments:['payments.php','Billing','billing'],notifications:['notifications.php','Notifications','bell']};
  function accountOptions(menu=true){
    const role=menu?' role="menuitem"':'';
    if(user.role==='admin')return `<button type="button"${role} data-action="dashboard">${icon('user')}Admin dashboard</button><hr><button type="button"${role} data-action="logout">${icon('logout')}Logout</button>`;
    return Object.entries(customerPages).filter(([key])=>key!=='notifications').map(([key,[file,label,symbol]])=>`${key==='payments'?'<hr>':''}<button type="button"${role} data-action="customer-page" data-customer-page="${key}">${icon(symbol)}${label}</button>`).join('')+`<button type="button"${role} data-action="rate-castle">${icon('check')}Rate Pikol Castle</button><hr><button type="button"${role} data-action="logout" class="home-account-logout">${icon('logout')}Logout</button>`;
  }
  function updateNotificationBells(){
    const enabled=user?.role==='customer';
    const make=(target,mobile=false)=>{if(!target||target.previousElementSibling?.matches('.home-notification-bell'))return;const bell=document.createElement('button');bell.type='button';bell.className='home-notification-bell'+(mobile?' home-bell-mobile':'');bell.dataset.action='customer-notifications';bell.setAttribute('aria-label','Notifications');bell.innerHTML=icon('bell')+'<span class="home-bell-count" hidden></span>';target.before(bell);};
    loginMarkup.forEach((original,button)=>{if(button.isConnected&&!button.closest('#mobile-menu'))make(button);});
    make($('#site-header [data-action="menu"]'),true);
    $$('.home-notification-bell').forEach(button=>{button.hidden=!enabled;if(!enabled)$('.home-bell-count',button).hidden=true;});
    if(enabled)refreshNotificationBells();else window.PikolNotifications?.close();
  }
  async function refreshNotificationBells(){
    if(user?.role!=='customer')return;const userId=user.id;
    try{const response=await fetch('api/customer/notifications.php',{credentials:'same-origin',headers:{Accept:'application/json'}});if(!response.ok)return;const data=await response.json();if(user?.id!==userId)return;const count=Math.max(0,Number(data.unread)||0);$$('.home-notification-bell').forEach(button=>{const badge=$('.home-bell-count',button);badge.textContent=count>99?'99+':String(count);badge.hidden=count===0;button.setAttribute('aria-label',count?'Notifications, '+count+' unread':'Notifications');});}catch{}
  }
  if(!window.PikolLive)setInterval(()=>{if(!document.hidden)refreshNotificationBells();},30000);
  function closeCustomerPanel(){document.body.classList.remove('account-page-open');const panel=$('#homepage-account');if(panel){panel.remove();$('[data-action="account"]')?.focus({preventScroll:true});}}
  function openCustomerPanel(page='profile.php',query={}){
    let panel=$('#homepage-account');
    if(!panel){panel=document.createElement('section');panel.id='homepage-account';panel.className='homepage-account';panel.setAttribute('aria-label','Your player account');panel.innerHTML=`<div class="homepage-account-heading"><div><span>PLAYER ACCOUNT</span><h2 id="homepage-account-title">Your account</h2></div></div><iframe id="homepage-account-frame" title="Your player account" referrerpolicy="same-origin"></iframe>`;$('main').prepend(panel);}
    const entry=Object.values(customerPages).find(item=>item[0]===page);$('#homepage-account-title').textContent=entry?.[1]||'Your account';
    const frame=$('#homepage-account-frame');if(!frame.dataset.authGuard){frame.dataset.authGuard='true';frame.addEventListener('load',()=>{try{const url=new URL(frame.contentWindow.location.href);if(url.origin===location.origin&&url.pathname.endsWith('/index.html')&&url.hash==='#login'){closeCustomerPanel();setUser(null);showAuth('login');}}catch{}});}frame.title=entry?.[1]||'Your player account';frame.src='customer/'+page+'?'+new URLSearchParams({...query,embedded:1});
    document.body.classList.add('account-page-open');closeAccountMenu();closeMenu();panel.scrollIntoView({behavior:'instant',block:'start'});
  }
  window.addEventListener('message',async event=>{
    const frame=$('#homepage-account-frame');if(event.origin!==location.origin||event.source!==frame?.contentWindow)return;
    if(event.data?.type==='pikol-account-logout'){closeCustomerPanel();setUser(null);toast('Logged out','See you on the court soon!');}
    if(event.data?.type==='pikol-account-expired'){closeCustomerPanel();setUser(null);showAuth('login');}
    if(event.data?.type==='pikol-notifications-updated')refreshNotificationBells();
    if(event.data?.type==='pikol-open-slot-picker'&&user?.role==='customer')window.PikolSlotPicker.open();
    if(event.data?.type==='pikol-open-payment'&&user?.role==='customer'){
      openSlotCheckout({payment_booking:String(event.data.bookingId),...(event.data.paymentId?{payment:String(event.data.paymentId)}:{})});
    }
    if(event.data?.type==='pikol-account-updated'){try{const result=await authRequest();if(result.user)setUser({id:result.user.user_id,name:result.user.full_name,email:result.user.email,role:result.user.role});}catch{}}
  });
  function closeAccountMenu() {
    $('#account-menu')?.remove();
    const trigger = $('[data-action="account"]');
    if(trigger)trigger.setAttribute('aria-expanded','false');
  }
  function toggleAccountMenu(button) {
    if($('#account-menu'))return closeAccountMenu();
    const menu=document.createElement('div');menu.id='account-menu';menu.className='account-menu';menu.setAttribute('role','menu');
    const rect=button.getBoundingClientRect();menu.style.top=`${rect.bottom+5}px`;menu.style.right=`${innerWidth-rect.right}px`;
    menu.innerHTML=`<div class="home-account-identity"><span class="home-profile-avatar">${escape(initials())}</span><div><strong>${escape(user.name)}</strong><small>${escape(user.email)}</small></div></div>${accountOptions(true)}`;
    document.body.append(menu);button.setAttribute('aria-expanded','true');$('button',menu).focus();
    motion?.menu(menu);
  }

  function renderCalendar() {
    if(!$('#booking-calendar'))return;
    const y = viewMonth.getUTCFullYear(), m = viewMonth.getUTCMonth();
    const label = new Intl.DateTimeFormat('en-US', {month:'long',year:'numeric',timeZone:'UTC'}).format(viewMonth);
    const offset = (viewMonth.getUTCDay() + 6) % 7;
    const cells = Array.from({length:42}, (_, i) => {
      const date = new Date(Date.UTC(y, m, 1 - offset + i));
      const key = dateKey(date), day = date.getUTCDate();
      if (date.getUTCMonth() !== m) return `<div aria-hidden="true" class="h-9 flex items-center justify-center text-sm text-muted-foreground/30 select-none">${day}</div>`;
      const disabled = key < today || key > lastDate;
      const selected = key === selectedDate;
      const cls = selected ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/30 scale-110' : key === today ? 'text-primary ring-1 ring-primary/70 hover:bg-primary/15' : 'text-foreground/90 hover:bg-accent';
      return `<div role="gridcell" class="flex justify-center"><button type="button" data-action="date" data-date="${key}" aria-pressed="${selected}" aria-label="${selected ? 'Selected' : 'Choose'} date ${key}" ${disabled ? 'disabled' : ''} class="h-9 w-9 rounded-full text-sm font-semibold transition-all duration-150 ${cls} ${disabled ? 'opacity-25 cursor-not-allowed pointer-events-none' : ''}">${day}</button></div>`;
    }).join('');
    const navClass = 'h-9 w-9 shrink-0 rounded-full border border-border flex items-center justify-center text-muted-foreground hover:text-primary hover:border-primary/60 transition-colors disabled:opacity-30 disabled:pointer-events-none';
    $('#booking-calendar').innerHTML = `<div class="flex items-center justify-between mb-4"><button type="button" data-action="month" data-offset="-1" aria-label="Previous month" class="${navClass}" ${dateKey(viewMonth).slice(0,7) <= today.slice(0,7) ? 'disabled' : ''}>${icon('left')}</button><div class="text-center font-extrabold tracking-tight select-none" aria-live="polite">${label}</div><button type="button" data-action="month" data-offset="1" aria-label="Next month" class="${navClass}" ${dateKey(viewMonth).slice(0,7) >= lastDate.slice(0,7) ? 'disabled' : ''}>${icon('right')}</button></div><div class="grid grid-cols-7 mb-1">${['Mo','Tu','We','Th','Fr','Sa','Su'].map(day=>`<div class="h-8 flex items-center justify-center text-[11px] font-bold uppercase tracking-wider text-muted-foreground">${day}</div>`).join('')}</div><div role="grid" aria-label="Choose booking date" class="grid grid-cols-7 gap-y-1">${cells}</div><div class="mt-3 pt-3 border-t border-border/60 flex items-center gap-1.5 text-[11px] text-muted-foreground">${icon('info','w-3.5 h-3.5 text-primary/70 shrink-0')}Bookings open up to ${advanceDays} days ahead.</div>`;
    $('#selected-date').textContent = dateLabel(selectedDate);
  }

  let playerDestination=null;
  function openPlayer(page='book-court.php',query={}) {
    if(page==='book-court.php'&&!query.reschedule){closeMenu();closeAccountMenu();window.PikolSlotPicker.open();return;}
    if(!user){playerDestination='customer/'+page+(Object.keys(query).length?'?'+new URLSearchParams(query):'');showAuth('login');return;}
    if(user.role==='admin'){location.assign('admin/index.php');return;}
    openCustomerPanel(page,query);
  }
  async function renderGrid() {
    if(!$('#court-grid'))return;
    const revision=++gridRevision,box=$('#court-grid');$('#selection-summary').hidden=true;
    box.innerHTML='<p class="p-6 text-sm text-muted-foreground">Checking live court availability...</p>';
    try{
      const response=await fetch('api/availability.php?date='+encodeURIComponent(selectedDate),{cache:'no-store'});const data=await response.json();if(!response.ok)throw Error(data.error);
      if(revision!==gridRevision)return;
      advanceDays=Number(data.policies.advance_days);lastDay.setTime(new Date(today+'T00:00:00Z').getTime());lastDay.setUTCDate(lastDay.getUTCDate()+advanceDays);lastDate=dateKey(lastDay);renderCalendar();
      const starts=[...new Set(data.courts.flatMap(c=>c.slots.map(s=>s.start_time)))].sort();
      if(!starts.length){box.innerHTML='<div class="p-6 text-muted-foreground">No court times are available on this date. Please choose another day.</div>';return;}
      const columns='190px repeat('+starts.length+',minmax(70px,1fr))';
      const display=time=>{const [h,m]=time.split(':');return (Number(h)%12||12)+':'+m+' '+(Number(h)<12?'AM':'PM');};
      box.innerHTML='<div style="min-width:900px"><div class="grid border-b border-border/70 bg-card/80" style="grid-template-columns:'+columns+'"><div class="px-4 py-3 font-semibold">Court</div>'+starts.map(time=>'<div class="px-1 py-3 text-center text-xs text-muted-foreground">'+display(time)+'</div>').join('')+'</div>'+data.courts.map(c=>'<div class="grid border-b border-border/50" style="grid-template-columns:'+columns+'"><div class="px-4 py-3"><strong class="text-sm">'+escape(c.court_name.split('\u2014')[0].trim())+'</strong><div class="text-xs text-muted-foreground">'+escape(c.type)+' / '+money(c.hourly_rate)+'/hr</div></div>'+starts.map(time=>{const slot=c.slots.find(s=>s.start_time===time),available=slot?.state==='Available';return '<button type="button" class="m-1 rounded-lg border min-h-[56px] text-xs '+(available?'border-primary/25 bg-primary/5 text-primary hover:bg-primary/15':'border-border/50 bg-muted/40 text-muted-foreground')+'" data-action="live-slot" data-court="'+c.court_id+'" data-start="'+time+'" '+(!available?'disabled':'')+' aria-label="'+escape(c.court_name+' at '+display(time))+ '">'+(available?icon('plus'):escape(slot?.state||'Closed'))+'</button>';}).join('')+'</div>').join('')+'</div>';
    }catch(error){if(revision===gridRevision)box.innerHTML='<div class="p-6"><p class="text-sm text-muted-foreground">Live availability needs XAMPP Apache and MySQL. Open the player booking page to check court times.</p><button type="button" class="btn btn-primary mt-4" data-action="book">Book a court</button></div>';}
  }
  function selectSlot(){window.PikolSlotPicker.open();}
  function openSlotCheckout(choice){location.assign('booking.html?'+new URLSearchParams(choice));}

  function openModal(content) {
    const revision = ++modalRevision;
    modalClosing = false;
    delete modal.dataset.closing;
    motion?.cancelTree(modal);
    if (modal.hidden) previousFocus = document.activeElement;
    window.PikolTurnstile?.unmount(modal);
    modal.replaceChildren();
    if (typeof content === 'string') modal.innerHTML = content; else modal.append(content);
    modal.classList.toggle('auth-modal', Boolean($('.auth-dialog',modal)));
    modal.hidden = false;
    modal.scrollTop = 0;
    main.inert = true;
    document.body.style.overflow = 'hidden';
    $('[role="dialog"]',modal).setAttribute('aria-modal','true');
    motion?.modalIn(modal);
    requestAnimationFrame(() => {
      if (revision === modalRevision && !modalClosing) ($('input:not([aria-hidden]), button, [tabindex]',modal) || $('[role="dialog"]',modal))?.focus({preventScroll:true});
    });
  }
  function closeModal() {
    if (modal.hidden || modalClosing) return;
    window.PikolTurnstile?.unmount(modal);
    window.PikolSlotPicker?.cancel();pendingPickerChoice=null;
    const revision = ++modalRevision;
    modalClosing = true;
    modal.dataset.closing = 'true';
    const finish = () => {
      if (revision !== modalRevision) return;
      modal.hidden = true;
      modal.replaceChildren();
      modal.classList.remove('auth-modal');
      modalClosing = false;
      delete modal.dataset.closing;
      main.inert = false;
      document.body.style.removeProperty('overflow');
      previousFocus?.focus({preventScroll:true});
    };
    if (motion) motion.modalOut(modal).then(finish); else finish();
  }
  function messageDialog(title, body, actions = '') {
    openModal(`<div role="dialog" aria-labelledby="message-title" tabindex="-1" class="fixed top-[50%] left-[50%] z-50 grid translate-x-[-50%] translate-y-[-50%] w-[calc(100%-2rem)] max-w-lg gap-4 rounded-xl border bg-card p-6 shadow-lg"><button type="button" data-action="close" aria-label="Close" class="absolute top-4 right-4 text-muted-foreground hover:text-foreground">${icon('x')}</button><h2 id="message-title" class="text-xl font-extrabold pr-6">${escape(title)}</h2>${body}${actions}</div>`);
  }
  function formError(form, text) {
    let box = $('.form-error',form);
    if (!box) { box = document.createElement('p'); box.className = 'form-error'; box.setAttribute('role','alert'); form.append(box); }
    box.textContent = text;
    motion?.error(box);
  }


  function showAuth(view) {
    const content = $(`#template-${view}`).content.cloneNode(true);
    content.querySelectorAll('button').forEach(button => {
      const text = button.textContent.trim();
      if (button.getAttribute('aria-label') === 'Close') button.dataset.action = 'close';
      if (/^Sign Up$/i.test(text) && button.type !== 'submit') { button.dataset.action = 'auth-view'; button.dataset.view = 'signup'; }
      if (/^Log In$/i.test(text) && button.type !== 'submit') { button.dataset.action = 'auth-view'; button.dataset.view = 'login'; }
      if (/Forgot password/i.test(text)) { button.dataset.action = 'auth-view'; button.dataset.view = 'forgot'; }
      if (/Back to log in/i.test(text)) { button.dataset.action = 'auth-view'; button.dataset.view = 'login'; }
      if (/password/.test(button.getAttribute('aria-label') || '')) button.dataset.action = 'password';
      if (button.getAttribute('role') === 'checkbox') button.dataset.action = 'remember';
    });
    const form = $('form',content);
    form.dataset.auth = view;
    form.removeAttribute('novalidate');
    if (view === 'forgot') $('#forgot-email',content).value = resetEmail;
    openModal(content);
    if(view==='login')window.PikolTurnstile.mount(form,async()=>(await authRequest()).turnstile.site_key);
    if(view==='forgot')window.PikolReset.mount($('#reset-password-form',modal),{request:resetRequest,done:email=>{showAuth('login');$('#login-email',modal).value=email;toast('Password changed','Log in with your new password.');}});
  }

  async function authRequest(action, data = {}) {
    const response = await fetch('api/auth.php', action ? {
      method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':authCsrf},body:JSON.stringify({action,...data})
    } : {credentials:'same-origin',cache:'no-store'});
    let result;
    try { result=await response.json(); } catch { throw new Error('Open this website through XAMPP Apache to sign in.'); }
    if (!response.ok) throw new Error(result.error || 'Authentication failed.');
    if (result.csrf) authCsrf=result.csrf;
    return result;
  }

  async function resetRequest(action,data={}) {
    await authRequest();
    const response=await fetch('api/password-reset.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':authCsrf},body:JSON.stringify({action,...data})});
    const result=await response.json();if(result.csrf)authCsrf=result.csrf;
    if(!response.ok)throw Error(result.error||'Password reset failed.');return result;
  }

  const authAlert=options=>Swal.fire({background:'#111d1d',color:'#e3eeec',confirmButtonColor:'#0e9f92',heightAuto:false,customClass:{container:'auth-alert-container',popup:'auth-alert-popup'},...(matchMedia('(prefers-reduced-motion: reduce)').matches?{showClass:{popup:''},hideClass:{popup:''}}:{}),...options});

  async function authenticate(form) {
    const button=$('button[type="submit"]',form), view=form.dataset.auth;
    if(button.disabled)return;
    button.disabled=true;
    if(view==='login')window.PikolTurnstile.setSubmitting(form,true);
    try {
      if(view==='forgot') return await window.PikolReset.submit(form);
      const turnstileToken=view==='login'?window.PikolTurnstile.token(form):undefined;
      const email=$('input[type="email"]',form).value.trim();
      authAlert({title:view==='signup'?'Creating your account':'Signing in',text:view==='signup'?'Sending your temporary password by email...':'Checking your account...',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()});
      await authRequest();
      const result=await authRequest(view==='signup'?'signup':'login',{
        email:$('input[type="email"]',form).value.trim(),
        password:view==='signup'?undefined:$('input[type="password"],input[autocomplete$="password"]',form).value,
        name:view==='signup'?$('#signup-name',form).value.trim():undefined,
        remember:$('[name="remember"]',form)?.checked ?? true,
        'cf-turnstile-response':turnstileToken
      });
      if(view==='signup'){await authAlert({icon:'success',title:'Check your email',text:result.message,confirmButtonText:'Continue to login'});showAuth('login');$('#login-email',modal).value=email;return;}
      if(result.user?.role==='customer'&&!result.requires_password_change){
        const resumePicker=pendingPickerChoice;playerDestination=null;pendingSlot=null;
        setUser({id:result.user.user_id,name:result.user.full_name,email:result.user.email,role:result.user.role});
        await authAlert({icon:'success',title:'Welcome back!',text:'You are signed in. Open your profile menu to visit your dashboard.',timer:1400,timerProgressBar:true,confirmButtonText:'Continue'});
        closeModal();closeMenu();if(resumePicker)openSlotCheckout(resumePicker);return;
      }
      if(result.redirect){await authAlert({icon:'success',title:result.requires_password_change?'Temporary password verified':'Welcome back!',text:result.requires_password_change?'Choose your new password to finish setting up your account.':'You are signed in. Opening your dashboard...',confirmButtonText:'Continue',timer:result.requires_password_change?undefined:1400,timerProgressBar:!result.requires_password_change});location.assign(result.redirect);return;}
      setUser({id:result.user.user_id,name:result.user.full_name,email:result.user.email,role:result.user.role},false);
      closeModal();toast(view==='signup'?'Welcome to the castle!':'Welcome back!',"You're ready to book your court.");
      if(pendingSlot){const pending=pendingSlot;pendingSlot=null;selectSlot(pending.courtIndex,pending.hour);}
    } catch(error) { if(view==='login')window.PikolTurnstile.reset(form);formError(form,error.message);if(view!=='forgot')await authAlert({icon:'error',title:view==='signup'?'Could not create account':'Login Failed',text:error.message,confirmButtonText:'Try again'}); }
    finally { if(view==='login')window.PikolTurnstile.setSubmitting(form,false);else button.disabled=false; }
  }

  function checkout(){openPlayer();}
  function confirmBooking(){openPlayer();}
  function scrollToSection(id) {
    if(document.body.classList.contains('account-page-open'))closeCustomerPanel();
    document.getElementById(id)?.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block:'start'});
  }
  function viewBookings(){openPlayer('my-reservations.php');}
  function closeMenu() { if (motion) motion.panel($('#mobile-menu'),false); else $('#mobile-menu').hidden = true; const b = $('[data-action="menu"]'); b.setAttribute('aria-expanded','false'); b.setAttribute('aria-label','Open menu'); b.innerHTML=icon('menu','w-6 h-6'); }

  document.addEventListener('click', async event => {
    if(!event.target.closest('#account-menu, [data-action="account"]'))closeAccountMenu();
    const link = event.target.closest('a[href^="#"]');
    if (link) {closeCustomerPanel();closeMenu();}
    const button = event.target.closest('[data-action]');
    if (!button || button.disabled) return;
    const action = button.dataset.action;
    switch (action) {
      case 'menu': { const open = button.getAttribute('aria-expanded') !== 'true'; if (motion) motion.panel($('#mobile-menu'),open); else $('#mobile-menu').hidden = !open; button.setAttribute('aria-expanded',String(open)); button.setAttribute('aria-label',open ? 'Close menu' : 'Open menu'); button.innerHTML=icon(open?'x':'menu','w-6 h-6'); break; }
      case 'book': closeMenu(); window.PikolSlotPicker.open(); break;
      case 'courts': scrollToSection('courts'); break;
      case 'court': closeMenu();closeAccountMenu();window.PikolSlotPicker.open();break;
      case 'live-slot': openPlayer('book-court.php',{court:button.dataset.court,date:selectedDate,start:button.dataset.start}); break;
      case 'date': selectedDate=button.dataset.date; selection=[]; renderCalendar(); renderGrid(); motion?.calendar(); motion?.grid(); break;
      case 'month': viewMonth.setUTCMonth(viewMonth.getUTCMonth()+Number(button.dataset.offset)); renderCalendar(); motion?.calendar(Number(button.dataset.offset)); break;
      case 'slot': selectSlot(Number(button.dataset.court),Number(button.dataset.hour)); break;
      case 'remove-slot': selection=selection.filter(s=>s.courtIndex !== Number(button.dataset.court) || s.hour !== Number(button.dataset.hour)); renderGrid(); break;
      case 'clear': selection=[]; renderGrid(); break;
      case 'checkout': checkout(); break;
      case 'confirm-booking': confirmBooking(); break;
      case 'login': pendingPickerChoice=null;pendingSlot=null; closeMenu(); showAuth('login'); break;
      case 'auth-view': showAuth(button.dataset.view); break;
      case 'close': closeModal(); break;
      case 'password': { const input=$('input',button.parentElement); const show=input.type === 'password'; input.type=show ? 'text' : 'password'; button.setAttribute('aria-label',show ? 'Hide password' : 'Show password'); button.setAttribute('aria-pressed',String(show)); button.innerHTML=icon(show?'eyeOff':'eye'); break; }
      case 'remember': { const checked=button.getAttribute('aria-checked') !== 'true'; button.setAttribute('aria-checked',String(checked)); button.dataset.state=checked?'checked':'unchecked'; $('span',button).hidden=!checked; break; }
      case 'faq': {
        const open=button.getAttribute('aria-expanded') !== 'true';
        $$('[data-action="faq"]').forEach(trigger=>{
          const active=trigger===button&&open, panel=$(`#faq-answer-${trigger.dataset.faq}`);
          trigger.setAttribute('aria-expanded',String(active)); trigger.dataset.state=active?'open':'closed';
          trigger.closest('[data-slot="accordion-item"]').dataset.state=active?'open':'closed';
          panel.dataset.state=active?'open':'closed';
          if (motion) motion.panel(panel,active);
          else panel.hidden=!active;
        }); break;
      }
      case 'account': toggleAccountMenu(button); break;
      case 'logout': {
        if(Swal.isVisible())break;
        const result=await authAlert({
          title:'Log out?',text:'Are you sure you want to log out of your account?',icon:'question',
          showCancelButton:true,confirmButtonText:'Log out',cancelButtonText:'Stay signed in',focusCancel:true,
          showLoaderOnConfirm:true,allowOutsideClick:()=>!Swal.isLoading(),allowEscapeKey:()=>!Swal.isLoading(),
          preConfirm:async()=>{
            try { await authRequest(); await authRequest('logout'); return true; }
            catch(error) { Swal.showValidationMessage(error.message||'Could not log out. Please try again.'); return false; }
          }
        });
        if(!result.isConfirmed)break;
        closeCustomerPanel(); setUser(null); pendingSlot=null; selection=[]; renderGrid(); closeModal(); closeMenu();
        toast('Logged out','See you on the court soon!'); break;
      }
      case 'customer-notifications': if(user?.role==='customer'){window.PikolNotifications?.toggle(button);refreshNotificationBells();}break;
      case 'rate-castle':
        closeAccountMenu();
        if(user?.role==='customer'){try{await authRequest();window.PikolRatings.open(authCsrf);}catch(error){showMessage(error.message);}}
        break;
      case 'customer-page':
        if(user?.role==='customer'&&customerPages[button.dataset.customerPage]){
          if(button.dataset.customerPage==='book'){closeAccountMenu();closeMenu();window.PikolSlotPicker.open();}
          else openPlayer(customerPages[button.dataset.customerPage][0]);
        }
        break;
      case 'close-customer-page': closeCustomerPanel();scrollToSection('home');break;
      case 'dashboard': location.assign(user?.role==='admin'?'admin/index.php':'customer/index.php'); break;
      case 'view-bookings': viewBookings(); break;
      case 'cancel-booking': case 'confirm-cancel': openPlayer('my-reservations.php'); break;
    }
  });
  document.addEventListener('submit', async event => {
    const form=event.target;
    event.preventDefault();
    if(form.dataset.auth)return authenticate(form);

  });
  modal.addEventListener('click',event=>{if(event.target===modal)closeModal();});
  document.addEventListener('keydown',event=>{
    if(window.Swal?.isVisible())return;
    if(event.key==='Escape'){if(!modal.hidden)closeModal();if($('#account-menu')){closeAccountMenu();$('[data-action="account"]')?.focus();}closeMenu();}
    if(event.target.closest('#account-menu')&&['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
      event.preventDefault();const items=$$('#account-menu button'),index=items.indexOf(document.activeElement);
      items[event.key==='Home'?0:event.key==='End'?items.length-1:(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length]?.focus();
    }
    if(event.key==='Tab'&&!modal.hidden){
      const focusable=$$('button:not([disabled]),a[href],input:not([type="hidden"]):not([aria-hidden]),select,textarea,iframe,[tabindex="0"]',modal).filter(e=>e.getClientRects().length&&e.tabIndex>=0);
      const first=focusable[0],last=focusable.at(-1);
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
      if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
    }
  });
  window.PikolNotifications?.configure({getUser:()=>user,getCsrf:async()=>{await authRequest();return authCsrf;},onChange:refreshNotificationBells,closeProfile:closeAccountMenu});
  const updateHeader=()=>$('#site-header').classList.toggle('is-scrolled',scrollY>24);
  window.addEventListener('scroll',updateHeader,{passive:true});
  try { const session=await authRequest(); if(session.user) user={id:session.user.user_id,name:session.user.full_name,email:session.user.email,role:session.user.role}; } catch { /* The public page can still be viewed without PHP. */ }
  await window.PikolCourtCatalog.refresh();
  updateHeader();updateAccount();renderCalendar();renderGrid();
  window.PikolIncludes.finish();
  motion?.init();
  const requestedPicker=new URL(location.href);
  if(requestedPicker.searchParams.has('pay-booking')){
    const choice={payment_booking:requestedPicker.searchParams.get('pay-booking'),...(requestedPicker.searchParams.has('payment')?{payment:requestedPicker.searchParams.get('payment')}:{})};
    requestedPicker.searchParams.delete('pay-booking');requestedPicker.searchParams.delete('payment');
    history.replaceState(history.state,'',requestedPicker.pathname+requestedPicker.search+requestedPicker.hash);
    if(user?.role==='customer')openSlotCheckout(choice);
    else if(!user){pendingPickerChoice=choice;showAuth('login');}
  }
  if(requestedPicker.searchParams.get('book-slots')==='1'){
    requestedPicker.searchParams.delete('book-slots');
    history.replaceState(history.state,'',requestedPicker.pathname+requestedPicker.search+requestedPicker.hash);
    window.PikolSlotPicker.open();
  }
  async function refreshBusinessHours(){
    try{
      const response=await fetch('api/business-hours.php',{cache:'no-store'});if(!response.ok)return;
      const {hours}=await response.json();
      for(const [key,value] of Object.entries(hours)){
        const target=$(`[data-business-hours="${key}"]`);if(!target)continue;
        target.textContent=value.label;target.title=value.overnight?'Closes the next day':'';
      }
      const duration=$('[data-business-duration]');if(duration)duration.textContent=hours.daily.duration_hours+'h';
    }catch{}
  }
  void refreshBusinessHours();
  const refreshPublicCourts=()=>{if(!document.hidden){void refreshBusinessHours();if(modal.hidden){window.PikolCourtCatalog.refresh();renderGrid();}}};
  if(!window.PikolLive)setInterval(refreshPublicCourts,30000);
  document.addEventListener('pikol:live-update',event=>{
    const changes=event.detail.changes;
    if(changes.some(key=>['courts','settings','reservations','payments'].includes(key))){refreshPublicCourts();window.PikolSlotPicker?.refresh();}
    if(changes.some(key=>['notifications','reservations'].includes(key)))refreshNotificationBells();
  });
  window.addEventListener('focus',refreshPublicCourts);
  document.addEventListener('visibilitychange',refreshPublicCourts);
  if(location.hash==='#login') {
    // A redirect may request login once; do not keep reopening it on refresh.
    history.replaceState(history.state,'',location.pathname+location.search);
    const navigation=performance.getEntriesByType('navigation')[0];
    if(!user&&navigation?.type!=='reload') showAuth('login');
  }
})().catch(error => window.PikolIncludes?.fail(error));
