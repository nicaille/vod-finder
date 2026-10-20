const {test} = require('node:test');
const assert = require('node:assert/strict');
const workerModule = import('../../cloudflare/notifications-worker.mjs');

const env = {CLOUDFLARE_CRON_TOKEN: 'a'.repeat(64)};
test('cron calls only the fixed HTTPS route with authentication and no redirects', async () => {
    const {default: worker, runScheduled} = await workerModule;
    await runScheduled(env, async (url, options) => {
        assert.equal(url, 'https://app-vod.venoix.fr/api/internal/notifications/tick');
        assert.equal(options.method, 'POST');
        assert.equal(options.headers.Authorization, `Bearer ${env.CLOUDFLARE_CRON_TOKEN}`);
        assert.equal(options.redirect, 'manual');
        assert.ok(options.signal instanceof AbortSignal);
        return Response.json({ok: true, status: 'processed'});
    });
    assert.equal((await worker.fetch()).status, 404);
});
test('missing secrets prevent the HTTP call', async () => {
    const {default: worker, runScheduled} = await workerModule;
    let calls = 0;
    await assert.rejects(runScheduled({}, async () => { calls++; }), /absent ou invalide/);
    assert.equal(calls, 0);
});
test('Cloudflare challenges, delivery errors and unexpected JSON fail visibly', async () => {
    const {default: worker, runScheduled} = await workerModule;
    for (const response of [
        new Response('<html>Challenge</html>', {status: 403, headers: {'cf-mitigated': 'challenge'}}),
        new Response('<html>Login</html>', {status: 200}),
        Response.json({ok: false}, {status: 503}),
        Response.json({ok: true, status: 'unknown'}),
    ]) await assert.rejects(runScheduled(env, async () => response));
});
test('an already triggered minute is an acknowledged idempotent result', async () => {
    const {default: worker, runScheduled} = await workerModule;
    await runScheduled(env, async () => Response.json({ok: true, status: 'already-triggered'}));
});

test('redirect responses fail without a second request or parsing a success body', async () => {
    const {runScheduled} = await workerModule;
    for (const status of [301, 302, 303, 307, 308]) {
        let calls = 0;
        const response = new Response(null, {status, headers: {location: 'https://other.example/private'}});
        response.json = () => { throw new Error('Redirect body must not be parsed'); };
        await assert.rejects(runScheduled(env, async (url, options) => {
            calls++;
            assert.equal(options.redirect, 'manual');
            return response;
        }), new RegExp(`HTTP ${status}`));
        assert.equal(calls, 1);
    }
});
