/* Multiple non-overlapping times on one court/date, saved as an atomic group. */
(() => {
  const P=window.Player,E=P.e;
  const original={choose:P.chooseCourt,load:P.loadSlots,slots:P.renderSlots,summary:P.renderSummary,select:P.actions['select-slot'],review:P.actions['review-booking']};
  const selected=state=>state.multiSlots|| (state.multiSlots=new Map());
  const ordered=state=>[...selected(state).values()].sort((a,b)=>a.slot.start_time.localeCompare(b.slot.start_time));
  const active=state=>P.page==='book'&&P.booking===state;
  function clear(state){state.multiSlots=new Map();state.multiBusy=false;state.multiVersion=(state.multiVersion||0)+1;state.slot=null;}
  P.chooseCourt=async(...args)=>{clear(P.booking);return original.choose(...args);};
  P.loadSlots=async(...args)=>{if(!P.booking.old)clear(P.booking);return original.load(...args);};
  P.renderSlots=()=>{
    const state=P.booking;if(state.old)return original.slots();
    const target=P.$('#booking-slots');if(!target)return;
    const picks=ordered(state);
    target.innerHTML=state.slots.length?state.slots.map(slot=>{
      const chosen=selected(state).has(slot.start_time),overlap=!chosen&&picks.some(item=>slot.start_time<item.slot.end_time&&slot.end_time>item.slot.start_time);
      const disabled=state.multiBusy||(!chosen&&(slot.state!=='Available'||overlap));
      const label=chosen?'Selected — click to remove':overlap?'Overlaps selection':slot.state;
      return `<button type="button" class="time-slot slot-${slot.state.toLowerCase()} ${chosen?'selected':''}" data-action="select-slot" data-start="${slot.start_time}" aria-pressed="${chosen}" ${disabled?'disabled':''}><strong>${P.time(slot.start_time)} – ${P.time(slot.end_time)}</strong><span>${E(label)}</span></button>`;
    }).join(''):'<div class="slot-empty">No court times are available on this day. Try another date.</div>';
    const note=P.$('.slot-privacy');if(note)note.textContent='Select up to 10 available time slots on this court and date. Click a selected slot again to remove it. Each slot is 1 hour; start times are 30 minutes apart.';
  };
  P.actions['select-slot']=async button=>{
    const state=P.booking;if(state.old)return original.select(button);if(state.multiBusy)return;
    const start=button.dataset.start,picks=selected(state);
    if(picks.has(start)){picks.delete(start);state.slot=ordered(state)[0]?.slot||null;P.renderSlots();P.renderSummary();return;}
    if(picks.size>=10){P.toast('Select up to 10 time slots at a time.','error');return;}
    const slot=state.slots.find(s=>s.start_time===start&&s.state==='Available');if(!slot)return;
    if(ordered(state).some(item=>slot.start_time<item.slot.end_time&&slot.end_time>item.slot.start_time)){P.toast('Choose a time that does not overlap your other selections.','error');return;}
    const version=state.multiVersion;state.multiBusy=true;P.renderSlots();P.renderSummary();
    try{
      const quote=await P.api('quote',{court_id:state.court.court_id,booking_date:state.date,start_time:slot.start_time,end_time:slot.end_time},'POST');
      if(!active(state)||version!==state.multiVersion)return;
      picks.set(start,{slot,quote});state.slot=slot;
    }catch(error){if(active(state)&&version===state.multiVersion)P.toast(error.message,'error');}
    finally{if(active(state)&&version===state.multiVersion){state.multiBusy=false;P.renderSlots();P.renderSummary();}}
  };
  P.actions['remove-booking-slot']=button=>P.actions['select-slot'](button);
  P.actions['clear-booking-slots']=()=>{const state=P.booking;if(state.multiBusy)return;clear(state);P.renderSlots();P.renderSummary();};
  function totals(items,removable=false){
    const total=items.reduce((sum,item)=>sum+Number(item.quote.amount),0),minutes=items.reduce((sum,item)=>sum+Number(item.quote.duration_minutes),0);
    return `<ul class="multi-slot-list">${items.map(({slot,quote})=>`<li><div><strong>${P.time(slot.start_time)} – ${P.time(slot.end_time)}</strong><small>${Number(quote.duration_minutes)} minutes</small></div><span>${P.money(quote.amount)}</span>${removable?`<button type="button" class="icon-button" data-action="remove-booking-slot" data-start="${slot.start_time}" aria-label="Remove ${E(P.time(slot.start_time))}">${P.icon('x')}</button>`:''}</li>`).join('')}</ul><p class="multi-slot-count">${items.length} time slot${items.length===1?'':'s'} · ${minutes} minutes total</p><div class="booking-summary-total"><span>Total court fee</span><strong>${P.money(total)}</strong></div>`;
  }
  P.multiSlotTotals=totals;
  P.renderSummary=()=>{
    const state=P.booking;if(state.old)return original.summary();
    const target=P.$('#booking-summary');if(!target)return;
    const items=ordered(state);
    target.innerHTML=`<div class="panel-heading"><div><h2>Booking summary</h2><p>Select one or more court times.</p></div>${P.icon('ticket-check')}</div><div class="panel-body">${items.length?`<dl class="booking-summary-details"><div><dt>Court</dt><dd>${E(state.court.court_name)}</dd></div><div><dt>Date</dt><dd>${P.date(state.date)}</dd></div></dl>${totals(items,!state.multiBusy)}${P.button('clear-booking-slots','Clear selections','x','text-button',state.multiBusy?'disabled':'')}`:'<div class="summary-placeholder">Choose a date, then select your available time slots.</div>'}${state.multiBusy?'<p class="multi-slot-status" role="status"><span class="spinner"></span>Checking your selected times...</p>':''}<label class="section-spacer">Payment method<select id="booking-payment" ${state.multiBusy?'disabled':''}>${state.catalog.methods.map(m=>P.option(m.payment_method_id,m.name,state.method)).join('')}</select></label><p class="payment-method-note" id="payment-instructions"></p><div class="booking-policies">${P.icon('shield-check')}<p>Each selected time is saved as a separate reservation with its own reference and payment record. Staff confirmation and payment verification are still required.</p></div><button type="button" class="primary-button booking-continue" data-action="review-booking" ${!items.length||state.multiBusy?'disabled':''}>Review ${items.length||''} selected time${items.length===1?'':'s'} ${P.icon('arrow-right')}</button><p class="booking-summary-footnote">All selected times are rechecked before saving. If any time is unavailable, none are booked.</p></div>`;
    const select=P.$('#booking-payment');const instructions=()=>{state.method=select.value;P.$('#payment-instructions').textContent=state.catalog.methods.find(m=>String(m.payment_method_id)===state.method)?.instructions||'';};select.addEventListener('change',instructions);instructions();P.icons();
  };
  P.actions['review-booking']=async()=>{
    const state=P.booking;if(state.old)return original.review();
    if(state.multiBusy||!selected(state).size)return;
    const version=state.multiVersion;state.multiBusy=true;P.renderSlots();P.renderSummary();
    try{
      // Refresh every quote together, including prices and five-minute quote tickets.
      const items=[];
      for(const item of ordered(state)){
        const quote=await P.api('quote',{court_id:state.court.court_id,booking_date:state.date,start_time:item.slot.start_time,end_time:item.slot.end_time},'POST');
        if(!active(state)||version!==state.multiVersion)return;
        items.push({slot:item.slot,quote});
      }
      items.forEach(item=>selected(state).set(item.slot.start_time,item));
      const method=state.method;
      if(P.config.embedded&&P.routeParams.checkout==='1'){P.homeCheckout.review({state,items,method,totals});return;}
      P.formModal('Review selected court times',`<dl class="booking-summary-details"><div><dt>Court</dt><dd>${E(state.court.court_name)}</dd></div><div><dt>Date</dt><dd>${P.date(state.date)}</dd></div>${P.config.embedded&&P.routeParams.checkout==='1'?'':`<div><dt>Payment method</dt><dd>${E(state.catalog.methods.find(m=>String(m.payment_method_id)===method)?.name||'')}</dd></div>`}</dl>${totals(items)}${P.config.embedded&&P.routeParams.checkout==='1'?`<label class="section-spacer">Payment method<select name="checkout_payment_method" required>${state.catalog.methods.map(m=>P.option(m.payment_method_id,m.name,method)).join('')}</select></label>`:''}<div class="helper-note section-spacer">${P.icon('info')}You will receive ${items.length} separate reservation reference${items.length===1?'':'s'}. All start Pending and Unpaid.</div><label class="policy-checkbox section-spacer"><input type="checkbox" required> I understand the ${E(state.catalog.policies.cancellation_hours)}-hour change cutoff, and that staff confirmation and payment verification are required for each reservation.</label>`,async form=>{
        const paymentMethod=form.get('checkout_payment_method')||method;
        const result=await P.api('create-booking',{quote_tokens:items.map(item=>item.quote.quote_token),payment_method_id:paymentMethod},'POST');
        await P.go('reservations');await P.pollNotifications();
        P.modal('Your court requests are saved',`<div class="dialog-body booking-success"><div class="booking-success-icon">${P.icon('check')}</div><h2>${result.count} time slot${result.count===1?'':'s'} reserved</h2><p>Your requests are Pending. Staff confirmation and payment verification are still required.</p><ul class="multi-slot-results">${result.bookings.map(b=>`<li><div><strong>${E(b.reference_no)}</strong><small>${P.time(b.start_time)} – ${P.time(b.end_time)}</small></div>${P.button('reservation-detail','View details','eye','secondary-button',`data-id="${b.booking_id}"`)}</li>`).join('')}</ul><div class="booking-summary-total"><span>Total court fee</span><strong>${P.money(result.total)}</strong></div><div class="button-row section-spacer">${P.button('close-modal','View my reservations','tickets','primary-button')}${P.button('success-dashboard','Dashboard','layout-dashboard','secondary-button')}</div></div>`,{wide:true});
      },{label:'Confirm selected times',keepOpen:true,subtitle:'Your selections are saved together, with one reservation per time slot.'});
    }catch(error){if(active(state)&&version===state.multiVersion){P.toast(error.message+' Refresh availability and choose your times again.','error');}}
    finally{if(active(state)&&version===state.multiVersion){state.multiBusy=false;P.renderSlots();P.renderSummary();}}
  };
})();
