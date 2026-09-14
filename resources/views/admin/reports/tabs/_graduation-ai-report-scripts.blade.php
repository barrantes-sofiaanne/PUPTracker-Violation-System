@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Prevent duplicate AI batch requests + show loading state.
        const batchForm = document.getElementById('aiBatchForm');
        const batchBtn = document.getElementById('aiBatchGenerateBtn');
        if (batchForm && batchBtn) {
            batchForm.addEventListener('submit', function (event) {
                if (batchBtn.disabled) {
                    event.preventDefault();
                    return;
                }
                window.PUPLoading && window.PUPLoading.start('aiReviewLoading');
                batchBtn.disabled = true;
            });
        }

        const panel = document.getElementById('batchProgressPanel');
        if (!panel) {
            return;
        }

        const bar = document.getElementById('batchProgressBar');
        const text = document.getElementById('batchProgressText');
        const completedEl = document.getElementById('batchCompleted');
        const remainingEl = document.getElementById('batchRemaining');
        const failedEl = document.getElementById('batchFailed');

        const batchId = panel.dataset.batchId || '';
        const progressUrl = panel.dataset.progressUrl || '';

        if (!batchId) {
            return;
        }

        panel.classList.remove('d-none');

        const url = progressUrl + '?batch_id=' + encodeURIComponent(batchId);

        const timer = setInterval(async function () {
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (!data.available) {
                    clearInterval(timer);
                    text.textContent = 'No active batch.';
                    return;
                }

                bar.style.width = data.percent + '%';
                text.textContent = data.percent + '%';
                completedEl.textContent = data.processed;
                remainingEl.textContent = data.remaining;
                failedEl.textContent = data.failed;

                if (data.finished) {
                    clearInterval(timer);
                    text.textContent = 'Batch finished (' + data.percent + '%)';
                    bar.classList.remove('progress-bar-animated');
                }
            } catch (error) {
                // ignore transient polling errors
            }
        }, 2000);
    });
</script>
@endpush
