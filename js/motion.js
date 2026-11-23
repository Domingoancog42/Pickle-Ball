/* Anime.js motion layer. The page remains usable when animation is unavailable. */
(() => {
  'use strict';

  const engine = window.anime;
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const active = new Map();
  const panelVersions = new WeakMap();
  const pending = new Map();
  let observer;
  let initialized = false;
  const enabled = () => Boolean(engine) && !preference.matches;

  function cancel(element) {
    const record = active.get(element);
    if (!record) return;
    record.animation?.cancel();
    record.finish();
  }

  function cancelTree(root) {
    for (const element of active.keys()) {
      if (element === root || root.contains(element)) cancel(element);
    }
  }

  // Restore inline styles so animations never override utility classes or hover states.
  function run(element, properties, options = {}) {
    if (!element) return Promise.resolve();
    cancel(element);
    if (!enabled() || !element.isConnected) return Promise.resolve();
    const names = ['opacity', 'transform', 'height', 'overflow', 'transition', 'will-change'];
    const original = names.map(name => [name, element.style.getPropertyValue(name), element.style.getPropertyPriority(name)]);
    element.style.transition = 'none';
    element.style.willChange = 'opacity, transform';
    if (options.clip) element.style.overflow = 'hidden';
    return new Promise(resolve => {
      const record = {
        animation: null,
        finish() {
          if (active.get(element) !== record) return;
          active.delete(element);
          original.forEach(([name, value, priority]) => {
            if (value) element.style.setProperty(name, value, priority);
            else element.style.removeProperty(name);
          });
          resolve();
        }
      };
      active.set(element, record);
      record.animation = engine.animate(element, {
        ...properties,
        duration: options.duration ?? 600,
        delay: options.delay ?? 0,
        ease: options.ease ?? 'out(4)',
        onComplete: record.finish
      });
    });
  }

  function enter(targets, options = {}) {
    const list = targets instanceof Element ? [targets] : Array.from(targets || []);
    const stagger = engine?.stagger(options.stagger ?? 65, {start: options.delay ?? 0});
    return Promise.all(list.map((element, index) => run(element, {
      opacity: [0, 1], y: [options.distance ?? 24, 0],
      ...(options.scale ? {scale: [options.scale, 1]} : {})
    }, {...options, delay: stagger ? stagger(element, index, list.length) : 0})));
  }

  function reveal(element) {
    const options = pending.get(element);
    if (!options) return;
    pending.delete(element);
    observer?.unobserve(element);
    element.dataset.reveal = 'entering';
    enter(element, options).then(() => { element.dataset.reveal = 'visible'; });
  }

  function observe(element, options = {}) {
    if (!element || pending.has(element) || !enabled() || !observer) return;
    element.dataset.reveal = 'pending';
    pending.set(element, options);
    observer.observe(element);
  }

  // A version per panel prevents a quick open/close/open from hiding new content.
  function panel(element, open, options = {}) {
    if (!element) return Promise.resolve();
    const version = (panelVersions.get(element) || 0) + 1;
    panelVersions.set(element, version);
    const start = element.hidden ? 0 : element.getBoundingClientRect().height;
    const opacity = element.hidden ? 0 : Number.parseFloat(getComputedStyle(element).opacity);
    cancel(element);
    if (!open && element.hidden) return Promise.resolve();
    if (open && options.content !== undefined) element.innerHTML = options.content;
    element.hidden = false;
    element.inert = !open;
    element.setAttribute('aria-hidden', String(!open));
    const end = open ? element.scrollHeight : 0;
    return run(element, {height: [start, end], opacity: [opacity, open ? 1 : 0]}, {
      clip: true, duration: open ? 360 : 220, ease: 'out(3)'
    }).then(() => {
      if (panelVersions.get(element) !== version) return;
      element.hidden = !open;
      if (!open && options.clear) element.replaceChildren();
    });
  }

  function dialogTravel(root, dialog, distance) {
    if (!dialog || !root.classList.contains('auth-modal')) return distance;
    const style = getComputedStyle(root);
    const available = root.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom) - dialog.offsetHeight;
    return Math.min(distance, Math.max(0, available / 2));
  }

  function modalIn(root) {
    cancelTree(root);
    const dialog = $('[role="dialog"]', root);
    run(root, {opacity: [0, 1]}, {duration: 220});
    run(dialog, {opacity: [0, 1], y: [dialogTravel(root, dialog, 22), 0], scale: [.97, 1]}, {duration: 380});
    const symbol = $('.success-symbol', root);
    if (symbol) run(symbol, {opacity: [0, 1], scale: [.65, 1]}, {delay: 120, duration: 650, ease: 'outBack'});
  }

  function modalOut(root) {
    const dialog = $('[role="dialog"]', root);
    return Promise.all([
      run(root, {opacity: [getComputedStyle(root).opacity, 0]}, {duration: 180, ease: 'in(2)'}),
      run(dialog, {opacity: [1, 0], y: [0, dialogTravel(root, dialog, 10)], scale: [1, .98]}, {duration: 180, ease: 'in(2)'})
    ]);
  }

  function calendar(direction = 0) {
    run($('#booking-calendar [role="grid"]'), {opacity: [0, 1], x: [direction * 14, 0]}, {duration: 300});
  }

  function grid() {
    enter($$('#court-grid > div > div').slice(1), {distance: 10, duration: 350, stagger: 40});
  }

  function selection(box, first) {
    if (first) enter(box, {distance: 14, duration: 350});
  }

  function slot(element, selected) {
    if (selected) run(element, {scale: [.87, 1]}, {duration: 400, ease: 'outBack'});
  }

  function notice(element, show) {
    const version = (panelVersions.get(element) || 0) + 1;
    panelVersions.set(element, version);
    cancel(element);
    element.hidden = false;
    return run(element, {opacity: show ? [0, 1] : [1, 0], y: show ? [12, 0] : [0, 8]}, {duration: show ? 320 : 180}).then(() => {
      if (!show && panelVersions.get(element) === version) element.hidden = true;
    });
  }

  function init() {
    if (initialized) return;
    initialized = true;
    if (engine) document.documentElement.classList.add('motion-enhanced');
    if (typeof IntersectionObserver === 'function') {
      observer = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) reveal(entry.target); });
      }, {threshold: .08, rootMargin: '0px 0px -24px 0px'});
    }

    const hero = $('#home .max-w-2xl');
    enter([...hero.children].filter(element => element.tagName !== 'DL'), {duration: 800, stagger: 85, delay: 80});
    enter($$('#home dl > div'), {duration: 650, stagger: 70, delay: 560, distance: 18});
    run($('#home > div:first-child img'), {scale: [1.055, 1]}, {duration: 1500, ease: 'out(3)'});

    $$('main > section:not(#home) h2').forEach(heading => observe(heading.parentElement));
    $$('main [data-slot="card"]').forEach((card, index) => {
      card.classList.add('motion-card');
      observe(card, {delay: index % 3 * 65, distance: 28, duration: 700});
    });
    $$('#home dl > div').forEach(card => card.classList.add('motion-card'));
    $$('#faq [data-slot="accordion-item"]').forEach((item, index) => observe(item, {delay: index % 3 * 45, distance: 16}));
    ['#booking-calendar', '#court-grid', '#locate-us .location-layout'].forEach(selector => observe($(selector), {distance: 20}));
    const lounge = $('#facilities img')?.parentElement;
    if (lounge) { lounge.classList.add('motion-image'); observe(lounge, {duration: 800, scale: .98}); }
    $$('footer .grid > div').forEach((column, index) => observe(column, {delay: index * 50, distance: 16}));

    // Keyboard navigation and anchor jumps always expose the requested content.
    document.addEventListener('focusin', event => {
      for (const element of pending.keys()) if (element.contains(event.target)) reveal(element);
    });
    preference.addEventListener('change', () => {
      if (!preference.matches) return;
      [...active.keys()].forEach(cancel);
      [...pending.keys()].forEach(reveal);
    });
  }

  window.PikolMotion = {
    init, enter, cancelTree, panel, modalIn, modalOut, calendar, grid, selection, slot, notice,
    results: root => enter(root.children, {distance: 12, duration: 400, stagger: 60}),
    error: element => enter(element, {distance: 6, duration: 250}),
    menu: element => enter(element, {distance: -6, duration: 220})
  };
})();
