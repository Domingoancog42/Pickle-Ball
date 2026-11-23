/* Present existing controls in one module frame; preserve their event handlers. */
(() => {
  const main=document.querySelector('#admin-main');
  function layout(){
    if(['reservations','customers','payments'].includes(window.Admin.page)||window.Admin.page.startsWith('archived-')){
      const panel=main.querySelector(':scope > section.panel'),heading=main.querySelector(':scope > .page-heading'),filters=main.querySelector(':scope > form[data-filters]');
      if(panel&&heading){panel.classList.add('table-module','table-module-primary');panel.prepend(heading);}
      if(panel&&filters){const toolbar=document.createElement('div');toolbar.className='module-table-toolbar';filters.before(toolbar);toolbar.append(filters);panel.querySelector('.page-heading').after(toolbar);}
    }
    main.querySelectorAll('.panel').forEach(panel=>{
      if(panel.querySelector('.admin-table,.table-pagination'))panel.classList.add('table-module');
      const footer=panel.querySelector('.table-pagination');if(!footer)return;
      let toolbar=panel.querySelector('.module-table-toolbar,.local-table-filters');
      if(!toolbar){toolbar=document.createElement('div');toolbar.className='module-table-toolbar';const form=panel.querySelector('#settings-filter-form');if(form){form.before(toolbar);toolbar.append(form);}else{const wrap=panel.querySelector('.table-scroll');if(wrap)wrap.before(toolbar);else footer.before(toolbar);}}
      const label=footer.querySelector('label:has(select[data-server-size],select[data-local-size],select[data-settings-size])');
      if(label){toolbar.querySelector('.module-rows-control')?.remove();label.classList.add('module-rows-control');const text=[...label.childNodes].find(n=>n.nodeType===Node.TEXT_NODE);if(text)text.textContent='Rows per page';toolbar.append(label);}
      toolbar.querySelectorAll('input:not([type=hidden]),select').forEach(field=>{
        if(field.closest('label'))return;
        const label=document.createElement('label');label.className='module-filter-field';const name=field.getAttribute('aria-label')||field.name?.replaceAll('_',' ')||'Search';
        label.append(document.createTextNode(name));field.before(label);label.append(field);
      });
    });
  }
  let queued=false;new MutationObserver(()=>{if(queued)return;queued=true;queueMicrotask(()=>{queued=false;layout();});}).observe(main,{childList:true,subtree:true});
  layout();
})();
