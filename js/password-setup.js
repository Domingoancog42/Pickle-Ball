(() => {
  const form=document.querySelector('#password-setup-form'),error=form.querySelector('.form-error'),button=form.querySelector('[type=submit]');
  const password=form.querySelector('#new-password'),symbol=/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/;
  const policy=JSON.parse(document.querySelector('#password-policy').textContent),checklist=document.querySelector('#password-rules');
  form.querySelectorAll('[autocomplete=new-password]').forEach(el=>{el.minLength=policy.minimum;el.maxLength=policy.maximum;});
  const validate=()=>{
    const value=password.value,lower=value.toLowerCase(),email=policy.email.toLowerCase(),username=email.split('@')[0];
    const rules={length:Array.from(value).length>=policy.minimum&&Array.from(value).length<=policy.maximum&&!value.includes(String.fromCharCode(0)),common:!policy.common.includes(lower)};
    checklist.hidden=value.length===0;let met=0;
    Object.entries(rules).forEach(([key,valid])=>{const item=checklist.querySelector('[data-password-rule="'+key+'"]');item.classList.toggle('is-valid',valid);item.querySelector('.rule-state').textContent=valid?'Met':'Not met';if(valid)met++;});
    checklist.querySelector('.password-progress').textContent=met===2?'All requirements met.':met+' of 2 requirements met.';
    const failed=Object.keys(rules).find(key=>!rules[key]);password.setCustomValidity(value&&failed?policy.labels[failed]+'.':'');return !failed;
  };
  password.addEventListener('input',validate);
  form.querySelectorAll('[data-password-toggle]').forEach(toggle=>toggle.addEventListener('click',()=>{
    const input=document.getElementById(toggle.getAttribute('aria-controls')),visible=input.type==='password';
    input.type=visible?'text':'password';toggle.setAttribute('aria-pressed',String(visible));toggle.setAttribute('aria-label',visible?'Hide password':'Show password');
    toggle.querySelector('[data-eye-slash]').hidden=!visible;
  }));
  form.addEventListener('submit',async event=>{
    event.preventDefault();error.hidden=true;const data=new FormData(form);
    if(!validate()){password.reportValidity();return;}
    if(data.get('new_password')!==data.get('confirm_password')){error.textContent='The passwords must match.';error.hidden=false;return;}
    button.disabled=true;
    try{
      const response=await fetch('api/auth.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':data.get('csrf')},body:JSON.stringify({action:'change_temporary_password',new_password:data.get('new_password'),confirm_password:data.get('confirm_password')})});
      const result=await response.json();if(!response.ok)throw Error(result.error||'Your password could not be saved.');
      location.assign(result.redirect);
    }catch(e){error.textContent=e.message;error.hidden=false;}finally{button.disabled=false;}
  });
})();
