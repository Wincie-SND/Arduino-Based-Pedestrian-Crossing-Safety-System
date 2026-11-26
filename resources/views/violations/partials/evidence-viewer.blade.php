<dialog class="evidence-viewer" id="evidence-viewer" aria-labelledby="evidence-viewer-title">
    <header class="evidence-viewer-header">
        <div>
            <h2 id="evidence-viewer-title">Incident evidence</h2>
            <p data-evidence-code></p>
        </div>
        <form method="dialog">
            <button class="evidence-viewer-close" type="submit" aria-label="Close evidence viewer" autofocus>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </form>
    </header>
    <div class="evidence-viewer-stage" data-evidence-stage>
        <p data-evidence-status role="status"></p>
    </div>
</dialog>

@push('scripts')
<script src="{{ asset('js/evidence-viewer.js') }}?v={{ filemtime(public_path('js/evidence-viewer.js')) }}" defer></script>
@endpush
