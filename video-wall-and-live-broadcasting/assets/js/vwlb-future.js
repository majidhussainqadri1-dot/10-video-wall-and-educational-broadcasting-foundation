(() => {
  'use strict';
  const cfg = window.VWLB || {};
  if (!cfg.root) return;
  const idempotencyKey = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') { const b = new Uint8Array(16); window.crypto.getRandomValues(b); return Array.from(b, (v) => v.toString(16).padStart(2, '0')).join(''); }
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  };
  const api = async (path, options = {}) => {
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    const method = String(options.method || 'GET').toUpperCase();
    if (!['GET','HEAD','OPTIONS'].includes(method) && !headers['Idempotency-Key']) headers['Idempotency-Key'] = options.idempotencyKey || idempotencyKey();
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    const response = await fetch(`${cfg.root}${path}`, Object.assign({credentials:'same-origin'}, options, {headers}));
    let payload = null;
    try { payload = await response.json(); } catch (_) { payload = null; }
    if (!response.ok) throw new Error(payload && payload.message ? payload.message : 'Request failed.');
    return payload;
  };
  const playerFor = (root) => root.closest('.content-area, body').querySelector('video.vwlb-player');

  document.querySelectorAll('[data-vwlb-future-video]').forEach((root) => {
    const videoId = root.getAttribute('data-vwlb-future-video');
    root.querySelectorAll('[data-vwlb-future-seek]').forEach((button) => button.addEventListener('click', () => {
      const player = playerFor(root); const seconds = Number(button.getAttribute('data-vwlb-future-seek') || 0);
      if (player && Number.isFinite(seconds)) { player.currentTime = Math.max(0, seconds); player.focus(); }
    }));
    const form = root.querySelector('[data-vwlb-search-inside]');
    const output = root.querySelector('[data-vwlb-search-results]');
    if (form && output) form.addEventListener('submit', async (event) => {
      event.preventDefault(); const q = (new FormData(form).get('q') || '').toString().trim(); if (q.length < 2) return;
      output.textContent = 'Searching…';
      try {
        const result = await api(`/videos/${encodeURIComponent(videoId)}/search-inside?q=${encodeURIComponent(q)}`);
        output.textContent = '';
        const items = Array.isArray(result.items) ? result.items : [];
        if (!items.length) { output.textContent = 'No reviewed transcript match found.'; return; }
        const list = document.createElement('ol'); list.className = 'vwlb-search-inside-results';
        items.forEach((item) => {
          const li = document.createElement('li'); const button = document.createElement('button'); button.type = 'button';
          const seconds = Math.floor(Number(item.start_ms || 0) / 1000); button.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2,'0')} — ${item.segment_text || ''}`;
          button.addEventListener('click', () => { const player = playerFor(root); if (player) { player.currentTime = seconds; player.focus(); } });
          li.appendChild(button); list.appendChild(li);
        }); output.appendChild(list);
      } catch (e) { output.textContent = e.message; }
    });
  });

  document.querySelectorAll('[data-vwlb-live-poll]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault(); const pollId = form.getAttribute('data-vwlb-live-poll');
      const selected = Array.from(form.querySelectorAll('input[name="poll_option"]:checked')).map((n) => n.value);
      const status = form.querySelector('.vwlb-status'); if (!selected.length) { if (status) status.textContent = 'Choose an option.'; return; }
      const button = form.querySelector('button[type="submit"]'); if (button) button.disabled = true;
      try { await api(`/live-polls/${encodeURIComponent(pollId)}/answers`, {method:'POST', body:JSON.stringify({option_ids:selected})}); if (status) status.textContent = 'Answer saved.'; }
      catch (e) { if (status) status.textContent = e.message; }
      finally { if (button) button.disabled = false; }
    });
  });
})();