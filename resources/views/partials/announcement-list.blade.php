{{--
    Shared announcement list — ONE layout for every module.

    Renders the hero header, the card stack and the empty state. Both the
    Student and Security announcement pages include this partial, so their UI
    is literally the same code rather than two approximations.

    Only these values differ per module, and all are supplied by the page:

      $heroTitle     — the module's own real page title.
      $heroSubtitle  — the module's own subtitle text.
      $heroActions   — optional trailing hero content (e.g. a module-specific
                       navigation link). Rendered inside the hero.

    No authorization, retrieval, filtering or visibility logic lives here:
    each module's controller still supplies `$announcements` exactly as before,
    so Security and Student keep their separate permissions and data handling.
--}}
@php
    $heroTitle = $heroTitle ?? 'Announcements';
    $heroSubtitle = $heroSubtitle ?? '';
@endphp

<div class="container-fluid py-1">

    {{-- Page header / banner — same treatment for every module. --}}
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

        @include('partials.announcement-card', ['announcement' => $announcement])

    @empty

        <div class="card portal-card">

            <div class="card-body text-center py-5">

                <i class="bi bi-megaphone fs-1 announcement-empty-icon" aria-hidden="true"></i>

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
