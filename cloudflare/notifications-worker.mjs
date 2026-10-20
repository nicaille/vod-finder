// Cloudflare dashboard: deploy this module, add the secret, then add * * * * *.
export async function runScheduled(env, request = fetch) {
    const token = env.CLOUDFLARE_CRON_TOKEN;
    if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) {
        throw new Error('CLOUDFLARE_CRON_TOKEN absent ou invalide');
    }
    const response = await request('https://app-vod.venoix.fr/api/internal/notifications/tick', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'User-Agent': 'VOD-Finder-Scheduler/1.0',
        },
        body: '{}',
        // Workers supports manual/follow only. Keep credentials on this URL;
        // any 3xx response is rejected by the response.ok check below.
        redirect: 'manual',
        signal: AbortSignal.timeout(55000),
    });
    // Never log response bodies, the bearer token, or user data.
    if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
        console.error('Cloudflare cron refusé', {status: response.status, ray: response.headers.get('cf-ray'), mitigated: response.headers.get('cf-mitigated')});
        throw new Error(`Ordonnanceur inaccessible (HTTP ${response.status})`);
    }
    const result = await response.json();
    if (result.ok !== true || !['processed', 'already-triggered'].includes(result.status)) {
        throw new Error('Réponse ordonnanceur invalide');
    }
    console.log('Cloudflare cron terminé', {status: result.status});
}

export default {
    async scheduled(controller, env) {
        await runScheduled(env);
    },
    async fetch() {
        return new Response('Not found', {status: 404});
    },
};
