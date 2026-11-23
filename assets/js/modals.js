(() => {
  const A=window.Admin||window.Player,root=A.$('#modal-root');let previousFocus=null,cleanup=null;
  A.closeModal=()=>{if(root.hidden)return;const dialog=root.firstElementChild;const finish=()=>{if(root.firstElementChild!==dialog)return;root.hidden=true;root.replaceChildren();A.$('.workspace').inert=false;if(A.$('#sidebar'))A.$('#sidebar').inert=false;document.body.style.overflow='';cleanup?.();cleanup=null;previousFocus?.focus({preventScroll:true});};if(A.animateClose)A.animateClose(dialog,finish);else finish();};
  A.modal=(title,body,options={})=>{
    if(root.hidden)previousFocus=document.activeElement;
    cleanup?.();cleanup=null;
    root.innerHTML=`<section class="admin-dialog ${options.wide?'wide':''}" role="dialog" aria-modal="true" aria-labelledby="dialog-title"><header class="dialog-header"><div><h2 id="dialog-title">${A.e(title)}</h2>${options.subtitle?`<p>${A.e(options.subtitle)}</p>`:''}</div><button class="icon-button" data-action="close-modal" aria-label="Close dialog">${A.icon('x')}</button></header>${body}</section>`;
    root.hidden=false;root.scrollTop=0;document.body.style.overflow='hidden';A.$('.workspace').inert=true;if(A.$('#sidebar'))A.$('#sidebar').inert=true;A.icons();A.animate('.admin-dialog',{y:16,duration:.25});
    requestAnimationFrame(()=>A.$('[autofocus],input:not([type=hidden]),select,textarea,button',root)?.focus({preventScroll:true}));
    return A.$('.admin-dialog',root);
  };
  A.formModal=(title,fields,onSubmit,options={})=>{
    const dialog=A.modal(title,`<form id="dialog-form"><div class="dialog-body"><div class="form-error" role="alert" hidden></div>${fields}</div><div class="dialog-actions"><button type="button" class="secondary-button" data-action="close-modal">Cancel</button><button class="${options.danger?'danger-button':'primary-button'}" type="submit">${A.e(options.label||'Save changes')}</button></div></form>`,options);
    A.$('form',dialog).addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget;const error=A.$('.form-error',form);error.hidden=true;const button=A.$('[type=submit]',form);button.disabled=true;try{const decision=await Swal.fire({title:'Confirm action',text:title,icon:options.danger?'warning':'question',showCancelButton:true,confirmButtonText:options.label||'Save changes',cancelButtonText:'Go back',focusCancel:true,background:'#242d33',color:'#e4edef',confirmButtonColor:options.danger?'#b94156':'#087f8c',cancelButtonColor:'#46535c',heightAuto:false,customClass:{container:'workspace-confirmation'},...(A.reduced()?{showClass:{popup:''},hideClass:{popup:''}}:{})});if(!decision.isConfirmed)return;await onSubmit(new FormData(form),form);if(!options.keepOpen)A.closeModal();}catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}finally{button.disabled=false;}});
    return dialog;
  };
  A.confirm=async(title,copy,onConfirm,options={})=>{
    const result=await Swal.fire({title,text:copy,icon:options.danger?'warning':'question',showCancelButton:true,confirmButtonText:options.label||'Confirm',cancelButtonText:'Keep unchanged',focusCancel:true,showLoaderOnConfirm:true,allowOutsideClick:()=>!Swal.isLoading(),allowEscapeKey:()=>!Swal.isLoading(),input:options.reason?'textarea':undefined,inputLabel:options.reason?'Reason':undefined,inputPlaceholder:options.reason?'Explain the reason for this change...':undefined,inputAttributes:{maxlength:'1000'},background:'#242d33',color:'#e4edef',confirmButtonColor:options.danger?'#b94156':'#087f8c',cancelButtonColor:'#46535c',heightAuto:false,customClass:{container:'workspace-confirmation'},...(A.reduced()?{showClass:{popup:''},hideClass:{popup:''}}:{}),preConfirm:async value=>{
      if(options.reason&&!String(value||'').trim()){Swal.showValidationMessage('Please enter a reason.');return false;}
      try{await onConfirm(String(value||'').trim());return true;}catch(error){Swal.showValidationMessage(error.message);return false;}
    }});
    if(result.isConfirmed)A.closeModal();return result.isConfirmed;
  };
  A.actions['close-modal']=A.closeModal;
  root.addEventListener('click',event=>{if(event.target===root)A.closeModal();});
  document.addEventListener('keydown',event=>{
    if(window.Swal?.isVisible())return;
    if(event.key==='Escape'){if(!root.hidden)A.closeModal();else{A.$('#profile-menu').hidden=true;A.$('#notification-menu').hidden=true;document.body.classList.remove('sidebar-open');A.$('.drawer-backdrop').hidden=true;}}
    if(event.key==='Tab'&&!root.hidden){const focusable=A.$$('button:not([disabled]),a[href],input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled])',root).filter(el=>el.getClientRects().length);const first=focusable[0],last=focusable.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
  });
})();
