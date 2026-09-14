@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const program = document.getElementById('programSelect');
    const graduatingWrap = document.getElementById('graduatingYearsWrap');
    const previewBtn = document.getElementById('previewGraduatingBtn');
    const previewWrap = document.getElementById('graduatingPreviewWrap');
    const previewBody = document.getElementById('graduatingPreviewBody');
    const previewSummary = document.getElementById('previewSummary');
    const ladderizedWrap = document.getElementById('ladderizedWrap');

    // Specific-student search controls.
    const studentSearchInput = document.getElementById('studentSearchInput');
    const studentSearchResults = document.getElementById('studentSearchResults');
    const addSpecificStudentBtn = document.getElementById('addSpecificStudentBtn');
    const clearStudentSearchBtn = document.getElementById('clearStudentSearchBtn');
    const selectedSpecificStudentsContainer = document.getElementById('selectedSpecificStudents');

    // Read server-side data from data-* attributes (no Blade directives in JS).
    const previewUrl = previewBtn.dataset.previewUrl || '';
    const searchStudentsUrl = previewBtn.dataset.searchUrl || '';
    let ladderizedPrograms = [];
    try {
        ladderizedPrograms = JSON.parse(program.dataset.ladderizedPrograms || '[]') || [];
    } catch (e) {
        ladderizedPrograms = [];
    }
    ladderizedPrograms = ladderizedPrograms.map(function (name) { return String(name).toUpperCase(); });

    let selectedStudents = []; // { student_number, label }

    // Students removed from the graduating preview for THIS report only.
    // Preview-only state — the underlying student records are untouched.
    let excludedStudents = [];
    const excludedInputs = document.getElementById('excludedStudentsInputs');

    function renderExcludedInputs() {
        if (!excludedInputs) return;
        excludedInputs.innerHTML = excludedStudents.map(function (n) {
            return '<input type="hidden" name="excluded_students[]" value="' + n + '">';
        }).join('');
    }

    // Load any previously selected specific students from old input.
    document.querySelectorAll('#selectedSpecificStudents input[name="additional_students[]"]').forEach(function (input) {
        if (input.value && !selectedStudents.some(function (s) { return s.student_number === input.value; })) {
            selectedStudents.push({ student_number: input.value, label: input.value });
        }
    });
    renderSelectedStudents();

    function isLadderizedProgram() {
        const option = program?.selectedOptions[0];
        const name = (option?.dataset.programName || '').toUpperCase();
        return ladderizedPrograms.indexOf(name) !== -1;
    }

    function getSelectedLadderized() {
        if (!ladderizedWrap || ladderizedWrap.classList.contains('d-none')) {
            return null;
        }
        const checked = document.querySelector('input[name="ladderized"]:checked');
        if (!checked || checked.value === '') {
            return null;
        }
        return checked.value;
    }

    function updateMode() {
        // Only the graduating workflow exists in this feature, so the graduating
        // field group is always shown. There is no mode selector to react to —
        // the retired regular (Program Sanction Report) mode had the year input.
        graduatingWrap?.classList.remove('d-none');
        // Ladderized radio shows only for BSOA/BSIT.
        if (ladderizedWrap) {
            ladderizedWrap.classList.toggle('d-none', !isLadderizedProgram());
        }
    }

    // ---- Specific student chips (search & add) ----
    function renderSelectedStudents() {
        if (!selectedSpecificStudentsContainer) return;
        selectedSpecificStudentsContainer.innerHTML = '';
        selectedStudents.forEach(function (s) {
            const chip = document.createElement('span');
            chip.className = 'badge rounded-pill text-bg-secondary px-3 py-2 d-inline-flex align-items-center gap-2';
            chip.innerHTML = '<i class="bi bi-person-fill"></i>' + s.label +
                ' <button type="button" class="btn-close btn-close-white remove-specific-student" style="font-size:0.6rem" data-student="' + s.student_number + '" aria-label="Remove ' + s.label + '"></button>';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'additional_students[]';
            hidden.value = s.student_number;

            chip.appendChild(hidden);
            selectedSpecificStudentsContainer.appendChild(chip);
        });
    }

    function addSpecificStudent(student) {
        if (!student || !student.student_number) return;
        if (selectedStudents.some(function (s) { return s.student_number === student.student_number; })) {
            alert('Student ' + student.student_number + ' is already in the list.');
            return;
        }
        selectedStudents.push({ student_number: student.student_number, label: (student.name ? student.name + ' (' + student.student_number + ')' : student.student_number) });
        renderSelectedStudents();
        studentSearchInput.value = '';
        studentSearchResults.classList.add('d-none');
        studentSearchResults.innerHTML = '';
    }

    let studentSearchTimer = null;
    studentSearchInput?.addEventListener('input', function () {
        clearTimeout(studentSearchTimer);
        const keyword = this.value.trim();
        if (keyword.length < 2) {
            studentSearchResults.classList.add('d-none');
            studentSearchResults.innerHTML = '';
            return;
        }
        studentSearchTimer = setTimeout(function () {
            const programId = program.value;
            if (!programId) {
                alert('Select a program first.');
                return;
            }
            fetch(searchStudentsUrl + '?program_id=' + encodeURIComponent(programId) + '&search=' + encodeURIComponent(keyword), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    const list = data.students || [];
                    studentSearchResults.innerHTML = '';
                    if (list.length === 0) {
                        studentSearchResults.innerHTML = '<button type="button" class="list-group-item list-group-item-action text-muted" disabled>No matching students.</button>';
                    } else {
                        list.forEach(function (s) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            btn.innerHTML = '<strong>' + s.student_number + '</strong> &mdash; ' + s.name + ' <span class="text-muted">(' + s.program + ' &middot; ' + (s.year || '-') + ')</span>';
                            btn.addEventListener('click', function () { addSpecificStudent(s); });
                            studentSearchResults.appendChild(btn);
                        });
                    }
                    studentSearchResults.classList.remove('d-none');
                })
                .catch(function () {
                    studentSearchResults.innerHTML = '<button type="button" class="list-group-item list-group-item-action text-muted" disabled>Search failed.</button>';
                    studentSearchResults.classList.remove('d-none');
                });
        }, 300);
    });

    addSpecificStudentBtn?.addEventListener('click', function () {
        const keyword = studentSearchInput.value.trim();
        if (keyword.length === 0) {
            alert('Type a student number or name to search first.');
            return;
        }
        // If the user typed an exact student number, add it directly.
        if (/^\S+$/.test(keyword)) {
            const programId = program.value;
            if (!programId) { alert('Select a program first.'); return; }
            fetch(searchStudentsUrl + '?program_id=' + encodeURIComponent(programId) + '&search=' + encodeURIComponent(keyword), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    const matches = (data.students || []).filter(function (s) {
                        return s.student_number === keyword || (s.name || '').toLowerCase().indexOf(keyword.toLowerCase()) !== -1;
                    });
                    if (matches.length === 0) {
                        alert('No matching student found. Check the program and student number/name.');
                        return;
                    }
                    matches.forEach(addSpecificStudent);
                })
                .catch(function () { alert('Search failed. Please try again.'); });
            return;
        }
        alert('Search results appear below. Click a result to add it to the list.');
    });

    clearStudentSearchBtn?.addEventListener('click', function () {
        studentSearchInput.value = '';
        studentSearchResults.classList.add('d-none');
        studentSearchResults.innerHTML = '';
    });

    selectedSpecificStudentsContainer?.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-specific-student');
        if (!btn) return;
        const studentNumber = btn.dataset.student;
        selectedStudents = selectedStudents.filter(function (s) { return s.student_number !== studentNumber; });
        renderSelectedStudents();
    });

    previewBtn?.addEventListener('click', async function () {
        const programId = program.value;
        if (!programId) { alert('Select a program first.'); return; }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
        previewWrap.classList.remove('d-none');
        previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Loading students...</td></tr>';
        previewSummary.textContent = '';

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    program_id: programId,
                    additional_students: selectedStudents.map(function (s) { return s.student_number; }),
                    ladderized: getSelectedLadderized(),                      exclude_students: excludedStudents,                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to preview students.');
            }

            const ladderizedLabel = data.ladderized_filter === 1 ? ' (Ladderized: Yes)' : (data.ladderized_filter === 0 ? ' (Ladderized: No)' : '');
              const removedLabel = excludedStudents.length > 0 ? (', ' + excludedStudents.length + ' removed') : '';
              previewSummary.textContent = data.total + ' student(s) in the graduating list (automatic year level + specific students)' + ladderizedLabel + removedLabel + '.';
            if (data.total === 0) {
                previewBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No students match the automatic year level for this program / specific students.</td></tr>';
                return;
            }

            // The SERVER already groups and orders this list:
            //   non-ladderized automatic -> ladderized automatic -> added by hand,
            // alphabetical by first name within each group. These headings just
            // make that grouping visible in the table.
            let previousHeading = '';

            previewBody.innerHTML = data.students.map(function (s) {
                let heading = s.is_added ? 'Added Student' : (s.is_ladderized ? 'Ladderized' : 'Bachelor (Non-Ladderized)');

                let markup = '';

                if (s.is_added) {
                    // Added students are rendered as their own final group, so a
                    // heading is emitted once when the group starts.
                    if (previousHeading !== heading) {
                        markup += '<tr class="table-light"><td colspan="6" class="fw-semibold small text-uppercase text-muted py-2">Added Student/s</td></tr>';
                    }
                } else if (previousHeading !== heading) {
                    markup += '<tr class="table-light"><td colspan="6" class="fw-semibold small text-uppercase text-muted py-2">' + heading + '</td></tr>';
                }

                markup += '<tr>' +
                    '<td>' + s.student_number + '</td>' +
                    '<td>' + s.name + '</td>' +
                    '<td>' + s.program + '</td>' +
                    '<td>' + (s.year || '-') + ' / ' + (s.section || '-') + '</td>' +
                    '<td>' + (s.status || '-') + '</td>' +
                    '<td class="text-end">' +
                          '<button type="button" class="admin-btn admin-btn-danger admin-btn-sm remove-preview-student" ' +
                              'data-student="' + s.student_number + '" ' +
                              'aria-label="Remove ' + (s.name || s.student_number) + ' from this report">' +
                              '<i class="bi bi-x-lg me-1"></i>Remove' +
                          '</button>' +
                      '</td>' +
                '</tr>';

                previousHeading = heading;

                return markup;
            }).join('');
        } catch (error) {
            console.error(error);
            previewBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">' + error.message + '</td></tr>';
        } finally {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i> Preview Students';
        }
    });

    // Remove a student from the preview. Preview-only: nothing is deleted, and
    // the server re-authorises the exclusion when the report is generated.
    previewBody?.addEventListener('click', function (event) {
        const btn = event.target.closest('.remove-preview-student');
        if (!btn) return;
        const studentNumber = btn.dataset.student;
        if (!studentNumber) return;
        if (!excludedStudents.includes(studentNumber)) {
            excludedStudents.push(studentNumber);
        }
        renderExcludedInputs();
        // Re-request the preview so the count and list reflect the removal.
        previewBtn?.click();
    });

    // report_mode is a fixed hidden value now, so only the program selection
    // can change the graduating/ladderized visibility.
    program?.addEventListener('change', updateMode);
    updateMode();
});
</script>
@endpush
