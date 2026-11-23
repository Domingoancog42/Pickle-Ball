(() => {
  const P=window.Player;let context=null;
  if(window.gsap&&window.ScrollTrigger)gsap.registerPlugin(ScrollTrigger);
  P.motion={clear(){context?.revert();context=null;},reveal(){
    if(!window.gsap||P.reduced())return;
    context=gsap.context(()=>{
      gsap.from('#player-main > .page-heading, .player-welcome',{y:14,opacity:0,duration:.45,clearProps:'opacity,transform'});
      gsap.from('.player-kpis .kpi-card',{y:10,opacity:0,duration:.4,stagger:.05,clearProps:'opacity,transform'});
      P.$$('[data-count]').forEach(el=>{const value=Number(el.dataset.count),counter={value:0};gsap.to(counter,{value,duration:.7,ease:'power2.out',onUpdate:()=>{el.textContent=Math.round(counter.value).toLocaleString();}});});
      P.$$('.player-reveal').forEach(el=>gsap.from(el,{scrollTrigger:{trigger:el,start:'top 95%',once:true},y:12,opacity:0,duration:.4,clearProps:'opacity,transform'}));
    },P.$('#player-main'));
  }};
  P.animateClose=(dialog,finish)=>{if(!window.gsap||P.reduced())return finish();gsap.to(dialog,{opacity:0,y:10,scale:.99,duration:.16,onComplete:finish});};
  matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change',event=>{if(event.matches){P.motion.clear();P.$$('[data-count]').forEach(el=>{el.textContent=Number(el.dataset.count).toLocaleString();});}});
})();
