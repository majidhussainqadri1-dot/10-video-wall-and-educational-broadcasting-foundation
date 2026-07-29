(function () {
	'use strict';

	function messageFrom(payload, fallback) {
		if (payload && payload.data && payload.data.message) {
			return payload.data.message;
		}
		return fallback;
	}

	async function fetchJson(body, options) {
		var response;
		try {
			response = await fetch(svwData.url, Object.assign({
				method: 'POST',
				credentials: 'same-origin',
				body: body
			}, options || {}));
		} catch (error) {
			throw new Error(svwData.i18n.error);
		}

		var payload;
		try {
			payload = await response.json();
		} catch (error) {
			throw new Error(svwData.i18n.error);
		}

		if (!response.ok || !payload.success) {
			throw new Error(messageFrom(payload, svwData.i18n.error));
		}
		return payload;
	}

	function statusNode(container) {
		return container ? container.querySelector('[data-svw-status]') : null;
	}

	function setStatus(container, message, isError) {
		var node = statusNode(container);
		if (!node) {
			return;
		}
		node.textContent = message || '';
		node.classList.toggle('is-error', Boolean(isError));
	}

	document.addEventListener('click', async function (event) {
		var button = event.target.closest('[data-svw-action]');
		if (!button) {
			return;
		}

		event.preventDefault();
		var container = button.closest('[data-video-id]');
		if (!container || button.disabled) {
			return;
		}

		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		setStatus(container, svwData.i18n.saving, false);

		var data = new URLSearchParams({
			action: 'svw_action',
			nonce: svwData.nonce,
			videoId: container.dataset.videoId,
			kind: button.dataset.svwAction
		});

		try {
			var payload = await fetchJson(data, {
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
			});
			if (payload.data && payload.data.reload) {
				window.location.reload();
			}
		} catch (error) {
			setStatus(container, error.message, true);
			button.disabled = false;
			button.removeAttribute('aria-busy');
		}
	});

	document.addEventListener('submit', async function (event) {
		var form = event.target.closest('form[data-svw-report]');
		if (!form) {
			return;
		}

		event.preventDefault();
		if (!form.reportValidity()) {
			return;
		}

		var container = form.closest('[data-video-id]');
		var submit = form.querySelector('button[type="submit"]');
		if (!container || !submit || submit.disabled) {
			return;
		}

		submit.disabled = true;
		submit.setAttribute('aria-busy', 'true');
		setStatus(container, svwData.i18n.saving, false);

		var data = new FormData(form);
		data.append('action', 'svw_action');
		data.append('nonce', svwData.nonce);
		data.append('videoId', container.dataset.videoId);
		data.append('kind', 'report');

		try {
			var payload = await fetchJson(data);
			form.reset();
			form.hidden = true;
			setStatus(container, messageFrom(payload, svwData.i18n.reportSent), false);
		} catch (error) {
			setStatus(container, error.message, true);
			submit.disabled = false;
			submit.removeAttribute('aria-busy');
		}
	});

	function initializeLocalPlayer(wrapper) {
		var video = wrapper.querySelector('video');
		if (!video) {
			return;
		}

		var resume = Number.parseInt(wrapper.dataset.resume || '0', 10);
		var lastSent = 0;
		var sending = false;

		video.addEventListener('loadedmetadata', function () {
			if (resume > 0 && resume < Math.max(0, video.duration - 10)) {
				video.currentTime = resume;
			}
		}, {once: true});

		async function sendProgress(force) {
			var current = Math.max(0, Math.floor(video.currentTime || 0));
			var duration = Math.max(0, Math.floor(video.duration || 0));
			if (!force && (sending || current - lastSent < 15)) {
				return;
			}
			lastSent = current;
			sending = true;

			var data = new URLSearchParams({
				action: 'svw_action',
				nonce: svwData.nonce,
				videoId: wrapper.dataset.videoId,
				kind: 'progress',
				progress: String(current),
				duration: String(duration)
			});

			try {
				await fetchJson(data, {
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
					keepalive: Boolean(force)
				});
			} catch (error) {
				// Progress failure must not interrupt playback.
			} finally {
				sending = false;
			}
		}

		video.addEventListener('timeupdate', function () { sendProgress(false); });
		video.addEventListener('pause', function () { sendProgress(true); });
		video.addEventListener('ended', function () { sendProgress(true); });
		window.addEventListener('pagehide', function () { sendProgress(true); });
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-svw-local-player]').forEach(initializeLocalPlayer);
	});
}());
