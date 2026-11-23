(() => {
  const A=window.Admin,E=A.e;
  A.reservationCustomerField=()=>`<div class="full reservation-customer-picker"><div class="customer-picker-heading"><label for="reservation-customer-name">Customer name</label><button type="button" class="secondary-button" id="customer-entry-mode" aria-pressed="false">Custom / Walk-in</button></div><div class="customer-picker-anchor"><input id="reservation-customer-name" name="full_name" maxlength="100" autocomplete="off" placeholder="Select an active customer" readonly role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="reservation-customer-options"><button type="button" class="customer-picker-toggle" aria-label="Open customer list" tabindex="-1">${A.icon('chevron-down')}</button><div class="customer-picker-popup" hidden><input type="search" class="customer-picker-search" placeholder="Search by name, email or phone" aria-label="Search active customers" autocomplete="off" role="combobox" aria-expanded="true" aria-controls="reservation-customer-options" aria-autocomplete="list"><div class="customer-picker-status" role="status"></div><div id="reservation-customer-options" role="listbox" aria-label="Active customers"></div><button type="button" class="customer-picker-more" hidden>Load more customers</button></div></div><small class="customer-picker-help">Select a player to fill their contact details, or choose Custom / Walk-in.</small></div>`;
  A.setupReservationCustomer=dialog=>{
    const form=dialog.querySelector('form'),root=dialog.querySelector('.reservation-customer-picker'),name=form.elements.full_name,email=form.elements.email,phone=form.elements.phone;
    const toggle=root.querySelector('.customer-picker-toggle'),mode=root.querySelector('#customer-entry-mode'),popup=root.querySelector('.customer-picker-popup'),search=root.querySelector('.customer-picker-search'),list=root.querySelector('[role=listbox]'),status=root.querySelector('[role=status]'),more=root.querySelector('.customer-picker-more'),help=root.querySelector('.customer-picker-help');
    let custom=false,selected=null,options=[],sequence=0,timer,page=1,pages=1,active=-1;
    const draft={name:'',email:'',phone:''};
    const close=()=>{popup.hidden=true;name.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');active=-1;};
    const choose=customer=>{selected=customer;name.value=customer.full_name;email.value=customer.email;phone.value=customer.phone||'';close();name.focus();};
    const render=()=>{list.innerHTML=options.map((customer,index)=>`<button type="button" tabindex="-1" class="customer-picker-option" role="option" aria-selected="${selected?.user_id===customer.user_id}" id="customer-choice-${index}" data-index="${index}"><strong>${E(customer.full_name)}</strong><span>${E(customer.email)}${customer.phone?' &middot; '+E(customer.phone):''}</span></button>`).join('');};
    const lookup=async(next=1)=>{
      const request=++sequence;status.textContent='Loading customers...';more.hidden=true;
      try{const data=await A.api('customers',{search:search.value.trim(),status:'active',page:next,per_page:20});if(request!==sequence||!dialog.isConnected||custom)return;
        delete more.dataset.retry;options=next===1?data.items:options.concat(data.items);page=data.page;pages=data.pages;active=-1;search.removeAttribute('aria-activedescendant');render();status.textContent=options.length?`${data.total} active ${data.total===1?'customer':'customers'}`:'No matching customers. Try another search or choose Custom / Walk-in.';more.hidden=page>=pages;more.textContent='Load more customers';
      }catch(error){if(request!==sequence||!dialog.isConnected)return;status.textContent=error.message;more.textContent='Try again';more.hidden=false;more.dataset.retry='true';}
    };
    const open=()=>{if(custom)return;popup.hidden=false;name.setAttribute('aria-expanded','true');search.focus();lookup();};
    name.addEventListener('click',()=>{if(!custom){if(popup.hidden)open();else close();}});toggle.onclick=()=>popup.hidden?open():close();
    mode.onclick=()=>{
      if(custom){Object.assign(draft,{name:name.value,email:email.value,phone:phone.value});}
      custom=!custom;++sequence;clearTimeout(timer);close();mode.setAttribute('aria-pressed',String(custom));mode.textContent=custom?'Select existing customer':'Custom / Walk-in';name.readOnly=!custom;email.readOnly=!custom;phone.readOnly=!custom;name.required=custom;toggle.hidden=custom;
      if(custom){name.removeAttribute('role');name.removeAttribute('aria-controls');name.removeAttribute('aria-haspopup');name.placeholder='Enter walk-in customer name';name.value=draft.name;email.value=draft.email;phone.value=draft.phone;help.textContent='Enter the walk-in customer details below. Email is required to create their customer record.';name.focus();}
      else{name.setAttribute('role','combobox');name.setAttribute('aria-controls','reservation-customer-options');name.setAttribute('aria-haspopup','listbox');name.placeholder='Select an active customer';name.value=selected?.full_name||'';email.value=selected?.email||'';phone.value=selected?.phone||'';help.textContent='Select a player to fill their contact details, or choose Custom / Walk-in.';open();}
      form.dispatchEvent(new Event('reservation-customer-mode'));
    };
    search.addEventListener('input',()=>{clearTimeout(timer);++sequence;options=[];list.replaceChildren();status.textContent='Searching customers...';more.hidden=true;timer=setTimeout(()=>lookup(),180);});
    more.onclick=()=>{search.focus({preventScroll:true});const retry=more.dataset.retry==='true';delete more.dataset.retry;lookup(retry?(options.length?page:1):page+1);};
    // Keep focus in the search field until the mouse click selects its option.
    list.addEventListener('mousedown',event=>{if(event.target.closest('[data-index]'))event.preventDefault();});
    list.addEventListener('click',event=>{const option=event.target.closest('[data-index]');if(option)choose(options[Number(option.dataset.index)]);});
    name.addEventListener('keydown',event=>{if(!custom&&['ArrowDown','ArrowUp','Enter',' '].includes(event.key)){event.preventDefault();open();}});
    root.addEventListener('keydown',event=>{
      if(popup.hidden)return;
      if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();name.focus();return;}
      if(event.target!==search)return;
      if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(!options.length)return;active=(active+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;list.querySelectorAll('[role=option]').forEach((option,index)=>option.classList.toggle('is-highlighted',index===active));const option=list.children[active];search.setAttribute('aria-activedescendant',option.id);option.scrollIntoView({block:'nearest'});}
      if(event.key==='Enter'){event.preventDefault();if(active>=0&&options[active])choose(options[active]);}
    });
    dialog.addEventListener('click',event=>{if(!root.contains(event.target))close();});root.addEventListener('focusout',event=>{if(event.relatedTarget){if(!root.contains(event.relatedTarget))close();}else requestAnimationFrame(()=>{if(!root.contains(document.activeElement))close();});});
    phone.addEventListener('input',()=>{phone.value=phone.value.replace(/[^0-9]/g,'').slice(0,11);});
    email.readOnly=true;phone.readOnly=true;
    return data=>{data.set('customer_mode',custom?'walk-in':'existing');if(!custom&&!selected)throw Error('Select an existing customer or choose Custom / Walk-in.');if(!custom)data.set('user_id',selected.user_id);else data.delete('user_id');};
  };
})();
