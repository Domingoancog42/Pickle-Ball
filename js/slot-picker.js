/* Public availability preview with server-quoted customer and guest checkout. */
(() => {
  let config,state,controller;
  const E=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const $=s=>document.querySelector(s);
  function animate(element,frames,duration=220){
    if(!element||matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    element.getAnimations().forEach(animation=>animation.cancel());
    element.animate(frames,{duration,easing:'cubic-bezier(.16,1,.3,1)'});
  }
  const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  const add=(date,n)=>{const d=new Date(date+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10);};
  const time=t=>{const [h,m]=t.split(':');return `${Number(h)%12||12}${m==='00'?'':':'+m}${Number(h)<12?'am':'pm'}`;};
  const money=n=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',maximumFractionDigits:0}).format(n);
  const dateIcon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>';
  const arrowIcon=direction=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+(direction<0?'m14 6-6 6 6 6':'m10 6 6 6-6 6')+'"/></svg>';
  function dates(){
    $('#slot-picker-date-label').textContent=new Intl.DateTimeFormat('en-US',{weekday:'short',month:'short',day:'numeric',timeZone:'UTC'}).format(new Date(state.date+'T12:00:00Z'));
    $('#slot-picker-previous').disabled=state.date<=today();$('#slot-picker-next').disabled=state.date>=state.max;
    const input=$('#slot-picker-more-date');input.min=today();input.max=state.max;input.value=state.date;
  }
  function rates(){
    const courts=state.data?.courts||[];
    $('#slot-picker-rates').innerHTML='<span class="picker-rates-label">Rates:</span>'+courts.map(c=>'<span class="picker-rate-chip"><strong>'+money(c.hourly_rate)+'/hr</strong><span>'+E(c.court_name)+' &middot; All available times</span></span>').join('');
    if(!courts.length)$('#slot-picker-rates').append(' No courts available');
  }
  function summary(){
    const court=state.data?.courts.find(c=>String(c.court_id)===state.court);
    const count=state.picks.size,ranges=[];
    const footer=$('.picker-footer'),wasHidden=footer.hidden;
    const selectionKey=JSON.stringify([state.court,[...state.picks.keys()].sort()]);
    const changed=footer.dataset.selection!==selectionKey;
    footer.dataset.selection=selectionKey;
    footer.hidden=!count;
    $('.slot-picker-dialog').classList.toggle('has-selection',Boolean(count));
    for(const slot of [...state.picks.values()].sort((a,b)=>a.start_time.localeCompare(b.start_time))){
      const last=ranges[ranges.length-1];
      if(last&&last.end===slot.start_time)last.end=slot.end_time;
      else ranges.push({start:slot.start_time,end:slot.end_time});
    }
    $('#slot-picker-summary').innerHTML=count?'<span class="picker-selection-count">'+ranges.length+' selection'+(ranges.length===1?'':'s')+'</span><strong>'+E(court.court_name)+'</strong><span class="picker-selection-times">'+ranges.map(r=>time(r.start)+' &ndash; '+time(r.end)).join(', ')+' &middot; '+count+' total hour'+(count===1?'':'s')+'</span>':'';
    $('#slot-picker-total').textContent=count?money(count*Number(court.hourly_rate)):'';
    $('#slot-picker-continue').disabled=state.busy||!count;$('#slot-picker-clear').hidden=!count;
    if(count&&changed){
      if(wasHidden)animate(footer,[{transform:'translateY(24px)',opacity:0},{transform:'translateY(0)',opacity:1}],300);
      else animate($('#slot-picker-summary'),[{opacity:.45,transform:'translateY(4px)'},{opacity:1,transform:'translateY(0)'}]);
      animate($('#slot-picker-total'),[{transform:'scale(.94)',opacity:.5},{transform:'scale(1)',opacity:1}]);
    }
  }
  function grid(){
    const focused=document.activeElement?.closest('[data-picker-court]');
    const focusKey=focused?{court:focused.dataset.pickerCourt,start:focused.dataset.pickerStart}:null;
    const courts=state.data.courts,starts=[...new Set(courts.flatMap(c=>c.slots.filter(s=>s.start_time.slice(3,5)==='00').map(s=>s.start_time)))].sort();
    const html=!courts.length?'<p class="picker-empty">No courts are enabled for booking.</p>':!starts.length?'<p class="picker-empty">No court times on this date. Choose another day.</p>':`<table class="picker-table"><thead><tr><th scope="col">Time</th>${courts.map(c=>`<th scope="col">${E(c.court_name)}<small>${E(c.type)}</small></th>`).join('')}</tr></thead><tbody>${starts.map(start=>{
      const example=courts.flatMap(c=>c.slots).find(s=>s.start_time===start);
      const hour=Number(start.slice(0,2)),period=hour<6?'Late night':hour<12?'Morning':hour<18?'Afternoon':'Evening';
      const prior=starts[starts.indexOf(start)-1],priorHour=prior?Number(prior.slice(0,2)):-1,priorPeriod=priorHour<0?'':priorHour<6?'Late night':priorHour<12?'Morning':priorHour<18?'Afternoon':'Evening';
      const heading=period!==priorPeriod?'<tr class="picker-period"><th colspan="'+(courts.length+1)+'" scope="rowgroup">'+period+'</th></tr>':'';
      const slotRates=[...new Set(courts.filter(c=>c.slots.some(slot=>slot.start_time===start)).map(c=>Number(c.hourly_rate)))].sort((a,b)=>a-b);
      const slotPrice=slotRates.length===1?money(slotRates[0]):money(slotRates[0])+'–'+money(slotRates[slotRates.length-1]);
      return `${heading}<tr><th scope="row"><span class="picker-time-range">${time(start)}-${time(example.end_time)}</span><strong class="picker-time-price" aria-label="${E(slotRates.length===1?'Hourly court price':'Hourly court price range')}">${slotPrice}</strong></th>${courts.map(c=>{
        const slot=c.slots.find(s=>s.start_time===start),available=slot?.state==='Available',selected=String(c.court_id)===state.court&&state.picks.has(start);
        const overlaps=!selected&&state.court===String(c.court_id)&&[...state.picks.values()].some(s=>start<s.end_time&&slot?.end_time>s.start_time);
        const booked=['Reserved','Booked','Confirmed'].includes(slot?.state),label=selected?'Selected':overlaps?'Overlaps selection':available?'Available':booked?'Booked':slot?.state||'Closed';
        return `<td><button type="button" data-picker-court="${c.court_id}" data-picker-start="${start}" class="picker-slot ${selected?'selected':booked?'booked':available&&!overlaps?'available':'blocked'}" ${!available||overlaps?'disabled':''} aria-pressed="${selected}" aria-label="${E(c.court_name+' '+time(start)+' to '+time(example.end_time)+' - '+label)}">${E(label)}</button></td>`;
      }).join('')}</tr>`;
    }).join('')}</tbody></table>`;
    const box=$('#slot-picker-grid'),layout=JSON.stringify([courts.map(c=>[c.court_id,c.court_name,c.type,c.hourly_rate]),starts]);
    box.style.setProperty('--court-count',Math.max(2,courts.length));
    if(box.dataset.layout===layout&&box.querySelector('.picker-table')){
      const template=document.createElement('template');template.innerHTML=html;
      const existing=box.querySelectorAll('[data-picker-court]');
      template.content.querySelectorAll('[data-picker-court]').forEach((next,i)=>{const button=existing[i],changed=button.getAttribute('aria-pressed')!==next.getAttribute('aria-pressed');button.className=next.className;button.disabled=next.disabled;button.textContent=next.textContent;button.setAttribute('aria-pressed',next.getAttribute('aria-pressed'));button.setAttribute('aria-label',next.getAttribute('aria-label'));if(changed)animate(button,[{transform:'scale(.96)'},{transform:'scale(1)'}]);});
    }else{box.innerHTML=html;box.dataset.layout=layout;}
    summary();
    if(focusKey)$(`[data-picker-court="${focusKey.court}"][data-picker-start="${focusKey.start}"]`)?.focus({preventScroll:true});
  }
  async function load(){
    controller?.abort();const request=controller=new AbortController();const snapshot=state;state.busy=true;
    const box=$('#slot-picker-grid');box.inert=true;box.setAttribute('aria-busy','true');
    if(!box.querySelector('.picker-table'))box.innerHTML='<p class="picker-empty">Checking live availability...</p>';
    $('#slot-picker-refresh').textContent='Updating availability...';summary();dates();
    $('#slot-picker-error').hidden=true;
    try{
      const r=await fetch('api/availability.php?date='+state.date,{signal:request.signal,cache:'no-store'}),data=await r.json();if(!r.ok)throw Error(data.error||'Could not load court times.');
      if(state!==snapshot||controller!==request||!$('#slot-picker-grid'))return;
      let removed=false;
      for(const start of state.picks.keys())if(!data.courts.find(c=>String(c.court_id)===state.court)?.slots.some(slot=>slot.start_time===start&&slot.state==='Available')){state.picks.delete(start);removed=true;}
      if(!state.picks.size)state.court='';
      state.data=data;state.max=add(today(),Number(data.policies.advance_days));state.busy=false;dates();rates();grid();
      if(removed)error('Some selected times are no longer available. Please choose another time.');
    }catch(e){if(e.name==='AbortError'||state!==snapshot||controller!==request||!$('#slot-picker-grid'))return;state.busy=false;box.innerHTML='<p class="picker-empty">Availability could not be loaded. <button type="button" data-picker-retry>Try again</button></p>';delete box.dataset.layout;error(e.message);summary();}
    finally{if(controller===request&&$('#slot-picker-grid')){box.inert=false;box.removeAttribute('aria-busy');$('#slot-picker-refresh').textContent='';}}
  }
  function error(message){$('#slot-picker-error').textContent=message;$('#slot-picker-error').hidden=false;}
  function chooseDate(date){if(date===state.date)return;if(date<today()||date>state.max){error('Choose a date within the booking window.');return;}state.date=date;state.picks.clear();state.court='';if(state.data)grid();load();}
  window.PikolSlotPicker={
    configure(options){config=options;},
    refresh(){if(state&&$('.slot-picker-dialog')&&!state.busy)return load();},
    cancel(){controller?.abort();},
    open(){
      if(!config?.page){location.assign('booking.html');return;}
      state={date:today(),max:add(today(),60),court:'',picks:new Map(),data:null,busy:true};
      config.openModal(`<section role="dialog" aria-labelledby="slot-picker-title" class="slot-picker-dialog" tabindex="-1"><header class="picker-heading"><h2 id="slot-picker-title">Book a slots</h2><button type="button" data-action="close" aria-label="Close booking" class="picker-close">&times;</button></header><ol class="picker-progress" aria-label="Booking steps"><li class="current" aria-current="step"><b>1</b><span>Slot</span></li><li><b>2</b><span>Review</span></li><li><b>3</b><span>Payment</span></li></ol><div class="picker-compact-date"><div class="picker-date-title">${dateIcon}<strong id="slot-picker-date-label" aria-live="polite"></strong></div><div class="picker-date-controls"><button type="button" id="slot-picker-previous" data-picker-day="-1" aria-label="Previous day">${arrowIcon(-1)}</button><label class="picker-calendar-control">${dateIcon}<input id="slot-picker-more-date" type="date" aria-label="Choose booking date"></label><button type="button" id="slot-picker-next" data-picker-day="1" aria-label="Next day">${arrowIcon(1)}</button></div></div><div id="slot-picker-rates" class="picker-rates" aria-label="Hourly court rates"><span>Loading rates?</span></div><p id="slot-picker-refresh" class="picker-refresh-status" role="status" aria-live="polite"></p><p id="slot-picker-error" class="picker-error" role="alert" hidden></p><div id="slot-picker-grid" class="picker-grid"></div><div class="picker-legend"><span><i class="available"></i>Available</span><span><i class="selected"></i>Selected</span><span><i class="booked"></i>Booked</span><span><i class="blocked"></i>Blocked</span></div><p class="picker-booking-note">Each slot is 1 hour. Staff confirmation and payment verification are required.</p><footer class="picker-footer" hidden><div><p id="slot-picker-summary" role="status" aria-live="polite"></p></div><strong id="slot-picker-total" class="picker-selection-total"></strong><button type="button" id="slot-picker-clear" class="picker-clear" hidden>Clear</button><button type="button" id="slot-picker-continue" class="picker-continue" disabled>Book</button></footer></section>`);
      const dialog=$('.slot-picker-dialog');
      dialog.addEventListener('click',event=>{
        const b=event.target.closest('button');if(!b)return;
        if(b.hasAttribute('data-picker-day')){chooseDate(add(state.date,Number(b.dataset.pickerDay)));return;}
        if(b.hasAttribute('data-picker-retry')){load();return;}
        if(b.id==='slot-picker-clear'){state.picks.clear();state.court='';grid();return;}
        if(b.id==='slot-picker-continue'){if(!state.busy&&state.picks.size)config.onContinue({court:state.court,date:state.date,starts:[...state.picks.keys()].sort().join(',')});return;}
        if(!b.dataset.pickerCourt||b.disabled||state.busy)return;
        $('#slot-picker-error').hidden=true;
        const court=b.dataset.pickerCourt,start=b.dataset.pickerStart;
        if(state.picks.size&&state.court!==court){error('Select slots on one court at a time. Clear your selection to change courts.');return;}
        if(state.picks.has(start)){state.picks.delete(start);if(!state.picks.size)state.court='';grid();return;}
        if(state.picks.size>=10){error('Select up to 10 one-hour slots.');return;}
        const slot=state.data.courts.find(c=>String(c.court_id)===court)?.slots.find(s=>s.start_time===start&&s.state==='Available');if(!slot)return;
        state.court=court;state.picks.set(start,slot);grid();
      });
      $('#slot-picker-more-date').addEventListener('change',event=>{if(event.target.value)chooseDate(event.target.value);});load();
    }
  };
})();
