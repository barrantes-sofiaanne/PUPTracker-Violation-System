<aside class="sidebar">

    <div class="sidebar-logo">

        <img
            src="{{ asset('assets/images/PUP_logo.png') }}"
            alt="PUP Logo">

        <h4>PUPTracker</h4>

    </div>

    <ul>

        <li>
            <a href="{{ route('student.dashboard') }}">
                Dashboard
            </a>
        </li>

        <li>
            <a href="#">
                My Records
            </a>
        </li>

        <li>
            <a href="#">
                Announcements
            </a>
        </li>

        <li>
            <a href="#">
                Account
            </a>
        </li>

    </ul>

    <form action="{{ route('logout') }}" method="POST">

        @csrf

        <button class="logout-btn">

            Logout

        </button>

    </form>

</aside>