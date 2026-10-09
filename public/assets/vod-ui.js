(() => {
    'use strict';
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-home-link]') || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        try {
            const key='vodfinder_search_form_v2';
            const saved=JSON.parse(localStorage.getItem(key) || 'null');
            if (saved && typeof saved==='object') localStorage.setItem(key,JSON.stringify({...saved,q:'',personId:null}));
        } catch (_) {}
    });
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

// Detail actions are delegated because popups arrive after the page has loaded.
(() => {
    const request = async (url, data, method = 'POST') => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(url, {method,credentials:'same-origin',signal:controller.signal,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},body:JSON.stringify(data)});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Cette action a échoué. Réessaie.');
            return result;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Cette action a pris trop de temps. Réessaie dans quelques instants.');
            throw error;
        } finally { clearTimeout(timeout); }
    };
    function closePanel(actions) {
        actions.querySelector('[data-list-panel]')?.classList.add('hidden');
        actions.querySelector('[data-open-list-menu]')?.setAttribute('aria-expanded','false');
    }
    function markListed(actions) {
        const button = actions.querySelector('[data-open-list-menu]');
        button.classList.add('is-active');
        button.setAttribute('aria-label','Déjà dans une liste');
        button.title = 'Déjà dans une liste';
        button.querySelector('.vod-action-label').textContent = 'Dans une liste';
        closePanel(actions);
    }
    function report(actions, message) {
        const status = actions.querySelector('[data-action-status]');
        if (status) { status.textContent = message; status.hidden = false; setTimeout(()=>{status.hidden=true;},5000); }
    }
    document.addEventListener('click', async event => {
        const actions = event.target.closest('[data-content-actions]');
        document.querySelectorAll('[data-content-actions]').forEach(element=>{if (!actions || element!==actions) closePanel(element);});
        if (!actions) return;
        const menu = event.target.closest('[data-open-list-menu]');
        if (menu) { const panel=actions.querySelector('[data-list-panel]'); panel.classList.toggle('hidden'); menu.setAttribute('aria-expanded',String(!panel.classList.contains('hidden'))); return; }
        const favorite = event.target.closest('[data-favorite-btn]');
        const playlist = event.target.closest('[data-playlist-btn]');
        const list = event.target.closest('[data-add-to-list]');
        const button = favorite || playlist || list;
        if (!button || button.disabled) return;
        button.disabled = true;
        try {
            const payload={tmdb_id:Number(actions.dataset.id),type:actions.dataset.type};
            if (playlist) {
                const result=await request('/watchlist/toggle',{...payload,title:playlist.dataset.title,year:playlist.dataset.year || null,poster:playlist.dataset.poster || null});
                if (typeof result.in_watchlist !== 'boolean') throw new Error('La mise à jour de la playlist n’a pas été confirmée. Réessaie.');
                const active=result.in_watchlist;
                const label=active?'Retirer de la playlist':'Ajouter à la playlist';
                document.querySelectorAll('[data-content-actions]').forEach(element=>{
                    if (element.dataset.id!==actions.dataset.id || element.dataset.type!==actions.dataset.type) return;
                    const pill=element.querySelector('[data-playlist-btn]');
                    if (!pill) return;
                    pill.classList.toggle('is-active',active);pill.setAttribute('aria-pressed',String(active));
                    pill.setAttribute('aria-label',label);pill.title=label;
                    pill.querySelector('.vod-action-label').textContent=active?'Dans la playlist':'Ajouter à la playlist';
                });
                document.querySelectorAll('[data-watchlist-button]').forEach(card=>{
                    if (card.dataset.id!==actions.dataset.id || card.dataset.type!==actions.dataset.type) return;
                    card.dataset.in=active?'1':'0';card.classList.toggle('is-active',active);
                    card.setAttribute('aria-pressed',String(active));card.setAttribute('aria-label',`${label} : ${card.dataset.title}`);card.title=label;
                    if (!active && card.closest('[data-playlist-card]')) {
                        card.closest('[data-playlist-card]').remove();
                        if (typeof window.renderPlaylist==='function') window.renderPlaylist();
                    }
                });
                // Cached searches must reload their playlist state on the next visit.
                try { Object.keys(sessionStorage).filter(key=>key.startsWith('vodfinder_results:')).forEach(key=>sessionStorage.removeItem(key)); } catch (_) {}
                document.dispatchEvent(new CustomEvent('vod:playlist-changed',{detail:{id:Number(actions.dataset.id),type:actions.dataset.type,inWatchlist:active}}));
                report(actions,active?'Ajouté à la playlist.':'Retiré de la playlist.');
            } else if (favorite) {
                const result=await request('/favorites/toggle',payload);
                favorite.classList.toggle('is-active',!!result.favorited);
                favorite.setAttribute('aria-pressed',String(!!result.favorited));
                favorite.setAttribute('aria-label',result.favorited?'Retirer des coups de cœur':'Coup de cœur');
                favorite.title=favorite.getAttribute('aria-label');
            } else { await request('/lists/'+encodeURIComponent(list.dataset.listId)+'/items',payload); markListed(actions); }
        } catch(error) { report(actions,error.message); }
        finally { button.disabled=false; }
    });
    document.addEventListener('submit', async event => {
        const followForm=event.target.closest('[data-series-follow-form]');
        if (followForm) {
            event.preventDefault();
            const actions=followForm.closest('[data-content-actions]');
            const button=followForm.querySelector('[data-action="follow"]');
            if (button.disabled) return;
            const wasFollowed = button.getAttribute('aria-pressed') === 'true';
            button.disabled=true;
            try {
                const result=await request(followForm.action,{tmdb_id:Number(actions.dataset.id)},wasFollowed ? 'DELETE' : 'POST');
                if (result.followed !== !wasFollowed || (result.followed && (!Number.isInteger(result.follow_id) || result.follow_id <= 0))) throw new Error('La modification du suivi n’a pas été confirmée. Réessaie.');
                document.querySelectorAll('[data-content-actions]').forEach(element=>{
                    if (element.dataset.type!==actions.dataset.type || element.dataset.id!==actions.dataset.id) return;
                    const pill=element.querySelector('[data-action="follow"]');
                    if (!pill) return;
                    const form = pill.closest('[data-series-follow-form]');
                    form.action = result.followed ? `${form.dataset.followDeleteBase}/${result.follow_id}` : form.dataset.followStart;
                    form.querySelector('input[name="_method"]')?.remove();
                    if (result.followed) { const input=document.createElement('input');input.type='hidden';input.name='_method';input.value='DELETE';form.appendChild(input); }
                    const label = result.followed ? 'Ne plus suivre cette série' : 'Suivre la série';
                    pill.classList.toggle('is-active',result.followed);pill.setAttribute('aria-pressed',String(result.followed));
                    pill.setAttribute('aria-label',label);pill.title=label;
                    pill.querySelector('.vod-action-label').textContent=result.followed ? 'Série suivie' : 'Suivre la série';
                });
                report(actions,result.message);
            } catch(error) { report(actions,error.message); }
            finally { button.disabled=false; }
            return;
        }
        const form=event.target.closest('[data-create-list-form]');
        if (!form) return;
        event.preventDefault();
        const actions=form.closest('[data-content-actions]');
        const button=form.querySelector('[type=submit]');
        if (button.disabled) return;
        button.disabled=true;
        try {
            // Keep the created ID on retry if adding the title temporarily fails.
            if (!form.dataset.createdListId) {
                const result=await request('/lists',{name:form.elements.name.value,is_public:form.elements.is_public.checked});
                form.dataset.createdListId=String(result.list.id);
            }
            await request('/lists/'+encodeURIComponent(form.dataset.createdListId)+'/items',{tmdb_id:Number(actions.dataset.id),type:actions.dataset.type});
            markListed(actions);
            form.reset(); delete form.dataset.createdListId;
        } catch(error) { report(actions,error.message); }
        finally { button.disabled=false; }
    });
    document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('[data-content-actions]').forEach(closePanel);});
})();
// All detail views share the same recoverable loading error.
window.vodShowPopupError = (overlay, retry, close) => {
    overlay.innerHTML = `<div class="fixed inset-0 flex items-start justify-center bg-black/70 z-50 overflow-y-auto">
        <section class="vod-popup-error" role="alert"><h2>Impossible de charger cette fiche</h2><p>Le service est momentanément indisponible. Tu peux réessayer ou revenir aux résultats.</p>
        <div class="vod-social-actions"><button type="button" data-popup-retry>Réessayer</button><button type="button" data-popup-error-close>Fermer</button></div></section></div>`;
    overlay.querySelector('[data-popup-retry]').onclick = retry;
    overlay.querySelector('[data-popup-error-close]').onclick = close;
};

