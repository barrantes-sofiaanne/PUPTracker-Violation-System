
@php
    $menuAvatarText = $menuAvatar ?? mb_strtoupper(
        collect(explode(' ', (string) $menuUserName))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_substr($word, 0, 1))
            ->implode('')
    ) ?: 'U';
@endphp

<div class="user-menu dropdown">

    {{-- Account menu toggle --}}
    <button
        class="user-menu-toggle"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="true"
        aria-expanded="false"
        aria-label="Account menu">

        <span class="user-menu-avatar" aria-hidden="true">
            {{ $menuAvatarText }}
        </span>

        <span class="user-menu-toggle-label d-none d-md-inline">
            {{ $menuUserName }}
        </span>

        <span class="user-menu-toggle-sub d-none d-lg-inline">
            {{ $menuUserSub }}
        </span>
    </button>

    {{-- Account dropdown menu --}}
    <ul class="dropdown-menu dropdown-menu-end user-menu-dropdown shadow">

        <li>
            <div class="user-menu-head">
                <span class="user-menu-head-name">
                    {{ $menuUserName }}
                </span>

                <span class="user-menu-head-sub">
                    {{ $menuUserSub }}
                </span>
            </div>
        </li>

        <li>
            <hr class="dropdown-divider">
        </li>

        @foreach ($menuItems as $item)
            <li>
                <a
                    class="dropdown-item user-menu-item"
                    href="{{ $item['url'] }}">

                    <i class="bi {{ $item['icon'] }}"
                       aria-hidden="true"></i>

                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach

        <li>
            <hr class="dropdown-divider">
        </li>

        <li>
            <form
                action="{{ $menuLogoutUrl }}"
                method="POST"
                class="mb-0"
                data-puptracker-logout-form>

                @csrf

                <button
                    type="submit"
                    class="dropdown-item user-menu-item user-menu-logout">

                    <i class="bi bi-box-arrow-right"
                       aria-hidden="true"></i>

                    <span>Logout</span>
                </button>
            </form>
        </li>

    </ul>

</div>
