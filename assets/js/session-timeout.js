(() => {
  'use strict';
  const endpoint=new URL('../../api/session.php',document.currentScript.src),home=new URL('../../index.html#login',document.currentScript.src);
  const embedded=window.parent!==window;
  let state=null,offset=0,warning=false,ending=false,pending=false,lastTouch=0,alarm=null,countdown=null;
  const channel=!embedded&&window.BroadcastChannel?new BroadcastChannel('pikol-session'):null;
  const now=()=>Date.now()+offset;
  const seconds=()=>Math.max(0,Math.ceil((state.expires_at*1000-now())/1000));
  async function request(action){
    const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
    try {
    const response=await fetch(endpoint,{signal:controller.signal,method:action?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:{'X-Pikol-Background':'1',...(action?{'Content-Type':'application/json','X-CSRF-Token':state?.csrf||''}:{})},...(action?{body:JSON.stringify({action})}:{})});
    const data=await response.json();if(!response.ok)throw Object.assign(Error(data.error||'Please try again.'),{status:response.status});return data;
    } finally {clearTimeout(timeout);}
  }
  function closeWarning(){if(!warning)return;warning=false;clearInterval(countdown);window.Swal?.close();}
  async function logout(broadcast=true){
    if(ending)return;ending=true;clearTimeout(alarm);closeWarning();if(broadcast)channel?.postMessage('logout');
    try{await Promise.race([request('logout'),new Promise(resolve=>setTimeout(resolve,2000))]);}catch{}finally{location.assign(home.href);}
  }
  function schedule(){
    clearTimeout(alarm);if(ending||state?.role!=='customer')return;
    if(now()>=state.expires_at*1000){void logout();return;}
    if(now()>=state.warning_at*1000){void showWarning();return;}
    alarm=setTimeout(schedule,Math.max(0,state.warning_at*1000-now()));
  }
  async function sync(){
    if(pending||ending)return;pending=true;
    try{const data=await request();const wasCustomer=state?.role==='customer';state=data;offset=data.server_time*1000-Date.now();
      if(wasCustomer&&!data.authenticated){void logout();return;}
      if(data.role!=='customer'){closeWarning();clearTimeout(alarm);return;}
      if(warning&&now()<data.warning_at*1000)closeWarning();schedule();
    }catch{if(state?.role==='customer'){if(now()>=state.expires_at*1000)void logout();else if(now()>=state.warning_at*1000)void showWarning();}}
    finally{pending=false;}
  }
  async function showWarning(){
    if(warning||ending)return;if(seconds()<=0){void logout();return;}
    if(!window.Swal){alarm=setTimeout(()=>void logout(),seconds()*1000);return;}
    warning=true;
    const result=await Swal.fire({title:'Are you still there?',html:'You have been inactive. Choose <strong>Stay signed in</strong> to continue.<br>You will be logged out in <strong data-idle-countdown></strong> seconds.',icon:'warning',showCancelButton:true,confirmButtonText:'Stay signed in',cancelButtonText:'Log out',allowOutsideClick:false,allowEscapeKey:false,timer:seconds()*1000,timerProgressBar:true,background:'#242d33',color:'#e4edef',confirmButtonColor:'#087f8c',cancelButtonColor:'#46535c',heightAuto:false,showLoaderOnConfirm:true,allowEnterKey:false,
      didOpen:()=>{const update=()=>{const text=Swal.getHtmlContainer()?.querySelector('[data-idle-countdown]');if(text)text.textContent=seconds();if(seconds()<=0)void logout();};update();countdown=setInterval(update,250);},
      preConfirm:async()=>{try{const data=await request('stay');state=data;offset=data.server_time*1000-Date.now();channel?.postMessage('stay');return true;}catch(error){if(error.status===401){void logout();return false;}Swal.showValidationMessage(error.message);return false;}},
    });
    clearInterval(countdown);if(!warning||ending)return;warning=false;
    if(result.isConfirmed)schedule();else void logout();
  }
  function activity(){
    if(embedded){if(Date.now()-lastTouch>10000){lastTouch=Date.now();parent.postMessage({type:'pikol-player-activity'},location.origin);}return;}
    if(state?.role!=='customer'||warning||ending||now()>=state.warning_at*1000||Date.now()-lastTouch<10000)return;
    lastTouch=Date.now();void request('activity').then(data=>{state=data;offset=data.server_time*1000-Date.now();schedule();}).catch(error=>{if(error.status===409||error.status===401)void sync();});
  }
  for(const event of ['pointerdown','pointermove','keydown','scroll','touchstart'])document.addEventListener(event,activity,{passive:true,capture:true});
  if(embedded)return;
  window.addEventListener('message',event=>{if(event.origin===location.origin&&event.data?.type==='pikol-player-activity'&&[...document.querySelectorAll('iframe')].some(frame=>frame.contentWindow===event.source))activity();});
  if(channel)channel.onmessage=event=>{if(event.data==='logout'&&state?.role==='customer')void logout(false);else if(event.data==='stay')void sync();};
  document.addEventListener('pikol-auth-changed',()=>void sync());
  // Check the known deadline independently of requests, including after a sleeping tab resumes.
  setInterval(()=>{if(state?.role==='customer'&&!ending)schedule();},1000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){schedule();void sync();}});window.addEventListener('focus',()=>{schedule();void sync();});
  setInterval(()=>{if(!document.hidden)void sync();},10000);void sync();
})();
