(() => {
    const viewer = document.getElementById('evidence-viewer');
    if (typeof viewer?.showModal !== 'function') return;

    const incidentCode = viewer.querySelector('[data-evidence-code]');
    const stage = viewer.querySelector('[data-evidence-stage]');
    const status = viewer.querySelector('[data-evidence-status]');
    let opener;
    let activeImage;

    document.querySelectorAll('[data-evidence-open]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            event.preventDefault();
            if (viewer.open) return;

            opener = link;
            incidentCode.textContent = link.dataset.evidenceOpen;
            status.textContent = 'Loading evidence…';
            status.hidden = false;
            activeImage?.remove();

            const image = new Image();
            image.alt = `Evidence for incident ${link.dataset.evidenceOpen}`;
            activeImage = image;

            image.addEventListener('load', () => {
                if (!viewer.open || activeImage !== image) return;

                status.hidden = true;
                stage.append(image);
            }, { once: true });

            image.addEventListener('error', () => {
                if (viewer.open && activeImage === image) {
                    status.textContent = 'This evidence image could not be loaded.';
                }
            }, { once: true });

            viewer.showModal();
            image.src = link.href;
        });
    });

    viewer.addEventListener('click', (event) => {
        if (event.target === viewer) viewer.close();
    });

    viewer.addEventListener('close', () => {
        if (viewer.open) return;

        activeImage?.remove();
        activeImage = null;
        opener?.focus({ preventScroll: true });
        opener = null;
    });
})();
