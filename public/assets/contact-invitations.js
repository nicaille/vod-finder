(() => {
    'use strict';
    const root = document.querySelector('[data-contact-invite]');
    if (!root) return;
    const find = selector => root.querySelector(selector);
    const status = find('[data-invite-status]');
    const email = find('[data-invite-email-input]');
    const phone = find('[data-invite-phone]');
    const link = find('[data-invite-url]');
    const picker = find('[data-contact-picker]');
    const share = find('[data-invite-share]');
    const sms = find('[data-invite-sms]');
    const say = message => { status.textContent = message; };
    let busy = false;
    const message = 'Rejoins-moi sur VOD Finder pour échanger des recommandations de films et séries. Invitation valable 7 jours : ';
    function validUrl(value) {
        try {
            const url = new URL(value);
            return url.origin === location.origin && /^\/invite\/[a-f0-9]{64}$/.test(url.pathname) && !url.search && !url.hash;
        } catch (_) { return false; }
    }
    function refreshActions() {
        const ready = validUrl(link.value);
        find('[data-invite-result]').hidden = !ready;
        share.hidden = !ready || typeof navigator.share !== 'function';
        sms.hidden = !ready;
        if (ready) {
            const number = phone.value.replace(/[\s().-]/g, '');
            const target = /^\+?\d{6,18}$/.test(number) ? number : '';
            sms.href = `sms:${target}?body=${encodeURIComponent(message + link.value)}`;
        }
    }
    phone.addEventListener('input', refreshActions);
    function choices(values, select, wrapper, input) {
        const clean = [...new Set((Array.isArray(values) ? values : []).filter(value => typeof value === 'string' && value.length <= 255))];
        select.replaceChildren();
        for (const value of clean) select.add(new Option(value, value));
        wrapper.hidden = clean.length < 2;
        input.value = clean[0] || '';
        select.onchange = () => { input.value = select.value; refreshActions(); };
    }
    const canPick = window.isSecureContext && navigator.contacts && typeof navigator.contacts.select === 'function';
    if (canPick) {
        let properties = ['email', 'tel'];
        const activate = available => {
            properties = properties.filter(property => available.includes(property));
            picker.hidden = !properties.length;
            if (properties.length) find('[data-contact-picker-help]').textContent = 'Seules les coordonnées du contact que tu choisis sont accessibles. Son numéro reste sur cet appareil.';
        };
        if (typeof navigator.contacts.getProperties === 'function') {
            navigator.contacts.getProperties().then(activate).catch(() => {});
        } else activate(properties);
        picker.addEventListener('click', async () => {
            try {
                // Call from the tap, before network work: the browser requires user activation.
                const selected = await navigator.contacts.select(properties, {multiple: false});
                if (!Array.isArray(selected) || !selected[0]) { say('Aucun contact sélectionné.'); return; }
                choices(selected[0].email, find('[data-contact-emails]'), find('[data-contact-email-choice]'), email);
                choices(selected[0].tel, find('[data-contact-phones]'), find('[data-contact-phone-choice]'), phone);
                refreshActions();
                say(email.value || phone.value ? 'Coordonnées sélectionnées. Choisis comment envoyer ton invitation.' : 'Ce contact n’a pas de coordonnées disponibles. Tu peux saisir une adresse ou partager un lien.');
            } catch (error) {
                say(error.name === 'AbortError' ? 'Sélection annulée. Aucun message envoyé.' : 'Le répertoire n’est pas accessible. Tu peux saisir les coordonnées ou partager un lien.');
            }
        });
    }
    async function create(event, channel) {
        event.preventDefault();
        if (busy) return;
        busy = true;
        const button = event.currentTarget.querySelector('button[type="submit"]');
        button.disabled = true;
        say('Préparation de l’invitation…');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            // Deliberately exclude the phone number, contact name and address book.
            const payload = {channel, ...(channel === 'email' ? {email: email.value.trim()} : {})};
            const response = await fetch(event.currentTarget.action, {method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(payload)});
            const type = response.headers.get('content-type') || '';
            const data = type.includes('application/json') ? await response.json() : null;
            if (!response.ok) {
                if (response.status === 429) throw new Error('Trop d’invitations. Réessaie plus tard.');
                if ([401, 419].includes(response.status) || response.redirected) throw new Error('Ta session a expiré. Recharge la page et reconnecte-toi.');
                const fieldError = data?.errors ? Object.values(data.errors).flat().find(value => typeof value === 'string') : null;
                throw new Error(fieldError || (response.status === 503 ? data?.message : null) || `Invitation non envoyée (HTTP ${response.status}).`);
            }
            if (!data) throw new Error('Réponse inattendue. Recharge la page avant de réessayer.');
            if (channel === 'link') {
                if (!validUrl(data.url)) throw new Error('Lien invalide. Recharge la page avant de réessayer.');
                link.value = data.url;
                refreshActions();
            }
            say(data.message || 'Invitation préparée.');
        } catch (error) {
            say(error.name === 'AbortError' ? 'Le serveur met trop de temps à répondre. Vérifie avant de renvoyer une invitation par e-mail.' : (error.message || 'Invitation non envoyée. Réessaie plus tard.'));
        } finally { clearTimeout(timeout); busy = false; button.disabled = false; }
    }
    find('[data-invite-link]').addEventListener('submit', event => create(event, 'link'));
    find('[data-invite-email]').addEventListener('submit', event => create(event, 'email'));
    find('[data-invite-copy]').addEventListener('click', async () => {
        if (!validUrl(link.value)) return;
        try { await navigator.clipboard.writeText(link.value); say('Lien copié.'); }
        catch (_) { link.focus(); link.select(); say('Copie le lien sélectionné.'); }
    });
    share.addEventListener('click', async () => {
        if (!validUrl(link.value)) return;
        try {
            await navigator.share({title: 'Invitation VOD Finder', text: 'Échangeons des recommandations de films et séries.', url: link.value});
            say('Le lien a été transmis à l’application de partage choisie.');
        } catch (error) { say(error.name === 'AbortError' ? 'Partage annulé. Tu peux réutiliser le lien.' : 'Le partage n’est pas disponible. Copie le lien ou prépare un SMS.'); }
    });
    refreshActions();
})();
