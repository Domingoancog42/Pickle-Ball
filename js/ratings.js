(() => {
  'use strict';
  const base=new URL('../',document.currentScript.src);
  const endpoint=path=>new URL(path,base).href;
  const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const star='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.3.9-4.6 4.4 1.1 6.3-5.6-3-5.6 3 1.1-6.3L3 9.6l6.2-.9Z"/></svg>';
  const initials=name=>String(name??'').trim().split(/\s+/u).filter(Boolean).map(part=>Array.from(part)[0]).slice(0,2).join('').toLocaleUpperCase();
  const avatar=review=>{const path=String(review.avatar_path||'');return /^uploads\/avatars\/[a-zA-Z0-9._-]+$/.test(path)?`<img src="${escape(endpoint(path))}" alt="" loading="lazy" decoding="async">`:'';};
  const quote='<svg class="review-quote" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21c3 0 7-4 7-11V3H3v7h4c0 5-2 7-4 7zm12 0c3 0 7-4 7-11V3h-7v7h4c0 5-2 7-4 7z"/></svg>';
  const stars=score=>`<span class="rating-stars" aria-label="${score} out of 5 stars">${Array.from({length:5},(_,i)=>`<span class="${i<Math.round(score)?'filled':''}">${star}</span>`).join('')}</span>`;
  async function request(path,options={}) {
    const response=await fetch(endpoint(path),{credentials:'same-origin',...options});
    const data=await response.json();if(!response.ok)throw Error(data.error||'Please try again.');return data;
  }
  let reviewPage=1,reviewRequest=0;
  const arrow=direction=>`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="${direction==='next'?'m9 5 7 7-7 7':'m15 5-7 7 7 7'}"/></svg>`;
  async function refresh(requestedPage=reviewPage,fromNavigation=false) {
    const root=document.getElementById('player-reviews');if(!root)return;
    const requestId=++reviewRequest;
    const focused=document.activeElement?.dataset.reviewDirection;
    root.querySelectorAll('.review-arrow').forEach(button=>button.disabled=true);
    try {
      const data=await request('api/ratings.php?page='+requestedPage);
      if(requestId!==reviewRequest)return;
      reviewPage=data.page;
      const controls=data.pages>1?`<nav class="review-pagination" aria-label="Player rating pages"><span class="review-page-status">${(data.page-1)*4+1}&ndash;${Math.min(data.page*4,data.total)} of ${data.total} reviews</span><button type="button" class="review-arrow" data-review-direction="previous" aria-label="Previous ratings" ${data.page<=1?'disabled':''}>${arrow('previous')}</button><button type="button" class="review-arrow" data-review-direction="next" aria-label="Next ratings" ${data.page>=data.pages?'disabled':''}>${arrow('next')}</button></nav>`:'';
      root.innerHTML=`<div class="reviews-summary">${data.total?`${stars(data.average)}<span>${data.average.toFixed(1)} &middot; ${data.total} ${data.total===1?'review':'reviews'}</span>`:'<p>No ratings yet. Be the first to share your experience from your player profile menu.</p>'}</div><div class="reviews-list">${data.reviews.map(r=>`<article class="player-review">${quote}${stars(r.score)}${r.comment?`<blockquote>&ldquo;${escape(r.comment)}&rdquo;</blockquote>`:'<p class="review-no-comment">Rated their experience at Pikol Castle.</p>'}<footer><span class="review-avatar" aria-hidden="true"><span>${escape(initials(r.name))}</span>${avatar(r)}</span><div class="review-author"><strong>${escape(r.name)}</strong><time datetime="${escape(r.updated_at.slice(0,10))}">${escape(new Date(r.updated_at.slice(0,10)+'T12:00:00').toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'}))}</time></div></footer></article>`).join('')}</div>${controls}`;
      const move=direction=>{const next=data.page+(direction==='next'?1:-1);if(next>=1&&next<=data.pages)refresh(next,true);};
      root.querySelectorAll('.review-arrow').forEach(button=>button.onclick=()=>move(button.dataset.reviewDirection));
      const list=root.querySelector('.reviews-list');let touchStart=null;
      list.addEventListener('touchstart',event=>{touchStart=event.touches.length===1?{x:event.touches[0].clientX,y:event.touches[0].clientY}:null;},{passive:true});
      list.addEventListener('touchend',event=>{if(!touchStart)return;const touch=event.changedTouches[0],dx=touch.clientX-touchStart.x,dy=touch.clientY-touchStart.y;touchStart=null;if(Math.abs(dx)>60&&Math.abs(dx)>Math.abs(dy)*1.5)move(dx<0?'next':'previous');},{passive:true});
      list.addEventListener('touchcancel',()=>touchStart=null,{passive:true});
      if(fromNavigation&&focused){const button=root.querySelector(`[data-review-direction="${focused}"]:not(:disabled)`)||root.querySelector('.review-arrow:not(:disabled)');button?.focus({preventScroll:true});}
      root.querySelectorAll('.review-avatar img').forEach(image=>image.addEventListener('error',()=>image.remove(),{once:true}));
    } catch(error) {if(requestId!==reviewRequest)return;root.innerHTML=`<p role="status">${escape(error.message)}</p><button type="button" class="rating-button" data-retry-ratings>Try again</button>`;root.querySelector('button').onclick=()=>refresh(requestedPage,fromNavigation);}
  }
  async function open(csrf) {
    if(document.querySelector('.rating-dialog'))return;
    const dialog=document.createElement('dialog');dialog.className='rating-dialog';dialog.setAttribute('aria-labelledby','rating-title');
    dialog.innerHTML=`<button type="button" class="rating-close" aria-label="Close rating form">Close</button><h2 id="rating-title">Rate your experience</h2><p class="rating-intro">How was your time at Pikol Castle?</p><p role="status" class="rating-status">Loading your rating…</p><form hidden><fieldset><legend>Your rating</legend><div class="rating-options">${[1,2,3,4,5].map(n=>`<label><input type="radio" name="score" value="${n}" required><span>${star}<span>${n}</span></span><span class="rating-sr">${n} ${n===1?'star':'stars'}</span></label>`).join('')}</div></fieldset><label class="rating-comment" for="rating-comment">Your experience <span>(optional)</span></label><textarea id="rating-comment" name="comment" maxlength="1000" rows="4" placeholder="Tell other players about your visit"></textarea><p class="rating-note">Your profile photo, first name, last initial, rating and comment will appear on the homepage. You can update your rating anytime.</p><p class="rating-error" role="alert"></p><button class="rating-button" type="submit">Save rating</button></form>`;
    const previous=document.activeElement;document.body.append(dialog);dialog.showModal();
    dialog.addEventListener('close',()=>{dialog.remove();previous?.focus();});dialog.querySelector('.rating-close').onclick=()=>dialog.close();
    dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
    const form=dialog.querySelector('form'),status=dialog.querySelector('.rating-status');
    try {const {rating}=await request('api/customer/ratings.php');if(!dialog.isConnected)return;if(rating){form.elements.score.value=rating.score;form.elements.comment.value=rating.comment;}status.hidden=true;form.hidden=false;}
    catch(error){status.textContent=error.message;return;}
    form.addEventListener('submit',async event=>{
      event.preventDefault();event.stopPropagation();const button=form.querySelector('[type=submit]'),error=form.querySelector('.rating-error');button.disabled=true;button.textContent='Saving…';error.textContent='';
      try {await request('api/customer/ratings.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({score:Number(form.elements.score.value),comment:form.elements.comment.value})});refresh();form.hidden=true;status.hidden=false;status.textContent='Thank you! Your rating is now on the homepage.';}
      catch(problem){error.textContent=problem.message;}
      finally{button.disabled=false;button.textContent='Save rating';}
    });
  }
  window.PikolRatings={open,refresh};
  if(window.Player)window.Player.actions['rate-castle']=()=>open(window.Player.config.csrf);
  (window.PikolIncludes?.ready||Promise.resolve()).then(()=>refresh());
  document.addEventListener('pikol:live-update',event=>{if(event.detail.changes?.some(key=>['ratings','players'].includes(key)))refresh();});
})();
