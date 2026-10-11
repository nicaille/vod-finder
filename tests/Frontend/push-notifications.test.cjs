const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function worker() {
    const events = {};
    const notifications = [];
    const opened = [];
    const self = {
        location: {origin: 'https://app-vod.example.test'},
        addEventListener: (name, handler) => {events[name] = handler;},
        registration: {showNotification: async (title, options) => notifications.push({title, options})},
        clients: {matchAll: async () => [], openWindow: async url => opened.push(url)}
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/service-worker.js'), 'utf8'), {self, URL});
    return {events, notifications, opened};
}

test('a real push event displays the test and opens the account when tapped', async () => {
    const {events, notifications, opened} = worker();
    let pending;
    events.push({data: {json: () => ({title: 'Test', body: 'Delivered', url: '/account', tag: 'unique-test'})}, waitUntil: promise => {pending = promise;}});
    await pending;
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].options.body, 'Delivered');
    assert.equal(notifications[0].options.tag, 'unique-test');
    events.notificationclick({notification: {data: notifications[0].options.data, close() {}}, waitUntil: promise => {pending = promise;}});
    await pending;
    assert.equal(opened[0], 'https://app-vod.example.test/account');
});

test('notification clicks cannot navigate to external URLs or private admin routes', async () => {
    for (const url of ['https://evil.test', '//evil.test', '/admin', '/account/../admin', '/account?next=https://evil.test']) {
        const {events, opened} = worker();
        let pending;
        events.notificationclick({notification: {data: {url}, close() {}}, waitUntil: promise => {pending = promise;}});
        await pending;
        assert.equal(opened[0], 'https://app-vod.example.test/series');
    }
});

test('permission is requested directly from the tap before any network request', async () => {
    const calls = [];
    let enableHandler;
    const enable = {addEventListener: (_, handler) => {enableHandler = handler;}};
    const status = {};
    const source = fs.readFileSync(path.join(__dirname, '../../public/assets/vod-ui.js'), 'utf8').split('// Select the next available')[0];
    const registration = {pushManager: {getSubscription: async () => null}};
    vm.runInNewContext(source, {
        window: {isSecureContext: true, PushManager: {}, Notification: {}, addEventListener() {}},
        navigator: {serviceWorker: {register: async () => {}, ready: Promise.resolve(registration)}},
        Notification: {permission: 'default', requestPermission: async () => {calls.push('permission'); return 'denied';}},
        matchMedia: () => ({matches: false}),
        document: {addEventListener() {}, querySelector: selector => selector === '[data-enable-push]' ? enable : selector === '[data-push-status]' ? status : null},
        fetch: async () => {calls.push('network'); return {ok: true, json: async () => ({publicKey: 'test'})};}
    });
    assert.deepEqual(calls, [], 'Loading the page must not request browser permission.');
    await enableHandler();
    assert.deepEqual(calls, ['permission']);
    assert.match(status.textContent, /Autorisation refusée/);
    assert.equal(enable.disabled, false);
});

test('signup acceptance registers the current device and asks to save its notification channel', async () => {
    const requests = [];
    let enableHandler;
    let subscription = null;
    const checkbox = {checked: false};
    const status = {};
    const permission = {permission: 'default', requestPermission: async () => {permission.permission = 'granted'; return 'granted';}};
    const enable = {addEventListener: (_, handler) => {enableHandler = handler;}};
    const registration = {pushManager: {
        getSubscription: async () => subscription,
        subscribe: async () => {
            subscription = {endpoint: 'https://fcm.googleapis.com/fcm/send/current', toJSON() {return {endpoint: this.endpoint, keys: {p256dh: 'test', auth: 'test'}};}};
            return subscription;
        }
    }};
    const elements = {'[data-enable-push]': enable, '[data-push-status]': status,
        '[data-push-onboarding]': {}, '#notify_web': checkbox, '#notify_opt_in': {checked: true}};
    const source = fs.readFileSync(path.join(__dirname, '../../public/assets/vod-ui.js'), 'utf8').split('// Select the next available')[0];
    vm.runInNewContext(source, {
        window: {isSecureContext: true, PushManager: {}, Notification: permission, addEventListener() {}},
        navigator: {serviceWorker: {register: async () => {}, ready: Promise.resolve(registration)}},
        Notification: permission, Uint8Array, atob,
        matchMedia: () => ({matches: false}),
        document: {addEventListener() {}, querySelector: selector => elements[selector] || null},
        fetch: async (url, options) => {
            requests.push({url, body: options.body ? JSON.parse(options.body) : null});
            return {ok: true, json: async () => url.endsWith('/key') ? {publicKey: 'A'.repeat(87)} : {registered: true, success: true}};
        }
    });
    await enableHandler();
    const sent = requests.find(request => request.url === '/notifications/push');
    assert.equal(sent.body.endpoint, 'https://fcm.googleapis.com/fcm/send/current');
    assert.equal(sent.body.enable_channel, true);
    assert.equal(checkbox.checked, true);
    assert.match(status.textContent, /autorisées et activées/);
    assert.equal(requests.some(request => request.url.endsWith('/test')), false);
});

test('the installed-app admin test stays visible and becomes enabled only for a permitted registered device', async () => {
    const cases = [
        {permission: 'default', subscription: false, registered: false, ready: false},
        {permission: 'denied', subscription: true, registered: true, ready: false},
        {permission: 'granted', subscription: true, registered: false, ready: false},
        {permission: 'granted', subscription: true, registered: true, ready: true},
        {permission: 'granted', subscription: false, registered: false, supported: false, ready: false}
    ];
    const source = fs.readFileSync(path.join(__dirname, '../../public/assets/vod-ui.js'), 'utf8').split('// Select the next available')[0];
    for (const state of cases) {
        const button = {hidden: false, disabled: true, addEventListener() {}};
        const status = {};
        const permission = {permission: state.permission};
        const registration = {pushManager: {getSubscription: async () => state.subscription ? {endpoint: 'https://fcm.googleapis.com/fcm/send/current'} : null}};
        const window = {isSecureContext: true, Notification: permission, addEventListener() {}};
        if (state.supported !== false) window.PushManager = {};
        const elements = {'[data-enable-push]': {addEventListener() {}}, '[data-test-push]': button, '[data-push-status]': status};
        vm.runInNewContext(source, {
            window, Notification: permission,
            navigator: {standalone: true, serviceWorker: {register: async () => {}, ready: Promise.resolve(registration)}},
            matchMedia: () => ({matches: true}),
            document: {addEventListener() {}, querySelector: selector => elements[selector] || null},
            fetch: async () => ({ok: true, json: async () => ({registered: state.registered})})
        });
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(button.hidden, false, JSON.stringify(state));
        assert.equal(button.disabled, !state.ready, JSON.stringify(state));
        assert.ok(status.textContent, 'The user must see the device state.');
    }
});
