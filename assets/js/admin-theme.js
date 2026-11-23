/* Admin-only preference, applied in the head before the workspace is painted. */
(() => {
  const root=document.documentElement,key='pikol.admin.theme';
  function apply(theme){
    const light=theme==='light';root.dataset.adminTheme=light?'light':'dark';
    root.classList.toggle('dark',!light);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content',light?'#f3f6f7':'#181C20');
    const button=document.querySelector('#admin-theme-toggle');
    if(button){button.setAttribute('aria-checked',String(!light));button.title=light?'Switch to dark mode':'Switch to light mode';}
    const A=window.Admin;
    if(window.Chart){Chart.defaults.color=light?'#52656f':'#91a8b3';Chart.defaults.borderColor=light?'#d4dfe3':'#3b474f66';}
    A?.charts?.forEach(chart=>{
      for(const scale of Object.values(chart.options.scales||{})){scale.ticks.color=light?'#52656f':'#91a8b3';if(scale.grid.display!==false)scale.grid.color=light?'#d4dfe3':'#3b474f66';}
      chart.update('none');
    });
  }
  let saved='dark';try{saved=localStorage.getItem(key)||'dark';}catch{}
  apply(saved);
  document.addEventListener('DOMContentLoaded',()=>apply(root.dataset.adminTheme));
  document.addEventListener('click',event=>{
    if(!event.target.closest('#admin-theme-toggle'))return;
    const theme=root.dataset.adminTheme==='light'?'dark':'light';apply(theme);
    try{localStorage.setItem(key,theme);}catch{}
  });
  window.addEventListener('storage',event=>{if(event.key===key)apply(event.newValue||'dark');});
})();
