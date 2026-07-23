<nav class="navbar navbar-expand bg-white shadow-sm mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-secondary me-3" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand mb-0 h5">Admin Panel</span>
        </div>
        <div class="d-flex align-items-center">
            <form action="{{ route('admin.logout') }}" method="POST" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>