{{-- 
    PUPTracker Admin — Standard Toast Notification Container

    Include once per layout (after body content). Individual toasts can be
    triggered from JS via:
        window.PUPToast.success('Record saved.');
        window.PUPToast.error('Unable to save.');
        window.PUPToast.info('Filters applied — 18 records found.');
        window.PUPToast.warning('Email delivery failed.');

    Toasts are rendered client-side by admin-ui.js into this container.
--}}
<div id="adminToastContainer" class="admin-toast-container" aria-live="polite" aria-atomic="false">
    <div class="admin-toast-stack"></div>
</div>
