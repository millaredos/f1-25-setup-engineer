/** Update notifications are advisory; checking never installs or changes the user's channel. */
async function refreshUpdate() {
    if (document.hidden || document.body.dataset.page === 'install') { return; }
    try {
        const response = await fetch('?api=update-status', { cache: 'no-store' });
        if (!response.ok) { return; }
        const data = await response.json();
        const indicator = document.querySelector('#update-indicator');
        indicator.hidden = !data.available;
        indicator.textContent = data.available ? ` · ${data.available}` : '';
    } catch { /* Notifications never block the workshop when the network is unavailable. */ }
}

// The workshop establishes its session first; other pages already receive it from the server.
document.addEventListener('setup-ready', refreshUpdate);
if (!['workshop', 'circuits', 'tests'].includes(document.body.dataset.page)) { refreshUpdate(); }
document.addEventListener('visibilitychange', refreshUpdate);
setInterval(refreshUpdate, 300000);
