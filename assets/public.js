(() => {
  'use strict';
  const url = new URL(location.href);
  const route = new URLSearchParams();
  for (const key of ['p', 'page_id']) {
    const id = url.searchParams.get(key);
    if (id && /^[0-9]{1,12}$/.test(id)) route.set(key, id);
  }
  const landing = url.origin + url.pathname + (route.size ? '?' + route.toString() : '');
  const details = { landing, referrer: '', ref_state: '', at: Date.now() };
  const safePath = value => value.length <= 512 ? value : '';
  for (const key of ['source', 'medium', 'campaign', 'term', 'content']) {
    const name = 'utm_' + key;
    details[name] = (url.searchParams.get(name) || '').slice(0, 190);
  }
  try {
    const ref = document.referrer ? new URL(document.referrer) : null;
    if (ref && ['http:', 'https:'].includes(ref.protocol)) {
      details.referrer = safePath(ref.origin + ref.pathname);
    } else if (!ref) {
      details.ref_state = 'none';
    }
  } catch (_) { /* Attribution is optional. */ }
  let visit = details;
  if (window.hteclRememberVisit) {
    try {
      const key = 'htecl-attribution:' + location.origin;
      const prior = JSON.parse(sessionStorage.getItem(key) || 'null');
      if (prior && prior.at > Date.now() - 1800000 && prior.at <= Date.now()) {
        visit = prior;
      } else {
        sessionStorage.setItem(key, JSON.stringify(details));
      }
    } catch (_) { /* Blocked storage does not prevent capture. */ }
  }
  for (const form of document.querySelectorAll('form.htecl-public')) {
    for (const key of Object.keys(details)) {
      if (key !== 'at' && typeof visit[key] === 'string') {
        form.elements.namedItem(key).value = visit[key];
      }
    }
  }
})();
