{{--
    Shared account user-menu (avatar icon in the topbar opens a dropdown
    containing Profile/Settings links and a Logout button).

    Expected variables:
      $menuUserName    : display name (string)
      $menuUserSub     : secondary line — email / student number / module label
      $menuItems       : array of ['label' => ..., 'url' => ..., 'icon' => ...]
      $menuLogoutUrl   : POST url for the logout form
      $menuAvatar      : optional initials override (defaults to first letters of the name)
--}}
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
