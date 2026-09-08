import Quill from "quill";
import "quill/dist/quill.snow.css";

/**
 * Initialize a Quill editor bound to an existing form field so formatted HTML
 * is submitted through the EXISTING announcement form/update process.
 *
 * @param {string} editorElId   Container element id where Quill mounts.
 * @param {string} sourceInputId The existing textarea/input that holds content.
 * @param {object|null} options  Quill options override (optional).
 * @returns {Quill|null}
 */
window.initAnnouncementQuill = function (
    editorElId,
    sourceInputId,
    options
) {
    const editorEl = document.getElementById(editorElId);
    const sourceInput = document.getElementById(sourceInputId);
    if (!editorEl || !sourceInput) {
        return null;
    }

    const form = sourceInput.closest("form");

    const toolbarOptions = (options && options.toolbar) || [
        [{ header: [2, 3, false] }],
        ["bold", "italic", "underline"],
        [{ list: "ordered" }, { list: "bullet" }],
        ["link"],
        ["clean"],
    ];

    const quill = new Quill(editorEl, {
        theme: "snow",
        modules: {
            toolbar: toolbarOptions,
        },
        placeholder: "Write the announcement content...",
    });

    // Load existing content (HTML or legacy plain text) into the editor.
    const initial = sourceInput.value || "";
    if (initial && initial.trim() !== "") {
        try {
            quill.clipboard.dangerouslyPasteHTML(0, initial);
        } catch (e) {
            quill.setText(initial);
        }
    }

    // Sync editor content back into the existing field before submit. Preserve
    // the previous "required" behavior: a blank editor stops submission and
    // shows a message instead of sending an empty announcement.
    if (form) {
        form.addEventListener("submit", function (event) {
            const hasText = quill.getText().replace(/\s/g, "").length > 0;
            if (!hasText) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "warning",
                        title: "Missing Content",
                        text: "Please enter the announcement content.",
                    });
                } else {
                    alert("Please enter the announcement content.");
                }
                return;
            }
            sourceInput.value = quill.root.innerHTML;
        });
    }

    window.quillInstances = window.quillInstances || {};
    window.quillInstances[editorElId] = quill;

    return quill;
};