(() => {
    'use strict';
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-watched-toggle]');
        if (!button || button.disabled) return;
        const container = button.closest('[data-content-actions], [data-history-card], [data-playlist-card]');
        const status = container?.querySelector('[data-watched-status], [data-action-status]');
        button.disabled = true;
        try {
            const response = await fetch('/watched/toggle', {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''}, body:JSON.stringify({tmdb_id:Number(button.dataset.id),type:button.dataset.type,title:button.dataset.title,year:button.dataset.year || null,poster:button.dataset.poster || null})});
            if (!response.ok) throw new Error('Impossible de modifier l’historique. Réessaie.');
            const result = await response.json();
            if (typeof result.watched !== 'boolean') throw new Error('Réponse invalide. Réessaie.');
            document.querySelectorAll('[data-watched-toggle]').forEach(other => {
                if (other.dataset.id !== button.dataset.id || other.dataset.type !== button.dataset.type) return;
                const label = result.watched ? 'Marquer comme non vu' : 'Marquer comme déjà vu';
                other.classList.toggle('is-active', result.watched); other.setAttribute('aria-pressed',String(result.watched)); other.setAttribute('aria-label',label); other.title=label;
                other.querySelector('.vod-action-label').textContent=result.watched?'Déjà vu':'Marquer comme vu';
            });
            try { Object.keys(sessionStorage).filter(key=>key.startsWith('vodfinder_results:') || key.startsWith('vodfinder_home')).forEach(key=>sessionStorage.removeItem(key)); } catch (_) {}
            document.dispatchEvent(new CustomEvent('vod:watched-changed',{detail:{id:Number(button.dataset.id),type:button.dataset.type,watched:result.watched}}));
            if (status) { status.hidden=false; status.textContent=result.watched?'Ajouté aux déjà vus.':'Retiré des déjà vus.'; }
            if (!result.watched && container?.matches('[data-history-card]')) container.remove();
        } catch (error) { if (status) { status.hidden=false; status.textContent=error.message; } }
        finally { button.disabled=false; }
    });
})();
