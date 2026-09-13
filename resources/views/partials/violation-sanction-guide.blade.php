{{--
    Shared "Violation Code & Sanctions Guide".

    ONE implementation, rendered by BOTH the Student Dashboard and the Security
    Dashboard, so the two can never show different violation/sanction
    information. The Student Dashboard was the source of truth; this partial is
    that exact markup, parameterised only for the two cosmetic bits the two
    dashboards legitimately differ on:

      $guideTitle — the card heading (Student keeps "Violation Code & Sanctions
                    Guide"; Security uses "Violation and Sanction Guide").
      $guideId    — a DOM id suffix, so the accordion's ids/collapse targets
                    stay unique if the partial is ever rendered twice on one
                    page.

    The DATA is not configurable: it always comes from $categories, which both
    controllers already load via
        ViolationCategory::with('violationTypes.disciplinarySanctions')->get()
    so every dashboard shows the same handbook content from the same source.
--}}
@php
    $guideId = $guideId ?? 'handbook';
    $guideTitle = $guideTitle ?? 'Violation Code & Sanctions Guide';
    $guideAccordionId = $guideId . 'Accordion';
@endphp

<div class="card portal-card">
    <div class="card-header card-header-custom">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-book me-2" aria-hidden="true"></i>{{ $guideTitle }}
        </h5>
    </div>
    <div class="card-body">
        <div class="accordion" id="{{ $guideAccordionId }}">
            @forelse($categories as $category)
                <div class="accordion-item border-0 mb-2">
                    <h2 class="accordion-header">
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $guideId }}Category{{ $category->violation_category_id }}"
                            aria-expanded="false">
                            <span class="portal-badge maroon me-2">{{ $category->violationTypes->count() }}</span>
                            {{ $category->category_name }}
                        </button>
                    </h2>
                    <div
                        id="{{ $guideId }}Category{{ $category->violation_category_id }}"
                        class="accordion-collapse collapse"
                        data-bs-parent="#{{ $guideAccordionId }}">
                        <div class="accordion-body pt-0">
                            @forelse($category->violationTypes as $type)
                                <div class="mb-4 pb-3 border-bottom">
                                    <h6 class="fw-bold portal-section-title mb-2">
                                        {{ $type->violation_type }}
                                    </h6>
                                    <p class="text-muted small mb-3">
                                        {{ $type->violation_description }}
                                    </p>
                                    <div class="portal-table-wrap">
                                        <table class="table table-sm table-bordered portal-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="text-center" style="width: 40%;">Offense Level</th>
                                                    <th>Sanction</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($type->disciplinarySanctions as $sanction)
                                                    <tr>
                                                        <td class="text-center">
                                                            <span class="portal-badge gold">
                                                                {{ $sanction->offense_level }}
                                                            </span>
                                                        </td>
                                                        <td>{{ $sanction->disciplinary_sanction }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted small">No sanctions defined</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted">No violation types in this category.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-info">
                    No violation categories available.
                </div>
            @endforelse
        </div>
    </div>
</div>
