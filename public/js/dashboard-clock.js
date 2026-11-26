(() => {
    const clock = document.querySelector('[data-dashboard-clock]');
    if (!clock) return;

    const serverTime = Date.parse(clock.dateTime);
    if (!Number.isFinite(serverTime)) return;

    const startedAt = performance.getEntriesByType('navigation')[0]?.responseStart || performance.now();
    const timeZone = clock.dataset.timeZone;
    const dateText = clock.querySelector('[data-clock-date]');
    const timeText = clock.querySelector('[data-clock-time]');
    const dateFormatter = new Intl.DateTimeFormat('en-US', { timeZone, month: 'short', day: 'numeric', year: 'numeric' });
    const timeFormatter = new Intl.DateTimeFormat('en-US', { timeZone, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });

    const updateClock = () => {
        const currentTime = new Date(serverTime + performance.now() - startedAt);
        dateText.textContent = dateFormatter.format(currentTime);
        timeText.textContent = timeFormatter.format(currentTime);
        clock.dateTime = currentTime.toISOString();
        window.setTimeout(updateClock, 1000 - currentTime.getMilliseconds());
    };

    updateClock();
})();
