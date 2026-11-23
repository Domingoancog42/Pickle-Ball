/* Review requests against the same court lock used by both booking interfaces. */
(() => {
  const A=window.Admin,original=A.views.reservations;
  A.views.reservations=async()=>{
    await original();if(A.page!=='reservations')return;const result=await A.api('reschedule-requests');if(A.page!=='reservations'||!result.items.length)return;
    A.cache.rescheduleRequests=result.items;const panel=document.createElement('section');panel.className='panel';panel.style.marginBottom='20px';
    panel.innerHTML=`<div class="panel-heading"><div><h2>Reschedule requests</h2><p>The original court time stays reserved until you approve a change.</p></div><span class="status-badge status-pending">${result.items.length} awaiting review</span></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Booking</th><th>Customer</th><th>Court</th><th>Requested time</th><th>Action</th></tr></thead><tbody>${result.items.map(r=>`<tr><td>${A.e(r.reference_no)}</td><td>${A.e(r.full_name)}</td><td>${A.e(r.court_name)}</td><td>${A.date(r.requested_date)} · ${A.time(r.requested_start)} – ${A.time(r.requested_end)}</td><td>${A.button('review-reschedule','Review request','calendar-clock','secondary-button',`data-id="${r.request_id}"`)}</td></tr>`).join('')}</tbody></table></div>`;
    A.$('.page-heading',A.$('#admin-main')).after(panel);
  };
  A.actions['review-reschedule']=button=>{
    const r=A.cache.rescheduleRequests.find(r=>String(r.request_id)===button.dataset.id);if(!r)return;
    A.formModal('Review reschedule request',`<p><strong>${A.e(r.reference_no)} · ${A.e(r.full_name)}</strong></p><div class="helper-note section-spacer">Current: ${A.date(r.booking_date)} · ${A.time(r.start_time)} – ${A.time(r.end_time)}<br>Requested: ${A.date(r.requested_date)} · ${A.time(r.requested_start)} – ${A.time(r.requested_end)}</div><p class="muted section-spacer">Player reason: ${A.e(r.customer_reason)}</p><div class="form-grid section-spacer"><label class="full">Decision<select name="decision"><option>Approved</option><option>Rejected</option></select></label><label class="full">Response to player<textarea name="response" required maxlength="1000" placeholder="Explain the outcome…"></textarea></label></div><div class="helper-note section-spacer">Availability is rechecked under a court lock. Approval keeps the original duration, fee, booking status, and payment records.</div>`,async data=>{await A.api('reschedule-requests',{request_id:r.request_id,...Object.fromEntries(data)},'POST');A.toast('Reschedule request reviewed.');await A.reload();},{label:'Save decision'});
  };
})();
