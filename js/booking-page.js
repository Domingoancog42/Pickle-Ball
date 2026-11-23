/* Dedicated public booking page: one normal document for slots, review and payment. */
(() => {
  'use strict';
  const content=document.querySelector('#booking-page-content'),title=document.querySelector('#booking-page-title');
  const layout=document.querySelector('.booking-page-layout'),panels=document.createElement('div');
  panels.className='booking-tab-panels';
  const tabSections=[['book','Book','.booking-page-workspace'],['about','About','.venue-about'],['info','Booking info','.venue-booking-info'],['addons','Add-ons','[aria-labelledby="equipment-title"]'],['food','Food & drinks','[aria-labelledby="food-title"]']];
  const tablist=document.createElement('div');tablist.className='booking-section-tabs';tablist.setAttribute('role','tablist');tablist.setAttribute('aria-label','Venue and booking');
  layout.append(panels);panels.append(tablist);
  for(const [id,label,selector] of tabSections){
    const section=document.querySelector(selector),button=document.createElement('button');
    button.type='button';button.id='venue-tab-'+id;button.textContent=label;button.setAttribute('role','tab');button.setAttribute('aria-controls','venue-panel-'+id);button.setAttribute('aria-selected',String(id==='book'));button.tabIndex=id==='book'?0:-1;
    section.id='venue-panel-'+id;section.setAttribute('role','tabpanel');section.setAttribute('aria-labelledby',button.id);section.hidden=id!=='book';section.tabIndex=0;
    tablist.append(button);panels.append(section);
  }
  panels.append(document.querySelector('#extras-selection-total'));
  const mobileTabs=matchMedia('(max-width:800px)'),extrasTotal=document.querySelector('#extras-selection-total');
  function arrangeSections(){
    panels.hidden=!mobileTabs.matches;
    const venue=document.querySelector('.booking-venue');
    for(const [id] of tabSections){
      const section=document.getElementById('venue-panel-'+id);
      if(mobileTabs.matches){
        panels.append(section);section.setAttribute('role','tabpanel');section.setAttribute('aria-labelledby','venue-tab-'+id);section.tabIndex=0;
        section.hidden=document.getElementById('venue-tab-'+id).getAttribute('aria-selected')!=='true';
      }else{
        section.hidden=false;section.removeAttribute('role');section.removeAttribute('tabindex');section.removeAttribute('aria-labelledby');
        if(id==='book')layout.insertBefore(section,venue);else venue.append(section);
      }
    }
    (mobileTabs.matches?panels:venue).append(extrasTotal);
  }
  mobileTabs.addEventListener('change',arrangeSections);arrangeSections();
  function selectTab(button){
    if(!mobileTabs.matches)return;
    tablist.querySelectorAll('[role="tab"]').forEach(tab=>{const active=tab===button;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;document.getElementById(tab.getAttribute('aria-controls')).hidden=!active;});
    button.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'});
  }
  tablist.addEventListener('click',event=>{const button=event.target.closest('[role="tab"]');if(button)selectTab(button);});
  tablist.addEventListener('keydown',event=>{
    const tabs=[...tablist.children],index=tabs.indexOf(document.activeElement);if(index<0)return;
    const next=event.key==='ArrowRight'?(index+1)%tabs.length:event.key==='ArrowLeft'?(index+tabs.length-1)%tabs.length:event.key==='Home'?0:event.key==='End'?tabs.length-1:-1;
    if(next>=0){event.preventDefault();selectTab(tabs[next]);tabs[next].focus();}
  });
  document.querySelectorAll('#site-header a[href="#booking-page-title"]').forEach(link=>link.addEventListener('click',()=>selectTab(tablist.firstElementChild)));
  const menuButton=document.querySelector('#site-header [data-action="menu"]'),mobileMenu=document.querySelector('#mobile-menu');
  function closeMenu(){mobileMenu.hidden=true;menuButton.setAttribute('aria-expanded','false');menuButton.setAttribute('aria-label','Open menu');}
  menuButton.addEventListener('click',()=>{const open=mobileMenu.hidden;mobileMenu.hidden=!open;menuButton.setAttribute('aria-expanded',String(open));menuButton.setAttribute('aria-label',open?'Close menu':'Open menu');});
  mobileMenu.addEventListener('click',event=>{if(event.target.closest('a'))closeMenu();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!mobileMenu.hidden){closeMenu();menuButton.focus();}});
  let user=null,observer,actionObserver,photos=[],photoIndex=0;
  function closeCheckout(){
    observer?.disconnect();clearCheckoutActions();
    document.querySelector('#booking-checkout-dialog')?.remove();document.body.classList.remove('checkout-dialog-open');
    history.replaceState(null,'',location.pathname);stage(1);
    if(!content.querySelector('.slot-picker-dialog'))window.PikolSlotPicker.open();
    else window.PikolSlotPicker.refresh();
    document.querySelector('#slot-picker-continue')?.focus({preventScroll:true});
  }
  function clearCheckoutActions(){actionObserver?.disconnect();document.querySelector('#booking-checkout-actions')?.remove();document.body.classList.remove('has-checkout-actions');}
  function checkoutActions(doc){
    clearCheckoutActions();
    const card=document.createElement('footer');card.id='booking-checkout-actions';card.className='booking-checkout-actions';card.hidden=true;
    card.innerHTML='<button type="button" class="booking-action-back">Back to review</button><button type="button" class="booking-action-submit"></button>';
    document.querySelector('#booking-checkout-dialog').append(card);
    const sync=()=>{
      const form=doc.querySelector('#home-review-form,#home-payment-form'),submit=form?.querySelector('.checkout-actions [type="submit"]');
      card.hidden=!submit;document.body.classList.toggle('has-checkout-actions',Boolean(submit));if(!submit)return;
      const review=form.id==='home-review-form';
      const button=card.querySelector('.booking-action-submit');button.textContent=submit.textContent;button.disabled=submit.disabled;
      const back=doc.querySelector('#checkout-back'),backButton=card.querySelector('.booking-action-back');backButton.hidden=false;backButton.disabled=Boolean(back?.disabled||submit.disabled&&review);backButton.textContent=back?.textContent||'Cancel';
    };
    card.querySelector('.booking-action-submit').addEventListener('click',()=>{const form=doc.querySelector('#home-review-form,#home-payment-form'),button=form?.querySelector('.checkout-actions [type="submit"]');if(button&&!button.disabled)form.requestSubmit(button);});
    card.querySelector('.booking-action-back').addEventListener('click',()=>{const back=doc.querySelector('#checkout-back');if(back)back.click();else closeCheckout();});
    actionObserver=new MutationObserver(sync);actionObserver.observe(doc.body,{subtree:true,childList:true,attributes:true,attributeFilter:['disabled']});sync();
  }
  let products=[],extrasLocked=false,foodCategory='food';
  const quantities=new Map(),money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',maximumFractionDigits:2}).format(value);
  const selectedExtras=()=>products.filter(p=>quantities.get(String(p.product_id))>0).map(p=>({product_id:Number(p.product_id),quantity:quantities.get(String(p.product_id))}));
  const escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function renderExtras(){
    for(const [category,target] of [['equipment','#equipment-products'],['food','#food-products']]){
      const selected=products.filter(p=>category==='food'?['food','snack'].includes(p.category):p.category===category).reduce((sum,p)=>sum+(quantities.get(String(p.product_id))||0),0);
      document.querySelector(`[data-extra-count="${category}"]`).textContent=selected?`${selected} selected`:'Optional';
      document.querySelector(target).innerHTML=products.filter(p=>p.category===(category==='food'?foodCategory:category)).map(p=>{
        const qty=quantities.get(String(p.product_id))||0;
        return `<article class="extra-product" data-selected="${qty>0}"><img src="${escape(p.image_path)}" alt="${escape(p.name)}" loading="lazy"><div><h3>${escape(p.name)}</h3><p class="extra-product-price">${money(Number(p.unit_price))}</p><div class="extra-quantity"><button type="button" data-extra="${p.product_id}" data-delta="-1" aria-label="Remove one ${escape(p.name)}" ${extrasLocked||!qty?'disabled':''}>−</button><output aria-label="${escape(p.name)} quantity">${qty}</output><button type="button" data-extra="${p.product_id}" data-delta="1" aria-label="Add one ${escape(p.name)}" ${extrasLocked||qty>=10?'disabled':''}>+</button></div></div></article>`;
      }).join('')||'<p class="extras-description">No items available.</p>';
    }
    const amount=products.reduce((sum,p)=>sum+(quantities.get(String(p.product_id))||0)*Number(p.unit_price),0);
    document.querySelector('#extras-selection-total').textContent=amount?'Selected add-ons: '+money(amount)+(extrasLocked?' · Included in booking review':''):'Optional items are added once per booking checkout.';
  }
  layout.addEventListener('click',event=>{
    const filter=event.target.closest('[data-food-category]');
    if(filter){
      foodCategory=filter.dataset.foodCategory;
      document.querySelectorAll('[data-food-category]').forEach(button=>button.setAttribute('aria-pressed',String(button===filter)));
      renderExtras();document.querySelector('#food-products').scrollTop=0;return;
    }
    const button=event.target.closest('[data-extra]');if(!button||button.disabled||extrasLocked)return;
    const id=button.dataset.extra,qty=Math.max(0,Math.min(10,(quantities.get(id)||0)+Number(button.dataset.delta)));quantities.set(id,qty);renderExtras();
    document.querySelector(`[data-extra="${id}"][data-delta="${button.dataset.delta}"]`)?.focus({preventScroll:true});
  });
  function loadProducts(){return fetch('api/booking-addons.php',{cache:'no-store'}).then(r=>{if(!r.ok)throw Error();return r.json();}).then(data=>{if(extrasLocked&&products.length)return;products=data.items;renderExtras();}).catch(()=>{if(!products.length)for(const id of ['equipment-products','food-products'])document.getElementById(id).textContent='Items are temporarily unavailable. You can still book a court.';});}
  void loadProducts();
  const session=fetch('api/auth.php',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(data=>{
    user=data.user;
    if(user)document.querySelectorAll('#booking-account-link,#booking-account-mobile').forEach(link=>{link.textContent='My account';link.href=user.role==='admin'?'admin/':'customer/';});
  }).catch(()=>{});
  function stage(step){
    extrasLocked=step!==1;if(products.length)renderExtras();
    title.textContent=step===1?'Book a court':step===2?'Review selected court times':step===3?'Payment':'Booking submitted';
    document.title=title.textContent+' · Pikol Castle';
    const dialogTitle=document.querySelector('#booking-dialog-title');
    const dialog=document.querySelector('#booking-checkout-dialog');
    if(dialog&&dialog.dataset.step!==String(step)){dialog.dataset.step=String(step);dialog.querySelector('.booking-dialog-content').scrollTop=0;}
    if(dialogTitle){dialogTitle.textContent=step===2?'Booking details':step===3?'Payment':'Booking submitted';document.querySelector('#booking-dialog-description').textContent=step===2?'Review your selected court times and enter your details.':step===3?'Choose how to pay and submit your payment screenshot.':'Your booking request has been received.';}
    document.querySelectorAll('#booking-page-progress li, #booking-dialog-steps li').forEach((li)=>{const i=[...li.parentElement.children].indexOf(li);li.classList.toggle('current',i+1===step);li.classList.toggle('done',i+1<step);li.querySelector('b').textContent=i+1<step?'✓':String(i+1);if(i+1===step)li.setAttribute('aria-current','step');else li.removeAttribute('aria-current');});
  }
  function slots(){closeCheckout();}
  async function checkout(choice){
    await session;
    if(user?.role==='admin'){content.textContent='Please use a player account or sign out to book as a guest.';return;}
    if(!choice.hold_id&&!choice.payment_booking&&!choice.addons)choice={...choice,addons:JSON.stringify(selectedExtras())};
    observer?.disconnect();clearCheckoutActions();stage(choice.payment_booking||choice.hold_id?3:2);
    document.querySelector('#booking-checkout-dialog')?.remove();
    const dialog=document.createElement('dialog');dialog.id='booking-checkout-dialog';dialog.className='booking-checkout-dialog';dialog.setAttribute('aria-labelledby','booking-dialog-title');dialog.setAttribute('aria-describedby','booking-dialog-description');
    dialog.innerHTML='<header class="booking-dialog-header"><div><h2 id="booking-dialog-title">Booking details</h2><p id="booking-dialog-description">Review your selected court times and enter your details.</p></div><button type="button" class="booking-dialog-close" aria-label="Close checkout">&times;</button></header><div class="booking-dialog-stepper"></div><div class="booking-dialog-content"><p class="booking-dialog-loading" role="status">Loading your booking details...</p></div>';
    const steps=document.querySelector('#booking-page-progress').cloneNode(true);steps.id='booking-dialog-steps';steps.hidden=false;dialog.querySelector('.booking-dialog-stepper').append(steps);
    dialog.querySelector('.booking-dialog-close').addEventListener('click',closeCheckout);
    dialog.addEventListener('cancel',event=>{event.preventDefault();closeCheckout();});
    document.body.append(dialog);document.body.classList.add('checkout-dialog-open');dialog.showModal();stage(choice.payment_booking||choice.hold_id?3:2);
    const params=new URLSearchParams({...choice,embedded:1,checkout:1,review:1,...(!user&&!choice.payment_booking?{guest:1}:{})});
    history.replaceState(null,'',location.pathname+'?'+new URLSearchParams(choice));
    const frame=document.createElement('iframe');frame.id='homepage-slot-checkout';frame.className='slot-checkout-frame';frame.title='Review and pay for your court booking';frame.src='customer/book-court.php?'+params;
    frame.addEventListener('load',()=>{
      try{
        const doc=frame.contentDocument;
        if(new URL(frame.contentWindow.location.href).pathname.endsWith('/index.html')){location.assign('index.html#login');return;}
        const style=doc.createElement('style');style.textContent='html,body,.workspace{min-height:0!important;height:auto!important}body{overflow:hidden!important}.customer-embedded #player-main{padding:0!important;margin:0!important}.admin-footer{display:none!important}';doc.head.append(style);
        style.textContent+='#home-review-form>.checkout-actions,#home-payment-form>.checkout-actions{display:none!important}';
        checkoutActions(doc);
        const resize=()=>{frame.style.height=Math.ceil(doc.body.getBoundingClientRect().height)+4+'px';};
        observer?.disconnect();observer=new ResizeObserver(resize);observer.observe(doc.body);resize();
      }catch{dialog.querySelector('.booking-dialog-content').textContent='Checkout could not be loaded. Close this window and try again.';}
    });
    dialog.querySelector('.booking-dialog-content').replaceChildren(frame);
  }
  window.PikolSlotPicker.configure({page:true,openModal:markup=>{
    content.innerHTML=markup;const panel=content.querySelector('.slot-picker-dialog');panel.removeAttribute('role');panel.removeAttribute('tabindex');panel.querySelector('.picker-heading').remove();panel.querySelector('.picker-progress').remove();
  },onContinue:checkout});
  window.addEventListener('message',event=>{
    if(event.origin!==location.origin||event.source!==document.querySelector('#homepage-slot-checkout')?.contentWindow)return;
    if(event.data?.type==='pikol-checkout-stage'&&[2,3,4].includes(Number(event.data.step))){
      if(Array.isArray(event.data.addons)){quantities.clear();for(const item of event.data.addons){quantities.set(String(item.product_id),Number(item.quantity));const product=products.find(p=>Number(p.product_id)===Number(item.product_id));if(product)product.unit_price=item.unit_price;}}
      stage(Number(event.data.step));
      const hold=new URL(event.source.location.href).searchParams.get('hold_id');
      if(hold)history.replaceState(null,'',location.pathname+'?'+new URLSearchParams({hold_id:hold}));
    }
    if(event.data?.type==='pikol-checkout-slots')slots();
    if(event.data?.type==='pikol-checkout-close')location.assign('index.html');
    if(event.data?.type==='pikol-checkout-reservations')location.assign('customer/my-reservations.php');
    if(event.data?.type==='pikol-account-expired')location.assign('index.html#login');
  });
  document.addEventListener('pikol:live-update',event=>{window.PikolSlotPicker.refresh();if(!extrasLocked&&event.detail.changes.includes('settings'))void loadProducts();});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)window.PikolSlotPicker.refresh();});
  window.addEventListener('popstate',()=>location.reload());
  let photoRequest=0;
  async function showPhoto(index){
    if(!photos.length)return;
    const previous=photoIndex;photoIndex=(index+photos.length)%photos.length;
    const request=++photoRequest,photo=photos[photoIndex],direction=index<previous?-1:1;
    document.querySelectorAll('#venue-thumbnails button').forEach((button,i)=>button.setAttribute('aria-pressed',String(i===photoIndex)));
    const preload=new Image();preload.src=photo.image_path;
    try{await preload.decode();}catch{return;}
    if(request!==photoRequest)return;
    const image=document.querySelector('#venue-photo'),changed=image.getAttribute('src')!==photo.image_path;
    image.getAnimations().forEach(animation=>animation.cancel());
    image.src=photo.image_path;image.alt=photo.court_name;image.classList.toggle('venue-logo-photo',Boolean(photo.is_logo));
    if(changed&&!matchMedia('(prefers-reduced-motion: reduce)').matches)image.animate([{opacity:.35,transform:`translateX(${direction*12}px) scale(1.015)`},{opacity:1,transform:'translateX(0) scale(1)'}],{duration:280,easing:'cubic-bezier(.16,1,.3,1)'});
  }
  document.querySelector('#venue-photo-previous').addEventListener('click',()=>showPhoto(photoIndex-1));document.querySelector('#venue-photo-next').addEventListener('click',()=>showPhoto(photoIndex+1));
  fetch('api/courts.php',{cache:'no-store'}).then(r=>{if(!r.ok)throw Error();return r.json();}).then(data=>{
    photos=[...(data.gallery_logo?[{image_path:data.gallery_logo,court_name:'Pikol Royal Club logo',is_logo:true}]:[]),...data.items.filter(c=>c.image_path)];const prices=data.items.map(c=>Number(c.hourly_rate)).filter(n=>n>0);
    document.querySelector('#venue-rate').textContent=prices.length?'From '+new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',maximumFractionDigits:0}).format(Math.min(...prices))+'/hr':'Contact the venue for rates';
    const thumbnails=document.querySelector('#venue-thumbnails');photos.forEach((photo,i)=>{const button=document.createElement('button');button.type='button';button.setAttribute('aria-label','Show '+photo.court_name);const img=document.createElement('img');img.src=photo.image_path;img.alt='';if(photo.is_logo)img.className='venue-logo-thumbnail';button.append(img);button.addEventListener('click',()=>showPhoto(i));thumbnails.append(button);});showPhoto(0);
  }).catch(()=>{document.querySelector('#venue-rate').textContent='Court rates are currently unavailable.';});
  const query=Object.fromEntries(new URLSearchParams(location.search));
  if(query.court||query.payment_booking||query.hold_id)checkout(query);else slots();
})();
