const CACHE = 'schedule-shell-v14'
const SHELL = ['/', '/index.html', '/style.css', '/app.js', '/manifest.webmanifest', '/pwa-icon.svg']
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL))); self.skipWaiting() })
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('schedule-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())) })
self.addEventListener('fetch', e => { const r=e.request,u=new URL(r.url); if(r.method!=='GET'||u.origin!==location.origin||u.pathname.startsWith('/api.php'))return; if(r.mode==='navigate'){e.respondWith(fetch(r).then(res=>{caches.open(CACHE).then(c=>c.put('/index.html',res.clone()));return res}).catch(()=>caches.match('/index.html')));return} e.respondWith(caches.match(r).then(c=>c||fetch(r).then(res=>{if(res.ok)caches.open(CACHE).then(cache=>cache.put(r,res.clone()));return res}))) })
