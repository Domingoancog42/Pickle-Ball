/* Shared record actions, portalled outside scrollable tables to avoid clipping. */
(() => {
  const A=window.Admin||window.Player;
  let active=null,sequence=0;
  const close=(focus=false)=>{
    if(!active)return;
    const {group,trigger,panel}=active;active=null;
    trigger.setAttribute('aria-expanded','false');panel.hidden=true;
    if(group.isConnected)group.append(panel);else panel.remove();
    if(focus&&trigger.isConnected)trigger.focus();
  };
  const position=()=>{
    if(!active)return;
    const {trigger,panel}=active,r=trigger.getBoundingClientRect();
    const width=Math.min(230,innerWidth-24);panel.style.width=width+'px';
    panel.style.left=Math.max(12,Math.min(r.right-width,innerWidth-width-12))+'px';
    const height=panel.offsetHeight;
    panel.style.top=Math.max(12,r.bottom+6+height>innerHeight-12?r.top-height-6:r.bottom+6)+'px';
    panel.style.maxHeight=Math.max(120,innerHeight-24)+'px';
  };
  const enhance=()=>{
    if(active&&!active.trigger.isConnected)close();
    const selectors='.table-actions,.admin-table td>.button-row,.court-content>.button-row,.reservation-copy>.button-row,.admin-table tbody tr>td:last-child';
    document.querySelectorAll(selectors).forEach(group=>{
      if(group.hasAttribute('data-inline-actions')||group.dataset.actionMenuReady||group.closest('.record-action-menu')||group.querySelector('.table-actions,.button-row,.record-action-trigger,.reservation-proof-thumbnail'))return;
      const items=[...group.children].filter(el=>el.matches('button[data-action],a[href]'));
      if(!items.length)return;
      group.dataset.actionMenuReady='true';group.classList.add('record-actions');
      const panel=document.createElement('div');panel.className='record-action-menu';panel.hidden=true;panel.id='record-actions-'+(++sequence);panel.setAttribute('role','menu');panel.setAttribute('aria-label','Record actions');
      const trigger=document.createElement('button');trigger.type='button';trigger.className='icon-button record-action-trigger';trigger.setAttribute('aria-label','More actions');trigger.setAttribute('aria-haspopup','menu');trigger.setAttribute('aria-expanded','false');trigger.setAttribute('aria-controls',panel.id);trigger.innerHTML=window.Admin?'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>':'<span aria-hidden="true">&bull;&bull;&bull;</span>';
      items.forEach(item=>{
        const label=item.textContent.trim()||item.title||item.getAttribute('aria-label')||'View details';
        if(!item.textContent.trim()){const span=document.createElement('span');span.textContent=label;item.append(span);}
        item.classList.add('record-action-item');item.setAttribute('role','menuitem');panel.append(item);
      });
      group.append(trigger,panel);
      trigger.addEventListener('click',event=>{
        event.stopPropagation();const wasOpen=active?.trigger===trigger;close();if(wasOpen)return;
        active={group,trigger,panel};(trigger.closest('.admin-dialog')||document.body).append(panel);panel.hidden=false;trigger.setAttribute('aria-expanded','true');position();panel.querySelector('button,a')?.focus({preventScroll:true});
      });
    });
  };
  document.addEventListener('click',event=>{if(!active)return;if(active.panel.contains(event.target)){if(event.target.closest('button,a'))queueMicrotask(()=>close());}else if(!active.trigger.contains(event.target))close();});
  document.addEventListener('keydown',event=>{
    if(!active)return;
    if(event.key==='Escape'){event.preventDefault();event.stopImmediatePropagation();close(true);return;}
    if(event.key==='Tab'){close();return;}
    if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
      event.preventDefault();const items=[...active.panel.querySelectorAll('button:not([disabled]),a[href]')],index=items.indexOf(document.activeElement);
      items[event.key==='Home'?0:event.key==='End'?items.length-1:(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length]?.focus();
    }
  },true);
  addEventListener('resize',()=>close());addEventListener('scroll',event=>{if(active&&!active.panel.contains(event.target))position();},true);
  let scheduled=false;new MutationObserver(()=>{if(!scheduled){scheduled=true;queueMicrotask(()=>{scheduled=false;enhance();});}}).observe(document.body,{childList:true,subtree:true});enhance();
})();
