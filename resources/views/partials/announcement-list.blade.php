{{--
    Shared announcement list.

    The Student Announcements page is the SOURCE OF TRUTH for this layout:
    the hero header (title + subtitle), the flat card stack with `mb-4`
    spacing, and the empty state all mirror it exactly. Both the Student and
    Security announcement pages render this partial so their UI cannot drift
    apart again.

    Only TWO things differ per module, and both are passed in:

      $heroTitle    — the module's own real page title
                      ("Student Announcements" / "Security Announcements").
      $heroSubtitle — the module's own subtitle text.

    Everything visual — hero treatment, spacing, typography, card structure,
    empty state — is identical.

    `$heroActions` (optional) renders extra content inside the hero (e.g. a
    back-to-dashboard link). It is styled to sit naturally on the hero's
    maroon/gold gradient, and both the label and the icon are light so the
    link keeps sufficient contrast (WCAG).

    No authorization, retrieval, filtering or visibility logic lives here:
    each module's controller still supplies `$announcements` exactly as before.
--}}
@php
    $cardVariant = $cardVariant ?? 'student';
    $heroTitle = $heroTitle ?? 'Announcements';
    $heroSubtitle = $heroSubtitle ?? '';
@endphp

<div class="container-fluid py-1">

    {{-- Page Header — identical treatment for every module. --}}
    <div class="portal-hero mb-4">

        <div class="announcement-hero-row">

            <div class="announcement-hero-text">

                <h2 class="fw-bold mb-1">

                    {{ $heroTitle }}

                </h2>

                @if($heroSubtitle !== '')
                    <p class="mb-0">

                        {{ $heroSubtitle }}

                    </p>
                @endif

            </div>

            @isset($heroActions)
                <div class="announcement-hero-actions">
                    {{ $heroActions }}
                </div>
            @endisset

        </div>

    </div>

    @forelse($announcements as $announcement)

        @include('partials.announcement-card', [
            'announcement' => $announcement,
            'cardVariant' => $cardVariant,
        ])

    @empty

        <div class="card portal-card">

            <div class="card-body text-center py-5">

                <i class="bi bi-megaphone fs-1" style="color: var(--portal-goldenrod);" aria-hidden="true"></i>

                <h4 class="mt-3">

                    No announcements available.

                </h4>

            </div>

        </div>

    @endforelse

    @if(method_exists($announcements, 'hasPages') && $announcements->hasPages())

        <div class="mt-4">

            {{ $announcements->links() }}

        </div>

    @endif

</div>
