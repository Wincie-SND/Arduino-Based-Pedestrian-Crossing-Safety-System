(() => {
    const panel = document.querySelector('[data-crossing-camera]');
    if (!panel) return;

    const toggle = panel.querySelector('[data-camera-toggle]');
    const video = panel.querySelector('[data-camera-video]');
    const placeholder = panel.querySelector('[data-camera-placeholder]');
    const status = panel.querySelector('[data-camera-status]');
    const deviceStatus = document.querySelector('[data-camera-device-status]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let activeSession = null;

    function showStatus(message) {
        if (status.textContent !== message) status.textContent = message;
    }

    function showDeviceStatus(online) {
        if (!deviceStatus) return;

        deviceStatus.classList.toggle('device-online', online);
        deviceStatus.classList.toggle('device-offline', !online);
        deviceStatus.textContent = online ? 'Online' : 'Offline';
    }

    function stopCamera(message = 'Camera is off.') {
        if (!activeSession) return;

        const session = activeSession;
        activeSession = null;
        clearTimeout(session.timer);
        session.controller.abort();
        session.stream?.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
        video.hidden = true;
        placeholder.hidden = false;
        toggle.textContent = 'Start camera';
        toggle.classList.remove('secondary');
        showDeviceStatus(false);
        showStatus(message);
    }

    async function requestCameraEndpoint(session, url, snapshot) {
        const request = new AbortController();
        const cancel = () => request.abort();
        const headers = { Accept: 'application/json' };
        let body;

        if (snapshot) {
            body = new FormData();
            body.append('snapshot', snapshot, 'snapshot.jpg');
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        session.controller.signal.addEventListener('abort', cancel);
        const timeout = setTimeout(cancel, 4000);

        try {
            const response = await fetch(url, {
                method: snapshot ? 'POST' : 'GET',
                headers,
                body,
                cache: 'no-store',
                mode: 'same-origin',
                credentials: 'same-origin',
                redirect: 'error',
                signal: request.signal,
            });
            const data = response.ok && !snapshot ? await response.json() : null;

            return { code: response.status, data };
        } finally {
            clearTimeout(timeout);
            session.controller.signal.removeEventListener('abort', cancel);
        }
    }

    function drawSnapshot() {
        const { videoWidth, videoHeight } = video;
        if (!videoWidth || !videoHeight || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
            throw new Error('The preview does not have a camera frame.');
        }

        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1280 / Math.max(videoWidth, videoHeight));
        canvas.width = Math.max(1, Math.round(videoWidth * scale));
        canvas.height = Math.max(1, Math.round(videoHeight * scale));
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        return new Promise((resolve, reject) => {
            canvas.toBlob((snapshot) => {
                if (snapshot?.type === 'image/jpeg') resolve(snapshot);
                else reject(new Error('Could not make a JPEG snapshot.'));
            }, 'image/jpeg', 0.85);
        });
    }

    function stopIfAccessExpired(code) {
        if (![401, 403, 419].includes(code)) return false;

        stopCamera('Camera stopped. Reload the dashboard to restore access.');
        return true;
    }

    async function pollIncidents(session) {
        try {
            const endpoint = new URL(panel.dataset.incidentsUrl, window.location.href);
            if (session.after !== null) endpoint.searchParams.set('after', session.after);

            const { code, data } = await requestCameraEndpoint(session, endpoint);
            if (activeSession !== session || stopIfAccessExpired(code)) return;
            if (code !== 200) throw new Error('Could not check for incidents.');

            if (session.after === null) {
                session.after = data.latest_incident_id;
            } else {
                const eligibleIds = new Set(data.incidents.map(({ id }) => id));
                for (const id of session.snapshots.keys()) {
                    if (!eligibleIds.has(id)) session.snapshots.delete(id);
                }

                let newSnapshot;
                for (const { id } of data.incidents) {
                    if (session.snapshots.has(id)) continue;

                    newSnapshot ??= await drawSnapshot();
                    if (activeSession !== session) return;
                    session.snapshots.set(id, newSnapshot);
                }

                for (const { id, evidence_url: evidenceUrl } of data.incidents) {
                    const upload = await requestCameraEndpoint(session, evidenceUrl, session.snapshots.get(id));
                    if (activeSession !== session || stopIfAccessExpired(upload.code)) return;
                    if (upload.code !== 204 && upload.code !== 404) throw new Error('Could not save the snapshot.');

                    if (upload.code === 204) session.savedSnapshot = true;
                    session.after = id;
                    session.snapshots.delete(id);
                }
            }

            showStatus(session.savedSnapshot
                ? 'Camera is live. Snapshot saved to Violation Reports.'
                : 'Camera is live and ready for new incidents.');
        } catch {
            if (activeSession === session) showStatus('Camera is live. Retrying incident capture.');
        } finally {
            if (activeSession === session) session.timer = setTimeout(() => pollIncidents(session), 1000);
        }
    }

    async function startCamera() {
        const session = {
            controller: new AbortController(),
            stream: null,
            timer: null,
            after: null,
            snapshots: new Map(),
            savedSnapshot: false,
        };
        activeSession = session;
        toggle.textContent = 'Stop camera';
        toggle.classList.add('secondary');
        showStatus('Waiting for camera permission…');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            if (activeSession !== session) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }

            session.stream = stream;
            for (const track of stream.getVideoTracks()) {
                track.addEventListener('ended', () => stopCamera('The camera disconnected.'), {
                    signal: session.controller.signal,
                });
            }

            video.srcObject = stream;
            video.hidden = false;
            placeholder.hidden = true;
            showStatus('Starting the camera preview…');
            await video.play();

            if (activeSession === session) {
                showDeviceStatus(true);
                showStatus('Camera is live. Connecting incident capture…');
                void pollIncidents(session);
            }
        } catch (error) {
            if (activeSession !== session) return;

            const messages = {
                NotAllowedError: 'Camera permission was denied. Allow access in your browser and try again.',
                NotFoundError: 'No webcam was found. Connect one and try again.',
                NotReadableError: 'The webcam could not be opened. Close other apps using it and try again.',
            };
            stopCamera(messages[error.name] ?? 'The camera could not start. Try again.');
        }
    }

    if (!window.isSecureContext) {
        showStatus('Open the dashboard on HTTPS or localhost to use a webcam.');
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        showStatus('This browser does not support webcam access.');
        return;
    }

    toggle.disabled = false;
    toggle.addEventListener('click', () => activeSession ? stopCamera() : void startCamera());
    window.addEventListener('pagehide', () => stopCamera());
})();
