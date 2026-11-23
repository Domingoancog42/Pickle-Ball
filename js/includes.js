/* Load the editable HTML sections before the application attaches its controls. */
(() => {
  'use strict';

  function fail(error) {
    const status = document.getElementById('site-loading');
    const filePreview = location.protocol === 'file:';
    status.replaceChildren();
    status.setAttribute('role', 'alert');
    const title = document.createElement('p');
    title.textContent = filePreview ? 'Open Pikol Castle through your local server.' : 'The page could not be loaded.';
    const detail = document.createElement('p');
    detail.className = 'text-sm text-muted-foreground';
    detail.textContent = filePreview ? 'Start Apache in XAMPP, then use the link below.' : 'Please try loading the page again.';
    const action = document.createElement(filePreview ? 'a' : 'button');
    action.className = 'btn btn-primary';
    if (filePreview) {
      action.href = 'http://localhost/pikolcastle/';
      action.textContent = 'Open Pikol Castle';
    } else {
      action.type = 'button';
      action.textContent = 'Try Again';
      action.addEventListener('click', () => location.reload());
    }
    status.append(title, detail, action);
    status.hidden = false;
    document.getElementById('page-shell').hidden = true;
    document.body.setAttribute('aria-busy', 'false');
    document.documentElement.dataset.siteReady = 'false';
    if (!filePreview) console.error('Pikol Castle could not load its sections:', error);
    return false;
  }

  async function load() {
    try {
      if (location.protocol === 'file:') return fail();
      const slots = [...document.querySelectorAll('template[data-include]')];
      const parts = await Promise.all(slots.map(async slot => {
        const path = slot.dataset.include;
        const response = await fetch(path, {cache: 'no-cache'});
        if (!response.ok) throw new Error(`${path}: HTTP ${response.status}`);
        // A complete document gives Live Server one </body> injection point.
        // Without it, Live Server injects before every </svg> and truncates the
        // response. Import only the editable body, without its reload script.
        const section = new DOMParser().parseFromString(await response.text(), 'text/html');
        section.body.querySelectorAll('script').forEach(script => script.remove());
        const content = document.createDocumentFragment();
        content.append(...section.body.childNodes);
        return {slot, content};
      }));
      // Insert together to avoid a partly initialized page if a section is missing.
      parts.forEach(({slot, content}) => slot.replaceWith(content));
      return true;
    } catch (error) {
      return fail(error);
    }
  }

  function finish() {
    document.getElementById('page-shell').hidden = false;
    document.getElementById('site-loading').hidden = true;
    document.body.setAttribute('aria-busy', 'false');
    document.documentElement.dataset.siteReady = 'true';
    // A direct URL such as /pikolcastle/#booking also works after the sections load.
    requestAnimationFrame(() => {
      let id;
      try { id = decodeURIComponent(location.hash.slice(1)); } catch { return; }
      if (id) document.getElementById(id)?.scrollIntoView({behavior: 'instant', block: 'start'});
    });
  }

  window.PikolIncludes = {ready: load(), finish, fail};
})();
