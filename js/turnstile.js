/* Cloudflare Turnstile lifecycle for the dynamically mounted login dialog. */
(() => {
  'use strict';
  const states = new WeakMap();
  let apiPromise;

  function loadApi() {
    if (window.turnstile) return Promise.resolve(window.turnstile);
    if (apiPromise) return apiPromise;
    apiPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      const fail = () => {
        clearTimeout(timer);
        script.remove();
        apiPromise = null;
        reject(new Error('Could not load the security check. Check your connection and try again.'));
      };
      const timer = setTimeout(fail, 15000);
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
      script.async = true;
      script.onerror = fail;
      script.onload = () => {
        if (!window.turnstile) return fail();
        clearTimeout(timer);
        resolve(window.turnstile);
      };
      document.head.append(script);
    });
    return apiPromise;
  }

  function sync(state, message, retry = false) {
    if (state.disposed) return;
    if (message) {
      state.status.textContent = message;
      state.retry.hidden = !retry;
    }
    state.button.disabled = state.submitting || !state.token;
  }

  function render(state) {
    if (state.disposed) return;
    const size = state.container.parentElement.clientWidth < 300 ? 'compact' : 'flexible';
    if (state.widgetId !== null) window.turnstile.remove(state.widgetId);
    state.token = '';
    state.size = size;
    state.container.dataset.size = size;
    sync(state, 'Complete the security check to log in.');
    state.widgetId = window.turnstile.render(state.container, {
      sitekey: state.siteKey,
      action: 'login',
      theme: 'light',
      size,
      callback: token => {
        state.token = token;
        sync(state, 'Security check complete.');
      },
      'expired-callback': () => {
        state.token = '';
        sync(state, 'Security check expired. Please complete it again.', true);
      },
      'timeout-callback': () => {
        state.token = '';
        sync(state, 'Security check timed out. Please try again.', true);
      },
      'error-callback': () => {
        state.token = '';
        sync(state, 'Security check failed. Check your connection and try again.', true);
        return true;
      }
    });
  }

  async function start(state) {
    state.token = '';
    sync(state, 'Loading security check...');
    try {
      const [siteKey] = await Promise.all([state.getSiteKey(), loadApi()]);
      if (state.disposed) return;
      if (!siteKey) throw new Error('The security check is unavailable. Please try again shortly.');
      state.siteKey = siteKey;
      render(state);
      if (!state.observer) {
        state.observer = new ResizeObserver(() => {
          const size = state.container.parentElement.clientWidth < 300 ? 'compact' : 'flexible';
          if (size !== state.size) render(state);
        });
        state.observer.observe(state.container.parentElement);
      }
    } catch (error) {
      sync(state, error.message, true);
    }
  }

  function reset(form) {
    const state = states.get(form);
    if (!state || state.disposed) return;
    state.token = '';
    sync(state, 'Complete the security check to log in.');
    if (state.widgetId !== null) window.turnstile.reset(state.widgetId);
    else start(state);
  }

  window.PikolTurnstile = {
    mount(form, getSiteKey) {
      const state = {
        container: form.querySelector('[data-turnstile-widget]'),
        status: form.querySelector('[data-turnstile-status]'),
        retry: form.querySelector('[data-turnstile-retry]'),
        button: form.querySelector('button[type="submit"]'),
        token: '', widgetId: null, submitting: false, disposed: false, getSiteKey
      };
      states.set(form, state);
      state.onRetry = () => start(state);
      state.retry.addEventListener('click', state.onRetry);
      start(state);
    },
    token(form) {
      const token = states.get(form)?.token;
      if (!token) throw new Error('Complete the security check before logging in.');
      return token;
    },
    setSubmitting(form, value) {
      const state = states.get(form);
      if (state) { state.submitting = value; sync(state); }
    },
    reset,
    unmount(root) {
      const form = root.querySelector('form[data-auth="login"]');
      const state = states.get(form);
      if (!state) return;
      state.disposed = true;
      state.observer?.disconnect();
      state.retry.removeEventListener('click', state.onRetry);
      if (state.widgetId !== null) window.turnstile.remove(state.widgetId);
      states.delete(form);
    }
  };
})();
