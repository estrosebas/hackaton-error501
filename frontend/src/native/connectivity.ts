// Updated both by WebView network events and the native Network bridge.
export let connectionOffline = !navigator.onLine
window.addEventListener('offline', () => { connectionOffline = true })
window.addEventListener('online', () => { connectionOffline = false })
