const CACHE_NAME = 'vodfinder-public-v3';
const PUBLIC_ASSETS = ['/offline.html', '/vod.css', '/assets/vod-ui.css', '/assets/vod-ui.js', '/icons/android/mipmap-xxxhdpi/w-watch.png'];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache=>cache.addAll(PUBLIC_ASSETS)).then(()=>self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key.startsWith('vodfinder-') && key!==CACHE_NAME).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const request=event.request;
    const url=new URL(request.url);
    if (request.method!=='GET' || url.origin!==self.location.origin) return;
    // Never cache authenticated pages, API responses or AJAX episode popups.
    if (request.mode==='navigate') {
        event.respondWith(fetch(request).catch(()=>caches.match('/offline.html')));
    } else if (PUBLIC_ASSETS.includes(url.pathname)) {
        event.respondWith(fetch(request).then(response=>{if(response.ok){const copy=response.clone();caches.open(CACHE_NAME).then(cache=>cache.put(request,copy));}return response;}).catch(()=>caches.match(request)));
    }
});
self.addEventListener('push', event => {
    let payload={title:'VOD Finder',body:'Une nouvelle alerte est disponible.',url:'/series',tag:'vod-alert'};
    try { if(event.data) payload={...payload,...event.data.json()}; } catch(_) {}
    event.waitUntil(self.registration.showNotification(payload.title,{body:payload.body,image:typeof payload.image==='string' && payload.image.startsWith('https://image.tmdb.org/t/p/') ? payload.image : undefined,icon:'/icons/android/mipmap-xxxhdpi/w-watch.png',tag:payload.tag,data:{url:(typeof payload.url==='string' && /^\/account\/(recommendations(?:\/\d+)?|contacts)$/.test(payload.url)) ? payload.url : '/series'}}));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const path=event.notification.data?.url;
    const safePath=typeof path==='string' && /^\/account\/(recommendations(?:\/\d+)?|contacts)$/.test(path)?path:'/series';
    const url=new URL(safePath,self.location.origin).href;
    event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(async clients=>{
        for(const client of clients) {if(new URL(client.url).origin===self.location.origin){await client.navigate(url);return client.focus();}}
        return self.clients.openWindow(url);
    }));
});
