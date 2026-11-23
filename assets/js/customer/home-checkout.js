/* The floating homepage checkout reuses quotes, reservations and private proof uploads. */
(() => {
  const P=window.Player,E=P.e;let flow,clockTimer,syncTimer;
  const target=()=>P.$('#player-main');
  function stage(step){parent.postMessage({type:'pikol-checkout-stage',step,addons:flow?.addons||[]},location.origin);}
  function error(message){const box=P.$('#checkout-error');box.textContent=message;box.hidden=false;box.scrollIntoView({block:'nearest'});}
  const addonTotal=()=> (flow.addons||[]).reduce((sum,item)=>sum+Number(item.quantity)*Number(item.unit_price),0);
  const total=()=>flow.items.reduce((n,item)=>n+Number(item.quote.amount),0)+addonTotal();
  function addonDetails(){return flow.addons?.length?`<section class="checkout-addon-summary"><h3>Add-ons & refreshments</h3><ul>${flow.addons.map(item=>`<li><span>${E(item.name)} × ${E(item.quantity)}</span><strong>${P.money(Number(item.quantity)*Number(item.unit_price))}</strong></li>`).join('')}</ul><p class="checkout-note">Added once for all selected court times.</p></section>`:'';}
  const method=()=>flow.state.catalog.methods.find(m=>String(m.payment_method_id)===String(flow.method));
  let updatingAccount=false;
  document.addEventListener('pikol:live-update',async event=>{
    const form=P.$('#home-payment-form');
    if(updatingAccount||!form||!flow||!event.detail.changes.some(key=>['settings','payments'].includes(key)))return;
    const current=method();if(!current||current.name==='Cash')return;
    updatingAccount=true;
    try{
      const data=await P.api('available-courts'),updated=data.methods.find(item=>String(item.payment_method_id)===String(current.payment_method_id));
      if(!updated||P.$('#home-payment-form')!==form||method()!==current)return;
      Object.assign(current,updated);
      const qr=form.querySelector('.checkout-qr-window'),image=qr?.querySelector('img');
      if(qr&&image&&current.qr_image){
        qr.href='../'+current.qr_image;image.src='../'+current.qr_image;
        image.classList.toggle('cropped',Boolean(current.qr_crop));image.style.cssText='';
        if(current.qr_crop)Object.assign(image.style,current.qr_crop);
      }
      const rows=form.querySelectorAll('.checkout-account-row strong');
      if(rows.length===2){rows[0].textContent=current.account_name||'Not configured';rows[1].textContent=current.account_number||'Not configured';}
      if(!current.example)form.querySelector('.checkout-example-label')?.remove();
    }catch{}finally{updatingAccount=false;}
  });
  function summary(){
    const c=flow.state.court,ranges=[];
    for(const item of [...flow.items].sort((a,b)=>a.slot.start_time.localeCompare(b.slot.start_time))){
      const last=ranges[ranges.length-1];
      if(last&&last.end===item.slot.start_time)last.end=item.slot.end_time;
      else ranges.push({start:item.slot.start_time,end:item.slot.end_time});
    }
    const date=new Intl.DateTimeFormat('en-US',{weekday:'long',month:'long',day:'numeric',timeZone:'UTC'}).format(new Date(flow.state.date+'T12:00:00Z'));
    const subtotal=flow.items.reduce((sum,item)=>sum+Number(item.quote.amount),0);
    return `<aside class="checkout-summary checkout-confirm-details" data-court="${E(c.court_name)}" data-date="${E(P.date(flow.state.date))}"><h3>Confirm details</h3><div class="confirm-club"><span>Club</span><strong>${E(flow.state.catalog.policies.venue_name||'Pikol Castle')}</strong></div><h4>Slots</h4><div class="confirm-slots">${ranges.map(range=>`<div class="confirm-slot"><p>${E(date)}</p><div><strong>${E(c.court_name)}</strong><span>${P.time(range.start)} &ndash; ${P.time(range.end)}</span></div></div>`).join('')}</div><div class="confirm-subtotal"><span>Subtotal</span><strong>${P.money(subtotal)}</strong></div>${addonDetails()}<div class="checkout-total"><span>Total Amount</span><strong>${P.money(total())}</strong></div></aside>`;
  }
  function shell(content){stopClock();target().innerHTML=`<div class="home-checkout"><div id="checkout-error" class="form-error" role="alert" hidden></div>${content}</div>`;P.icons();}
  function reminder(){
    return `<section class="checkout-card checkout-reminder" aria-labelledby="checkout-reminder-title">
      <h3 id="checkout-reminder-title">REMINDER‼️</h3>
      <ul>
        <li><span aria-hidden="true">—</span><span>No refunds or cancellations once confirmed.</span></li>
        <li><span aria-hidden="true">—</span><span>However, your slot is fully transferable. If you can no longer attend, you may find a replacement player and inform the host for approval.</span></li>
        <li><span aria-hidden="true">—</span><span>Please be <strong>RESPECTFUL</strong> to all participants, <strong>REGARDLESS</strong> of skill level, and <strong>FOLLOW</strong> the queue.</span></li>
      </ul>
      <p><strong>PLEASE BE ON TIME TO MAXIMIZE PLAYING TIME.</strong></p>
    </section>`;
  }

  function stopClock(){clearInterval(clockTimer);clearInterval(syncTimer);clockTimer=null;syncTimer=null;}
  function applyHold(data){
    flow.addons=data.addons||[];
    flow.hold=data;flow.holdSyncedAt=performance.now();flow.created=data;flow.paymentIds=data.payment_ids.map(String);
    if(P.config.guest&&data.bookings[0]){
      const guest=data.bookings[0];flow.guest={full_name:guest.full_name,email:guest.email,phone:guest.phone};
    }
    flow.items=data.bookings.map(b=>({slot:{start_time:b.start_time,end_time:b.end_time},quote:{amount:Number(b.court_fee)-Number(b.addons_total||0),duration_minutes:b.duration_minutes}}));
    const url=new URL(location.href);url.searchParams.set('hold_id',data.hold_id);history.replaceState(history.state,'',url.pathname+url.search);
    P.routeParams.hold_id=String(data.hold_id);
  }
  const remaining=()=>Math.max(0,Math.ceil(flow.hold.expires_at_unix-flow.hold.server_time-(performance.now()-flow.holdSyncedAt)/1000));
  function expired(){
    stage(3);
    shell('<section class="checkout-card checkout-expired"><h2>Payment time expired</h2><p>Your unpaid slots have been released. Choose available slots again to make a new booking.</p><div class="checkout-actions"><button type="button" class="primary-button" id="checkout-choose-again">Choose slots again</button><button type="button" class="text-button" id="checkout-my-reservations">My reservations</button></div></section>');
    P.$('#checkout-choose-again').addEventListener('click',()=>parent.postMessage({type:'pikol-checkout-slots'},location.origin));
    if(P.config.guest)P.$('#checkout-my-reservations').remove();
    else P.$('#checkout-my-reservations').addEventListener('click',()=>parent.postMessage({type:'pikol-checkout-reservations'},location.origin));
  }
  function clockMarkup(){
    if(!flow.hold)return '';
    if(flow.hold.state==='submitted')return '<section class="checkout-hold-banner"><div><h2>Payment proof submitted</h2><p>Your slots remain reserved while staff review your payment.</p></div></section>';
    return '<section class="checkout-hold-banner" aria-labelledby="checkout-hold-title"><div><h2 id="checkout-hold-title">Your slots are held for payment</h2><p id="checkout-hold-status" role="status">Submit your payment screenshot before the timer ends to keep your slots reserved.</p></div><output id="checkout-hold-countdown" role="timer" aria-label="Time left to submit payment">10:00</output></section>';
  }
  function startClock(){
    if(!flow.hold||flow.hold.state!=='holding')return;
    const snapshot=flow;let syncing=false,expiryChecked=false;
    const check=async()=>{
      if(syncing||flow!==snapshot||!P.$('#checkout-hold-countdown'))return;
      syncing=true;
      try{
        const data=await P.api('checkout-hold',{hold_id:flow.hold.hold_id});
        if(flow!==snapshot||!P.$('#checkout-hold-countdown'))return;
        applyHold(data);
        if(data.state==='expired'){expired();return;}
        if(data.state==='submitted'){complete(false);return;}
        tick();
      }catch{
        if(flow===snapshot&&P.$('#checkout-hold-status'))P.$('#checkout-hold-status').textContent='Could not refresh the payment window. Check your connection or reload this payment step.';
      }finally{syncing=false;}
    };
    const tick=()=>{
      const output=P.$('#checkout-hold-countdown');
      if(flow!==snapshot||!output){stopClock();return;}
      const seconds=remaining();output.textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');
      if(!seconds){
        P.$('#checkout-submit').disabled=true;
        if(flow.hold.state==='holding')P.$('#checkout-hold-status').textContent='Your payment window has ended. Checking the reservation status...';
        if(!expiryChecked){expiryChecked=true;check();}
      }
    };
    tick();clockTimer=setInterval(tick,1000);syncTimer=setInterval(check,15000);
    if(!P.cleanup.includes(stopClock))P.cleanup.push(stopClock);
  }

  function guestDetails(){
    if(!P.config.guest)return '';
    const guest=flow.guest||{},readonly=flow.created?'readonly':'';
    return `<fieldset class="checkout-guest-details"><legend><span>${P.icon('users-round')}</span>Your Details</legend><label for="guest-full-name">Full name <span aria-hidden="true">*</span></label><input id="guest-full-name" name="full_name" type="text" autocomplete="name" placeholder="Juan dela Cruz" maxlength="100" value="${E(guest.full_name)}" required ${readonly}><label for="guest-email">Email address <span aria-hidden="true">*</span></label><input id="guest-email" name="email" type="email" autocomplete="email" placeholder="juan@example.com" maxlength="150" value="${E(guest.email)}" aria-describedby="guest-email-help" required ${readonly}><p id="guest-email-help">We'll send your booking confirmation to this email.</p><label for="guest-phone">Mobile number <span aria-hidden="true">*</span></label><input id="guest-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX" maxlength="20" value="${E(guest.phone)}" required ${readonly}></fieldset>`;
  }
  function review(){
    stage(2);
    shell(`<form id="home-review-form"><div class="checkout-layout"><section class="checkout-card"><h2>Booking details</h2><p class="checkout-note">Check your times and contact details. Confirming holds all selected slots for 10 minutes. Choose how to pay in the next step.</p>${guestDetails()}<label class="policy-checkbox"><input type="checkbox" required ${flow.accepted||flow.created?'checked':''}> I understand that I must submit my payment screenshot within 10 minutes. Staff confirmation and payment verification are required.</label>${flow.created?'<p class="checkout-note">Your slots are already held. Returning to payment keeps the original deadline.</p>':''}</section>${summary()}</div><div class="checkout-actions"><span class="checkout-note">${P.config.guest?'Guest booking · No account needed · ':''}${flow.items.length} one-hour slot${flow.items.length===1?'':'s'}</span><button type="submit" class="primary-button">Continue to payment</button></div></form>`);
    const form=P.$('#home-review-form');
    if(P.config.guest){
      const save=()=>{flow.guest={full_name:form.elements.full_name.value,email:form.elements.email.value,phone:form.elements.phone.value};};
      form.addEventListener('input',save);
      form.elements.phone.addEventListener('input',()=>{
        const phone=form.elements.phone,value=phone.value.replace(/[\s()\-]/g,'');
        phone.setCustomValidity(value&&!/^(?:09\d{9}|\+639\d{9})$/.test(value)?'Enter an 11-digit mobile number starting with 09, or use +639 followed by 9 digits.':'');
      });
      save();
    }
    form.addEventListener('submit',async event=>{
      event.preventDefault();flow.accepted=true;const button=P.$('[type=submit]',form);button.disabled=true;
      try{
        if(!flow.created){
          if(!flow.holdAttempted){
            const fresh=[];for(const item of flow.items)fresh.push(await P.api('quote',{court_id:flow.state.court.court_id,booking_date:flow.state.date,start_time:item.slot.start_time,end_time:item.slot.end_time},'POST'));
            if(fresh.some((q,i)=>Number(q.amount)!==Number(flow.items[i].quote.amount))){flow.items.forEach((item,i)=>item.quote=fresh[i]);review();error('Court prices changed. Review the updated total before confirming.');return;}
            flow.items.forEach((item,i)=>item.quote=fresh[i]);
          }
          flow.requestId ||= [...crypto.getRandomValues(new Uint8Array(16))].map(n=>n.toString(16).padStart(2,'0')).join('');
          flow.holdAttempted=true;
          applyHold(await P.api('checkout-hold',{request_id:flow.requestId,payment_method_id:flow.method,quote_tokens:flow.items.map(item=>item.quote.quote_token),addons:(flow.addons||[]).map(item=>({product_id:item.product_id,quantity:item.quantity})),addons_total:addonTotal(),...(P.config.guest?flow.guest:{})},'POST'));
        }
        await payment();
      }catch(e){if(/quote expired|rate changed/i.test(e.message))flow.holdAttempted=false;error(e.message);}
      finally{if(button.isConnected)button.disabled=false;}
    });
  }
  function paymentMethods(){
    return `<fieldset class="checkout-payment-methods"><legend>Payment method</legend><div class="checkout-method-buttons">${flow.state.catalog.methods.map(item=>`<button type="button" class="checkout-method-button" data-payment-method="${E(item.payment_method_id)}" aria-pressed="${String(item.payment_method_id)===String(flow.method)}">${P.icon('credit-card')}<span>${E(item.name)}</span>${P.icon('check')}</button>`).join('')}</div></fieldset>`;
  }
  function paymentAccount(m){
    const cash=m.name==='Cash';
    return `${m.qr_image?`<a class="checkout-qr-window" href="../${E(m.qr_image)}" target="_blank" rel="noopener" aria-label="Open full-size ${E(m.name)} QR"><img src="../${E(m.qr_image)}" class="checkout-qr ${m.qr_crop?'cropped':''}" style="${m.qr_crop?`width:${E(m.qr_crop.width)};left:${E(m.qr_crop.left)};top:${E(m.qr_crop.top)}`:''}" alt="${E(m.name)} payment QR"></a>`:''}<div class="checkout-account-details"><h2>${E(m.name)}</h2>${cash?`<p class="checkout-instructions">${E(m.instructions)}</p>`:`${m.example?'<p class="checkout-example-label">Example account details</p>':''}<div class="checkout-account-row"><div><small>Account name</small><strong>${E(m.account_name||'Not configured')}</strong></div><button type="button" class="checkout-copy-button" data-copy-account="name" aria-label="Copy account name">${P.icon('copy')}Copy</button></div><div class="checkout-account-row"><div><small>Account number</small><strong>${E(m.account_number||'Not configured')}</strong></div><button type="button" class="checkout-copy-button" data-copy-account="number" aria-label="Copy account number">${P.icon('copy')}Copy</button></div>${!m.qr_image?'<p class="checkout-note">QR image not configured.</p>':''}`}</div>`;
  }
  async function payment(){
    if(flow.hold){
      applyHold(await P.api('checkout-hold',{hold_id:flow.hold.hold_id}));
      if(flow.hold.state==='expired'){expired();return;}
    }else if(flow.existing){
      const details=[];for(const b of flow.created.bookings)details.push(await P.api('reservations',{id:b.booking_id}));
      flow.payments=details.flatMap(b=>b.payments.filter(p=>['Unpaid','Pending Verification'].includes(p.status)&&(!flow.paymentIds||flow.paymentIds.includes(String(p.payment_id)))));
      if(flow.payments.length!==flow.created.count)throw Error('A payment changed. Open My reservations to check its latest status.');
    }
    stage(3);let m=method();if(!m)throw Error('Choose an enabled payment method.');const cash=m.name==='Cash';
    shell(`${clockMarkup()}<p class="checkout-intro">${cash?'Your requests are saved as Pending. Pay at the venue; staff will verify your payment.':'Pay the total using the details below, then upload your receipt. Your slots remain reserved while staff verify a timely submitted payment.'}</p><form id="home-payment-form"><div class="checkout-layout">${paymentMethods()}<div class="checkout-payment-column"><section class="checkout-card checkout-qr-card" id="checkout-payment-account">${paymentAccount(m)}</section>${!cash?`<section class="checkout-card"><div class="checkout-upload-heading"><h3>Upload payment screenshot</h3><small>PNG, JPEG or WebP - up to 3 MB</small></div><label class="checkout-dropzone" id="checkout-dropzone"><input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required><span>${P.icon('upload')}</span><strong>Click to upload or drag & drop</strong><small>A screenshot of your successful payment</small><img id="checkout-preview" alt="Selected payment screenshot" hidden><span id="checkout-filename" role="status"></span></label><p class="checkout-note">For multiple slots, pay the total once. This receipt will be attached to each reservation for staff review.</p></section>`:''}</div>${summary()}</div>${reminder()}<div class="checkout-actions"><button type="button" class="text-button" id="checkout-back">${P.icon('arrow-left')}Back to review</button><button type="submit" class="primary-button" id="checkout-submit" ${!cash?'disabled':''}>Submit payment proof</button></div></form>`);
    P.$('#checkout-back').addEventListener('click',()=>{
      if(flow.existing)parent.postMessage({type:'pikol-checkout-reservations'},location.origin);
      else review();
    });
    if(flow.existing){
      P.$('#checkout-back').textContent='Back to reservations';
      P.$('#checkout-submit').textContent='Submit payment proof';
    }
    const form=P.$('#home-payment-form'),input=form.elements.proof,submit=P.$('#checkout-submit');let previewUrl;
    form.addEventListener('click',async event=>{
      const choice=event.target.closest('[data-payment-method]');
      if(choice&&!choice.disabled){
        if(String(flow.method)===choice.dataset.paymentMethod)return;
        flow.method=choice.dataset.paymentMethod;
        const next=method();
        if(!next)return;
        if((next.name==='Cash')!==cash){try{await payment();}catch(e){error(e.message);}return;}
        m=next;P.$('#checkout-payment-account').innerHTML=paymentAccount(m);
        P.$$('[data-payment-method]',form).forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.paymentMethod===String(flow.method))));
        P.icons();return;
      }
      const copy=event.target.closest('[data-copy-account]');
      if(copy){const current=method(),value=copy.dataset.copyAccount==='name'?current.account_name:current.account_number;try{await navigator.clipboard.writeText(value||'');P.toast('Account '+copy.dataset.copyAccount+' copied.');}catch{P.toast('Select and copy the account details.','error');}}
    });
    function selected(){
      const file=input.files[0];submit.disabled=true;if(previewUrl)URL.revokeObjectURL(previewUrl);P.$('#checkout-preview').hidden=true;
      if(!file)return;
      if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>3*1024*1024){error('Choose a JPEG, PNG or WebP screenshot up to 3 MB.');input.value='';return;}
      P.$('#checkout-error').hidden=true;P.$('#checkout-filename').textContent=file.name;previewUrl=URL.createObjectURL(file);P.$('#checkout-preview').src=previewUrl;P.$('#checkout-preview').hidden=false;submit.disabled=Boolean(flow.hold&&flow.hold.state==='holding'&&!remaining());
    }
    if(input){input.addEventListener('change',selected);const drop=P.$('#checkout-dropzone');
      for(const name of ['dragenter','dragover'])drop.addEventListener(name,e=>{e.preventDefault();drop.classList.add('dragging');});
      drop.addEventListener('dragleave',()=>drop.classList.remove('dragging'));
      drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('dragging');if(e.dataTransfer.files.length){const transfer=new DataTransfer();transfer.items.add(e.dataTransfer.files[0]);input.files=transfer.files;selected();}});
    }

    form.addEventListener('submit',async event=>{
      event.preventDefault();submit.disabled=true;P.$('#checkout-back').disabled=true;P.$$('[data-payment-method]',form).forEach(button=>button.disabled=true);
      try{
        if(!cash){
          const data=new FormData();data.set('payment_method_id',flow.method);data.set('proof',input.files[0]);
          if(P.config.guest)data.set('hold_id',flow.hold.hold_id);
          const paymentIds=flow.hold?flow.hold.payment_ids:flow.payments.map(p=>p.payment_id);
          paymentIds.forEach(id=>data.append('payment_ids[]',id));
          await P.api('checkout-proof',data,'POST');
        }
        if(previewUrl)URL.revokeObjectURL(previewUrl);stopClock();await P.pollNotifications().catch(()=>{});complete(cash);
      }catch(e){
        if(flow.hold){
          try{const status=await P.api('checkout-hold',{hold_id:flow.hold.hold_id});applyHold(status);if(status.state==='expired'){expired();return;}}catch{}
        }
        error(e.message);submit.disabled=Boolean(flow.hold&&flow.hold.state==='holding'&&!remaining());P.$('#checkout-back').disabled=false;P.$$('[data-payment-method]',form).forEach(button=>button.disabled=false);
      }
    });
    startClock();
  }
  function complete(cash){
    stage(4);shell(`<section class="checkout-complete checkout-card"><div class="checkout-success-icon">${P.icon('check')}</div><h2>${cash?'Your booking requests are saved':'Payment proof submitted'}</h2><p>${cash?'Pay at the venue. Staff still need to confirm your court times.':'Your slots remain reserved. Staff will review your receipt and confirm your reservations.'}</p><ul>${flow.created.bookings.map(b=>`<li><strong>${E(b.reference_no)}</strong><span>${P.time(b.start_time)} - ${P.time(b.end_time)}</span></li>`).join('')}</ul><button type="button" class="primary-button" id="checkout-reservations">View my reservations</button></section>`);
    if(P.config.guest){
      P.$('#checkout-reservations').textContent='Done';
      const note=document.createElement('p');note.textContent="Keep your reservation references. We'll email your confirmation after staff verify your payment.";P.$('#checkout-reservations').before(note);
    }
    P.$('#checkout-reservations').addEventListener('click',()=>{parent.postMessage({type:P.config.guest?'pikol-checkout-close':'pikol-checkout-reservations'},location.origin);});
  }
  P.homeCheckout={
    async resumeHold(holdId,catalog){
      try{
        const data=await P.api('checkout-hold',{hold_id:holdId});
        const first=data.bookings[0];if(!first)throw Error('This checkout has no reserved slots.');
        const state={catalog,date:first.booking_date,court:catalog.items.find(c=>String(c.court_id)===String(first.court_id))||{court_id:first.court_id,court_name:first.court_name,image_path:first.image_path}};
        const chosen=catalog.methods.find(m=>String(m.payment_method_id)===String(data.payment_method_id))||catalog.methods[0];
        if(!chosen)throw Error('Bank and wallet payments are currently unavailable. Contact the venue.');
        flow={state,items:[],method:String(chosen.payment_method_id),totals:P.multiSlotTotals,existing:!P.config.guest,created:data};
        applyHold(data);await payment();
      }catch(e){
        target().innerHTML=`<div class="home-checkout"><div class="checkout-card"><h2>Payment unavailable</h2><p class="checkout-note" role="alert">${E(e.message)}</p><button type="button" class="primary-button" id="payment-return">Back to reservations</button></div></div>`;
        if(P.config.guest)P.$('#payment-return').textContent='Choose slots again';
        P.$('#payment-return').addEventListener('click',()=>parent.postMessage({type:P.config.guest?'pikol-checkout-slots':'pikol-checkout-reservations'},location.origin));
      }
    },

    async resumePayment(bookingId,paymentId,catalog){
      target().innerHTML='<div class="home-checkout"><p class="checkout-note" role="status">Loading your reservation payment...</p></div>';
      try{
        const b=await P.api('reservations',{id:bookingId});
        if(b.checkout_hold_id)return P.homeCheckout.resumeHold(b.checkout_hold_id,catalog);
        if(!['Pending','Confirmed'].includes(b.booking_status))throw Error('This reservation is not eligible for payment proof submission.');
        const record=b.payments.find(p=>(!paymentId||String(p.payment_id)===String(paymentId))&&['Unpaid','Pending Verification'].includes(p.status));
        if(!record)throw Error('There is no payment awaiting proof for this reservation.');
        if(!catalog.methods.length)throw Error('Bank and wallet payments are currently unavailable. Contact the venue.');
        const chosen=catalog.methods.find(m=>m.name===record.payment_method)||catalog.methods[0];
        const state={catalog,date:b.booking_date,court:{court_id:b.court_id,court_name:b.court_name,image_path:b.image_path}};
        flow={state,items:[{slot:{start_time:b.start_time,end_time:b.end_time},quote:{amount:record.amount,duration_minutes:b.duration_minutes}}],method:String(chosen.payment_method_id),created:{bookings:[b],count:1},paymentIds:[String(record.payment_id)],existing:true};
        await payment();
      }catch(e){
        target().innerHTML=`<div class="home-checkout"><div class="checkout-card"><h2>Payment unavailable</h2><p class="checkout-note" role="alert">${E(e.message)}</p><button type="button" class="primary-button" id="payment-return">Back to reservations</button></div></div>`;
        P.$('#payment-return').addEventListener('click',()=>parent.postMessage({type:'pikol-checkout-reservations'},location.origin));
      }
    },
    async start(state){
      // Checkout has its own loading/error/review views. Never mount the court picker here.
      target().innerHTML='<div class="home-checkout"><p class="checkout-note" role="status">Preparing your selected court times...</p></div>';
      try{
        const requested=(P.routeParams.starts||P.routeParams.start||'').split(',').filter(Boolean).sort();
        if(!requested.length||requested.length>10||new Set(requested).size!==requested.length)throw Error('Choose between one and ten different court times.');
        state.court=state.catalog.items.find(c=>String(c.court_id)===String(P.routeParams.court));
        if(!state.court)throw Error('This court is no longer available. Choose another court.');
        const available=await P.api('available-slots',{court_id:state.court.court_id,date:state.date,duration:60});
        const items=[];
        for(const start of requested){
          const slot=available.slots.find(s=>s.start_time===start&&s.state==='Available');
          if(!slot)throw Error('One of your selected times is no longer available. Choose your slots again.');
          if(items.some(item=>slot.start_time<item.slot.end_time&&slot.end_time>item.slot.start_time))throw Error('Selected court times must not overlap.');
          const quote=await P.api('quote',{court_id:state.court.court_id,booking_date:state.date,start_time:slot.start_time,end_time:slot.end_time},'POST');
          items.push({slot,quote});
        }
        const selections=JSON.parse(P.routeParams.addons||'[]');
        if(!Array.isArray(selections)||selections.length>20)throw Error('Choose valid add-ons.');
        const seen=new Set(),addons=selections.map(item=>{const product=state.catalog.addons?.find(p=>Number(p.product_id)===Number(item.product_id)),qty=Number(item.quantity);if(!product||!Number.isInteger(qty)||qty<1||qty>10||seen.has(Number(item.product_id)))throw Error('An add-on changed. Return to slots and review your selections.');seen.add(Number(item.product_id));return {...product,quantity:qty};});
        flow={state,items,addons,method:state.method,totals:P.multiSlotTotals,created:null};review();
      }catch(e){
        target().innerHTML=`<div class="home-checkout"><div class="checkout-card"><h2>Review unavailable</h2><p class="checkout-note" role="alert">${E(e.message)}</p><button type="button" class="primary-button" id="checkout-reselect">Choose slots again</button></div></div>`;
        P.$('#checkout-reselect').addEventListener('click',()=>parent.postMessage({type:'pikol-checkout-slots'},location.origin));
      }
    },
    review(options){flow={...options,created:null};review();}
  };
})();
