(() => {
    'use strict';
    const token = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const jsonRequest = async (url, method, data) => {
        const response = await fetch(url, {method, credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token()}, body: data ? JSON.stringify(data) : undefined});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'La demande n’a pas abouti. Réessaie.');
        return result;
    };
    let installPrompt;
    const installButton = document.querySelector('[data-install-app]');
    const installHelp = document.querySelector('[data-install-help]');
    const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    if (standalone && installButton) installButton.hidden = true;
    window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); installPrompt = event; });
    installButton?.addEventListener('click', async () => {
        if (installPrompt) { await installPrompt.prompt(); await installPrompt.userChoice; installPrompt = null; }
        else if (installHelp) { installHelp.hidden = !installHelp.hidden; installHelp.textContent = /iPad|iPhone|iPod/.test(navigator.userAgent) ? 'Dans Safari : Partager → Sur l’écran d’accueil.' : 'Dans le menu du navigateur, choisis « Installer l’application » ou « Ajouter à l’écran d’accueil ». Une connexion HTTPS est nécessaire.'; }
    });
    window.addEventListener('appinstalled', () => { if (installButton) installButton.hidden = true; });
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    let registration;
    if (window.isSecureContext && 'serviceWorker' in navigator) registration = navigator.serviceWorker.register('/service-worker.js').then(() => navigator.serviceWorker.ready).catch(() => null);
    const enable = document.querySelector('[data-enable-push]');
    const disable = document.querySelector('[data-disable-push]');
    const status = document.querySelector('[data-push-status]');
    const setStatus = message => { if (status) status.textContent = message; };
    const refresh = async () => {
        if (!supported) {
            if (enable) enable.disabled = true;
            setStatus(!window.isSecureContext ? 'Ouvre le site en HTTPS pour activer les notifications navigateur.' : 'Ce navigateur ne prend pas en charge les notifications push. Sur iPhone, installe l’application depuis Safari sur l’écran d’accueil (iOS 16.4 ou plus récent).');
            return;
        }
        const sw = await registration;
        if (!sw) { setStatus('Impossible de préparer les notifications. Recharge la page.'); return; }
        const subscription = await sw.pushManager.getSubscription();
        const registered = subscription ? (await jsonRequest('/notifications/push/status','POST',{endpoint:subscription.endpoint})).registered : false;
        if (disable) disable.hidden = !subscription;
        if (enable) enable.hidden = !!registered;
        setStatus(registered ? 'Cet appareil est connecté. Enregistre aussi tes préférences ci-dessous.' : subscription ? 'Cet appareil doit être associé à ce compte. Autorise-le ou désactive son ancien abonnement.' : Notification.permission === 'denied' ? 'Notifications bloquées. Autorise-les dans les paramètres du navigateur, puis réessaie.' : 'Active cet appareil, puis enregistre tes préférences.');
    };
    if (enable) refresh().catch(() => setStatus('Impossible de lire les réglages du navigateur.'));
    enable?.addEventListener('click', async () => {
        enable.disabled = true;
        let subscription;
        let created = false;
        try {
            const {publicKey} = await jsonRequest('/notifications/push/key','GET');
            if (!publicKey) throw new Error('Les notifications navigateur ne sont pas encore disponibles.');
            if (await Notification.requestPermission() !== 'granted') throw new Error('Autorisation refusée. Tu peux continuer à recevoir les alertes dans l’application.');
            const sw = await registration;
            if (!sw) throw new Error('Recharge la page pour préparer les notifications.');
            subscription = await sw.pushManager.getSubscription();
            if (!subscription) {
                const base64 = (publicKey + '='.repeat((4 - publicKey.length % 4) % 4)).replace(/-/g,'+').replace(/_/g,'/');
                const key = Uint8Array.from(atob(base64), c=>c.charCodeAt(0));
                subscription = await sw.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
                created = true;
            }
            await jsonRequest('/notifications/push','POST',subscription.toJSON());
            const checkbox = document.querySelector('#notify_web');
            if (checkbox) checkbox.checked = true;
            await refresh();
        } catch(error) {
            if (created && subscription) await subscription.unsubscribe().catch(()=>{});
            setStatus(error.message);
        } finally { enable.disabled = false; }
    });
    disable?.addEventListener('click', async () => {
        disable.disabled = true;
        try {
            const sw = await registration;
            const subscription = await sw?.pushManager.getSubscription();
            if (subscription) { await jsonRequest('/notifications/push','DELETE',{endpoint:subscription.endpoint}); await subscription.unsubscribe(); }
            await refresh();
        } catch(error) { setStatus(error.message); }
        finally { disable.disabled = false; }
    });
    document.querySelector('[data-logout]')?.addEventListener('submit', async event => {
        if (!supported) return;
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const sw = await registration;
            const subscription = await sw?.pushManager.getSubscription();
            if (subscription) {
                const field = document.createElement('input'); field.type='hidden'; field.name='push_endpoint'; field.value=subscription.endpoint; form.append(field);
                await subscription.unsubscribe();
            }
        } catch (_) {}
        form.submit();
    });
})();

// Select the next available TMDb resolution for the actual rendered size and pixel density.
// Dynamic popups use the same behaviour as full-page detail views.
(() => {
    const initialized = new WeakSet();
    const active = new Set();
    function initialize(image) {
        if (initialized.has(image)) return;
        initialized.add(image);
        const frame = image.closest('[data-detail-image-frame]');
        const sources = JSON.parse(image.dataset.imageSources || '[]');
        const original = sources.at(-1);
        if (!frame || !original) return;
        let width = Number(image.dataset.imageWidth) || 0;
        let height = Number(image.dataset.imageHeight) || 0;
        const fit = (selectSource = true) => {
            const density = window.devicePixelRatio || 1;
            let displayWidth = frame.clientWidth;
            if (width && height) {
                displayWidth = Math.min(displayWidth, frame.clientHeight * width / height, width / density);
                image.style.width = displayWidth + 'px';
                image.style.height = (displayWidth * height / width) + 'px';
            }
            if (selectSource && displayWidth) {
                const required = Math.ceil(displayWidth * density);
                const source = width ? sources.find(item => item.width >= required && item.width <= width) || original : original;
                if (image.getAttribute('src') !== source.url) image.src = source.url;
            }
        };
        image.addEventListener('load', () => {
            // If TMDb omitted the metadata, the original itself gives its real dimensions.
            if ((!width || !height) && image.src === original.url) {
                width = image.naturalWidth;
                height = image.naturalHeight;
                fit(false);
            }
        });
        const observer = new ResizeObserver(() => fit());
        observer.observe(frame);
        active.add({image, observer, fit});
        fit();
        if (image.complete && image.naturalWidth && (!width || !height)) {
            width = image.naturalWidth; height = image.naturalHeight; fit(false);
        }
    }
    const refresh = () => {
        for (const entry of active) {
            if (!entry.image.isConnected) { entry.observer.disconnect(); active.delete(entry); }
        }
        document.querySelectorAll('[data-detail-image]').forEach(initialize);
    };
    new MutationObserver(refresh).observe(document.documentElement, {childList:true, subtree:true});
    window.addEventListener('resize', () => { for (const entry of active) entry.fit(); });
    refresh();
})();
