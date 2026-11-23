/* One outstanding long poll per visible page; payloads contain opaque change tokens only. */
(() => {
  const endpoint=new URL('../../api/live.php',document.currentScript.src);
  let cursor='',versions=null,timer=null,request=null,running=false,stopped=false,failures=0,retryDelay=250;
  const active=()=>!stopped&&!document.hidden&&navigator.onLine;
  function schedule(delay=0){clearTimeout(timer);if(active())timer=setTimeout(poll,delay);}
  async function poll(){
    if(running||!active())return;
    running=true;request=new AbortController();const timeout=setTimeout(()=>request?.abort(),26000);
    try{
      const url=new URL(endpoint);if(cursor)url.searchParams.set('since',cursor);
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','X-Pikol-Background':'1'},signal:request.signal});
      if(!response.ok)throw Error('Live updates unavailable');
      const data=await response.json();if(typeof data.version!=='string'||!data.versions)throw Error('Invalid live update');
      const changes=Object.keys(data.versions).filter(key=>!versions||versions[key]!==data.versions[key]);
      cursor=data.version;versions=data.versions;failures=0;
      retryDelay=Number.isFinite(data.retry_after_ms)?Math.max(250,Math.min(60000,data.retry_after_ms)):250;
      document.dispatchEvent(new CustomEvent('pikol:live-update',{detail:{changes,audience:data.audience}}));
    }catch(error){if(error.name!=='AbortError')failures=Math.min(failures+1,5);}
    finally{clearTimeout(timeout);request=null;running=false;schedule(failures?Math.min(30000,1000*2**failures):retryDelay);}
  }
  function pause(){clearTimeout(timer);request?.abort();}
  window.PikolLive={start(){stopped=false;schedule();},stop(){stopped=true;pause();}};
  document.addEventListener('visibilitychange',()=>{if(document.hidden)pause();else schedule();});
  window.addEventListener('offline',pause);window.addEventListener('online',()=>schedule());
  window.addEventListener('pagehide',()=>{stopped=true;pause();});window.addEventListener('pageshow',()=>{stopped=false;schedule();});

  const dirty=new WeakSet();let pending=new Set(),refreshing=false,flushTimer,refreshFailures=0;
  const workspace=()=>window.Admin||window.Player;
  const main=()=>document.querySelector(window.Admin?'#admin-main':'#player-main');
  function safe(){
    const W=workspace(),root=main();if(!W||!root||document.hidden)return false;
    if(['profile','book'].includes(W.page))return false;
    if(W.page==='settings'&&!['audit','backups'].includes(root.querySelector('.settings-tabs .active')?.dataset.tab))return false;
    if(document.querySelector('#modal-root:not([hidden]),.record-action-menu:not([hidden]),.dropdown:not([hidden])')||window.Swal?.isVisible())return false;
    if(root.querySelector('.page-loading,.player-skeleton')||root.contains(document.activeElement)&&document.activeElement.matches('input,select,textarea,[contenteditable=true]'))return false;
    return ![...root.querySelectorAll('form')].some(form=>dirty.has(form));
  }
  function queueFlush(){
    if(!pending.size||document.hidden||!navigator.onLine){clearTimeout(flushTimer);flushTimer=null;return;}
    if(flushTimer)return;
    flushTimer=setTimeout(()=>{flushTimer=null;flush();},refreshFailures?Math.min(30000,2000*2**refreshFailures):800);
  }
  async function flush(){
    if(refreshing||!pending.size||!safe()){queueFlush();return;}
    const W=workspace(),page=W.page,root=main(),scroll=window.scrollY;
    const searches=[...root.querySelectorAll('.local-table-filters input[type=search]')].map(input=>input.value);
    const tableScroll=[...root.querySelectorAll('.table-scroll')].map(table=>table.scrollLeft);
    const relevant={dashboard:['courts','reservations','payments','customers'],courts:['courts','settings'],reservations:['reservations','payments','courts','customers'],payments:['payments','reservations'],customers:['customers','reservations'],reports:['reservations','payments','courts','customers','settings'],notifications:['notifications'],calendar:['reservations','courts'],settings:['backups','audit','locks']};
    const keys=page.startsWith('archived-')?['reservations','payments','courts','customers']:relevant[page]||[];
    if(!keys.some(key=>pending.has(key))){pending.clear();return;}
    const changes=pending;pending=new Set();refreshing=true;
    W.liveRefreshing=true;W.liveCanRefresh=()=>W.page===page&&safe();
    try{
      if(W.cache){W.cache.courts=null;W.cache.catalog=null;}
      if(page==='settings')await W.liveSettingsRefresh?.([...changes]);
      else if(page==='calendar')W.calendar?.refetchEvents();
      else{
        W.motion?.clear();
        await W.views[page]();
        if(W.page===page){
          W.icons();
          root.querySelectorAll('.local-table-filters input[type=search]').forEach((input,index)=>{if(searches[index]){input.value=searches[index];input.dispatchEvent(new Event('input',{bubbles:true}));}});
          root.querySelectorAll('.table-scroll').forEach((table,index)=>{table.scrollLeft=tableScroll[index]||0;});
          if(W.charts)W.charts=W.charts.filter(chart=>{if(!chart.canvas?.isConnected){chart.destroy();return false;}return true;});
          window.scrollTo({top:scroll,behavior:'instant'});
        }
      }
      refreshFailures=0;
    }catch{refreshFailures=Math.min(refreshFailures+1,4);changes.forEach(key=>pending.add(key));}
    finally{W.liveRefreshing=false;W.liveCanRefresh=null;refreshing=false;queueFlush();}
  }
  document.addEventListener('input',event=>{
    const form=event.target.closest('form');if(form&&!form.matches('[data-filters],#reservation-filters,#settings-filter-form,#player-payment-filters'))dirty.add(form);
  });
  document.addEventListener('pikol:live-update',event=>{
    const W=workspace();if(!W)return;
    if(W.config?.guest)return;
    if(event.detail.audience!== (window.Admin?'admin':'customer')){window.PikolLive.stop();(W.loadNotifications||W.pollNotifications)().catch(()=>{});return;}
    if(W.cache){W.cache.courts=null;W.cache.catalog=null;}
    event.detail.changes.forEach(key=>pending.add(key));
    if(event.detail.changes.includes('notifications')||event.detail.changes.includes('reservations')||(window.Admin&&event.detail.changes.includes('payments')))(W.loadNotifications||W.pollNotifications)().catch(()=>{});
    queueFlush();
  });
  document.addEventListener('focusout',queueFlush);
  document.addEventListener('visibilitychange',queueFlush);window.addEventListener('online',queueFlush);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>schedule());else schedule();
})();
