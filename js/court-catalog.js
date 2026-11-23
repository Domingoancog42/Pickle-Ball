/* Public court cards and hourly rates share Court Management's saved records. */
(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const money = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP', maximumFractionDigits: 2}).format(Number(value)).replace(/\.00$/, '');
  let grid, rates, templates, rateTemplate, request;
  let cards = new Map(), rateCards = new Map();

  function initialize() {
    grid = $('[data-court-grid]');
    rates = $('[data-rate-grid]');
    templates = new Map([...grid.children].map(wrapper => [String($('[data-court-id]', wrapper).dataset.courtId), wrapper.cloneNode(true)]));
    rateTemplate = rates?.firstElementChild.cloneNode(true);
    grid.replaceChildren();
    rates?.replaceChildren();
  }

  function render(items) {
    const nextCards = new Map(), nextRates = new Map();
    for (const court of items) {
      const id = String(court.court_id);
      let wrapper = cards.get(id);
      if (!wrapper) {
        const template = templates.get(id) || templates.values().next().value;
        wrapper = template.cloneNode(true);
        if (!templates.has(id)) $('ul', wrapper)?.remove();
      }
      const card = $('[data-court-id]', wrapper);
      card.dataset.courtId = id;
      $('[data-court-name]', card).textContent = court.court_name;
      $('[data-court-rate]', card).textContent = money(court.hourly_rate);
      $('[data-court-type]', card).textContent = court.type;
      $('[data-court-surface]', card).textContent = court.type;
      const image = $('img', card);
      const source = court.image_path || 'assets/images/logo.png';
      if (image.getAttribute('src') !== source) image.setAttribute('src', source);
      image.alt = court.court_name;
      $('[data-action="court"]', card).dataset.court = id;
      nextCards.set(id, wrapper);

      if (rateTemplate) {
        const rate = rateCards.get(id) || rateTemplate.cloneNode(true);
        $('[data-rate-name]', rate).textContent = court.court_name;
        $('[data-rate-type]', rate).textContent = court.type;
        $('[data-rate-price]', rate).textContent = money(court.hourly_rate);
        nextRates.set(id, rate);
      }
    }
    const sameIds = (previous, next) => [...previous.keys()].join(',') === [...next.keys()].join(',');
    if (!sameIds(cards, nextCards) || !grid.firstElementChild) grid.replaceChildren(...nextCards.values());
    if (rates && (!sameIds(rateCards, nextRates) || !rates.firstElementChild)) rates.replaceChildren(...nextRates.values());
    cards = nextCards;
    rateCards = nextRates;
    if (!items.length) {
      notice(grid, 'No courts are currently available for booking.');
      notice(rates, 'Court rates will appear when courts are available.');
    }
    $('[data-court-count]').textContent = items.length;
    const count = items.length === 1 ? 'One' : items.length === 2 ? 'Two' : String(items.length);
    $('#courts h2').textContent = items.length ? `${count} Battleground${items.length === 1 ? '' : 's'}. One Castle.` : 'Our Courts';
  }

  function notice(container, message, retry = false) {
    if (!container) return;
    const box = document.createElement('div');
    box.className = 'col-span-full text-center text-muted-foreground py-6';
    const text = document.createElement('p');
    text.setAttribute('role', 'status');
    text.textContent = message;
    box.append(text);
    if (retry) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-primary mt-4';
      button.textContent = 'Try again';
      button.addEventListener('click', refresh);
      box.append(button);
    }
    container.replaceChildren(box);
  }

  function refresh() {
    if (request) return request;
    if (!grid) initialize();
    request = (async () => {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 8000);
      try {
        const response = await fetch('api/courts.php', {cache: 'no-store', credentials: 'same-origin', signal: controller.signal});
        const data = await response.json();
        if (!response.ok || !Array.isArray(data.items)) throw new Error('Court catalog unavailable.');
        render(data.items);
      } catch {
        cards.clear();
        rateCards.clear();
        notice(grid, 'Court details are currently unavailable. Please try again.', true);
        notice(rates, 'Court rates are currently unavailable. Please try again.', true);
      } finally {
        clearTimeout(timer);
        request = null;
      }
    })();
    return request;
  }

  window.PikolCourtCatalog = {refresh};
})();
