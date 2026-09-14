{{--
    Reports tab — deferred-content placeholder.

    Bootstrap switches tabs client-side, but each tab's dataset is only loaded
    when that tab is requested (?tab=...). Rendering an empty pane for a tab whose
    data has not been fetched looks like a broken page, so this partial explains
    the state and gives the user a one-click way to load it.

    Note for future edits: do NOT remove this in favour of omitting the pane
    entirely — an omitted pane is genuinely blank when Bootstrap switches to it.
--}}
<div class="text-center py-5 text-muted">
    <i class="bi bi-hourglass-split display-5 d-block mb-3"></i>
    <p class="mb-3">This tab&rsquo;s content loads when you open it.</p>
    <a href="#" class="admin-btn admin-btn-neutral" data-reports-load-tab>
        <i class="bi bi-arrow-clockwise me-1"></i>
        Load this tab
    </a>
</div>
