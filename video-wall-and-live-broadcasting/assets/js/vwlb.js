(() => {
  'use strict';
  const cfg = window.VWLB || {};
  const status = (root, message, error = false) => {
    const node = root.querySelector('.vwlb-status');
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('is-error', error);
  };
  const idempotencyKey = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') { const b = new Uint8Array(16); window.crypto.getRandomValues(b); return Array.from(b, (v) => v.toString(16).padStart(2, '0')).join(''); }
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  };
  const request = async (path, options = {}) => {
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    const method = String(options.method || 'GET').toUpperCase();
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !headers['Idempotency-Key']) headers['Idempotency-Key'] = options.idempotencyKey || idempotencyKey();
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    const response = await fetch(`${cfg.root}${path}`, Object.assign({credentials: 'same-origin'}, options, {headers}));
    let payload = null;
    try { payload = await response.json(); } catch (e) { payload = null; }
    if (!response.ok) {
      const message = payload && payload.message ? payload.message : (cfg.i18n && cfg.i18n.error) || 'Request failed.';
      const err = new Error(message); err.payload = payload; throw err;
    }
    return payload;
  };

  document.querySelectorAll('[data-vwlb-back]').forEach((button) => {
    button.addEventListener('click', () => {
      if (window.history.length > 1) window.history.back();
      else window.location.assign('/');
    });
  });

  document.querySelectorAll('[data-vwlb-video]').forEach((root) => {
    const videoId = root.getAttribute('data-vwlb-video');
    const player = root.querySelector('video.vwlb-player');
    let lastSaved = 0;
    let watchStart = Date.now();
    let restShown = false;

    if (player) {
      player.autoplay = false;
      const resume = Number(player.getAttribute('data-resume') || 0);
      player.addEventListener('loadedmetadata', () => {
        if (resume > 0 && resume < player.duration - 5) player.currentTime = resume;
      }, {once: true});
      const save = async (force = false) => {
        const seconds = Math.floor(player.currentTime || 0);
        if (!force && seconds - lastSaved < 15) return;
        lastSaved = seconds;
        try {
          await request(`/videos/${encodeURIComponent(videoId)}/progress`, {
            method: 'POST',
            body: JSON.stringify({progress_seconds: seconds, duration_seconds: Math.floor(player.duration || 0)})
          });
        } catch (e) { /* progress is best-effort */ }
      };
      player.addEventListener('timeupdate', () => {
        save(false);
        const restAfter = Math.max(15, Number(root.getAttribute('data-vwlb-session-minutes') || 45)) * 60000;
        if (!restShown && Date.now() - watchStart >= restAfter) {
          restShown = true;
          status(root, (cfg.i18n && cfg.i18n.sessionRest) || 'Consider a short rest.');
        }
      });
      player.addEventListener('pause', () => save(true));
      player.addEventListener('ended', () => { save(true); watchStart = Date.now(); });
      document.addEventListener('visibilitychange', () => { if (document.hidden) save(true); });
      root.querySelectorAll('[data-vwlb-seek]').forEach((button) => {
        button.addEventListener('click', () => {
          const target = Number(button.getAttribute('data-vwlb-seek') || 0);
          if (Number.isFinite(target)) { player.currentTime = target; player.focus(); }
        });
      });
    }

    root.querySelectorAll('[data-vwlb-action]').forEach((button) => {
      button.addEventListener('click', async () => {
        const action = button.getAttribute('data-vwlb-action');
        button.disabled = true; button.setAttribute('aria-busy', 'true');
        try {
          const result = await request(`/videos/${encodeURIComponent(videoId)}/interactions`, {
            method: 'POST', body: JSON.stringify({interaction: action})
          });
          button.setAttribute('aria-pressed', result.active ? 'true' : 'false');
          status(root, (cfg.i18n && cfg.i18n.saved) || 'Saved.');
        } catch (e) { status(root, e.message, true); }
        finally { button.disabled = false; button.removeAttribute('aria-busy'); }
      });
    });

    const bandwidth = root.querySelector('[data-vwlb-bandwidth]');
    if (bandwidth) {
      const stored = window.localStorage ? localStorage.getItem('vwlb-low-bandwidth') === '1' : false;
      bandwidth.setAttribute('aria-pressed', stored ? 'true' : 'false');
      root.classList.toggle('vwlb-low-bandwidth', stored);
      bandwidth.addEventListener('click', () => {
        const enabled = bandwidth.getAttribute('aria-pressed') !== 'true';
        bandwidth.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        root.classList.toggle('vwlb-low-bandwidth', enabled);
        try { localStorage.setItem('vwlb-low-bandwidth', enabled ? '1' : '0'); } catch (e) {}
        status(root, enabled ? ((cfg.i18n && cfg.i18n.lowBandwidth) || 'Low bandwidth mode') : '');
      });
    }

    const transcriptButton = root.querySelector('[data-vwlb-transcript-toggle]');
    const transcript = root.querySelector('[data-vwlb-transcript]');
    if (transcriptButton && transcript) {
      transcriptButton.addEventListener('click', () => {
        const open = transcriptButton.getAttribute('aria-expanded') !== 'true';
        transcriptButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        transcript.hidden = !open;
      });
    }
  });

  document.querySelectorAll('[data-vwlb-live]').forEach((root) => {
    const liveId = root.getAttribute('data-vwlb-live');
    const waiting = root.querySelector('[data-vwlb-waiting-room]');
    if (waiting) waiting.addEventListener('click', async () => {
      waiting.disabled = true;
      try {
        const result = await request(`/live-events/${encodeURIComponent(liveId)}/waiting-room`, {method:'POST', body:JSON.stringify({reminder_minutes:15})});
        status(root, result.state === 'waiting' ? 'Waiting room joined.' : 'Updated.');
      } catch (e) { status(root,e.message,true); }
      finally { waiting.disabled = false; }
    });
    const consent = root.querySelector('[data-vwlb-recording-consent]');
    if (consent) consent.addEventListener('change', async () => {
      consent.disabled = true;
      try {
        await request(`/live-events/${encodeURIComponent(liveId)}/recording-consent`, {method:'POST',body:JSON.stringify({consent:consent.checked,consent_version:'v1'})});
        status(root, consent.checked ? 'Recording consent saved.' : 'Recording consent withdrawn.');
      } catch (e) { consent.checked = !consent.checked; status(root,e.message,true); }
      finally { consent.disabled = false; }
    });
    const qform = root.querySelector('[data-vwlb-question-form]');
    if (qform) qform.addEventListener('submit', async (event) => {
      event.preventDefault();
      const textarea = qform.querySelector('textarea[name="question"]');
      const question = textarea ? textarea.value.trim() : '';
      if (!question) return;
      const button = qform.querySelector('button[type="submit"]'); if (button) button.disabled = true;
      try {
        await request(`/live-events/${encodeURIComponent(liveId)}/questions`, {method:'POST',body:JSON.stringify({question})});
        if (textarea) textarea.value = ''; status(root,'Question submitted for moderation.');
      } catch (e) { status(root,e.message,true); }
      finally { if (button) button.disabled = false; }
    });
  });

  document.querySelectorAll('[data-vwlb-countdown]').forEach((node) => {
    const target = Date.parse(node.getAttribute('datetime')); if (!Number.isFinite(target)) return;
    const render = () => {
      const diff = Math.max(0, target - Date.now());
      if (diff <= 0) { node.textContent = (cfg.i18n && cfg.i18n.startingSoon) || 'Starting soon'; return; }
      const seconds = Math.floor(diff / 1000);
      const days = Math.floor(seconds / 86400);
      const hours = Math.floor((seconds % 86400) / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      node.textContent = `${days}d ${hours}h ${minutes}m`;
    };
    render(); window.setInterval(render, 60000);
  });

  document.querySelectorAll('[data-vwlb-clear-history]').forEach((button) => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try { await request('/history', {method:'DELETE'}); window.location.reload(); }
      catch (e) { const root = button.closest('.vwlb-shell'); if (root) status(root,e.message,true); button.disabled = false; }
    });
  });

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('vwlb-reduced-motion');
  }
})();
