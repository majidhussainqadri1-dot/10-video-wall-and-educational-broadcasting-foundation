(() => {
  'use strict';
  const cfg = window.VWLB || {};
  const status = (root, message, error = false) => {
    const node = root.querySelector('.vwlb-status');
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('is-error', error);
  };
  const request = async (path, options = {}) => {
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    const response = await fetch(`${cfg.root}${path}`, Object.assign({credentials: 'same-origin'}, options, {headers}));
    let payload = null;
    try { payload = await response.json(); } catch (e) { payload = null; }
    if (!response.ok) {
      const message = payload && payload.message ? payload.message : (cfg.i18n && cfg.i18n.error) || 'Request failed.';
      const err = new Error(message);
      err.payload = payload;
      throw err;
    }
    return payload;
  };

  document.querySelectorAll('[data-vwlb-video]').forEach((root) => {
    const videoId = root.getAttribute('data-vwlb-video');
    const player = root.querySelector('video.vwlb-player');
    let lastSaved = 0;
    if (player) {
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
        } catch (e) { /* Progress is best-effort; never interrupt playback. */ }
      };
      player.addEventListener('timeupdate', () => save(false));
      player.addEventListener('pause', () => save(true));
      player.addEventListener('ended', () => save(true));
      document.addEventListener('visibilitychange', () => { if (document.hidden) save(true); });
    }
    root.querySelectorAll('[data-vwlb-action]').forEach((button) => {
      button.addEventListener('click', async () => {
        const action = button.getAttribute('data-vwlb-action');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
          const result = await request(`/videos/${encodeURIComponent(videoId)}/interactions`, {
            method: 'POST', body: JSON.stringify({interaction: action})
          });
          button.setAttribute('aria-pressed', result.active ? 'true' : 'false');
          status(root, (cfg.i18n && cfg.i18n.saved) || 'Saved.');
        } catch (e) {
          status(root, e.message, true);
        } finally {
          button.disabled = false;
          button.removeAttribute('aria-busy');
        }
      });
    });
  });

  document.querySelectorAll('[data-vwlb-countdown]').forEach((node) => {
    const target = Date.parse(node.getAttribute('datetime'));
    if (!Number.isFinite(target)) return;
    const render = () => {
      const diff = Math.max(0, target - Date.now());
      if (diff <= 0) { node.textContent = 'Starting soon'; return; }
      const seconds = Math.floor(diff / 1000);
      const days = Math.floor(seconds / 86400);
      const hours = Math.floor((seconds % 86400) / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      node.textContent = `${days}d ${hours}h ${minutes}m`;
    };
    render();
    window.setInterval(render, 60000);
  });
})();
