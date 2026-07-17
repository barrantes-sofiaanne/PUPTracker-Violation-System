document.addEventListener("DOMContentLoaded", function () {
    // --- COMMON: Mobile Navigation Toggle ---
    const mobileNavToggle = document.querySelector(".mobile-nav-toggle");
    const primaryNav = document.getElementById("primary-navigation");
    const notificationsDropdownContent = document.getElementById(
        "notificationsDropdownContent",
    );

    if (mobileNavToggle && primaryNav) {
        mobileNavToggle.addEventListener("click", () => {
            const isVisible =
                primaryNav.getAttribute("data-visible") === "true";
            if (isVisible) {
                primaryNav.setAttribute("data-visible", "false");
                mobileNavToggle.setAttribute("aria-expanded", "false");
            } else {
                primaryNav.setAttribute("data-visible", "true");
                mobileNavToggle.setAttribute("aria-expanded", "true");
                if (notificationsDropdownContent) {
                    notificationsDropdownContent.classList.remove("show");
                }
            }
        });
    }

    // --- COMMON: Notification Dropdown ---
    const notificationLinkToggle = document.getElementById(
        "notificationLinkToggle",
    );
    const markAllReadBtn = document.getElementById("mark-all-read-btn");
    const notificationList = document.querySelector(".notification-list");
    const notificationCountBadge = document.querySelector(
        ".notification-count",
    );

    if (notificationLinkToggle && notificationsDropdownContent) {
        notificationLinkToggle.addEventListener("click", function (event) {
            event.preventDefault();
            notificationsDropdownContent.classList.toggle("show");
        });
    }

    document.addEventListener("click", function (event) {
        if (notificationsDropdownContent && notificationLinkToggle) {
            if (
                !notificationLinkToggle.contains(event.target) &&
                !notificationsDropdownContent.contains(event.target)
            ) {
                notificationsDropdownContent.classList.remove("show");
            }
        }
    });

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener("click", function () {
            fetch("../PHP/mark_all_notifications_read.php", { method: "POST" })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success && notificationList) {
                        notificationList.innerHTML =
                            '<li class="no-notifications">No new notifications.</li>';
                        if (notificationCountBadge) {
                            notificationCountBadge.style.display = "none";
                        }
                        this.style.display = "none";
                    }
                })
                .catch((error) =>
                    console.error(
                        "Error marking all notifications as read:",
                        error,
                    ),
                );
        });
    }

    // --- ALL NOTIFICATIONS PAGE: Mark All as Read Button ---
    const markAllReadPageBtn = document.getElementById("markAllReadPageBtn");
    if (markAllReadPageBtn) {
        markAllReadPageBtn.addEventListener("click", function () {
            fetch("../PHP/mark_all_notifications_read.php", { method: "POST" })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        window.location.reload();
                    }
                })
                .catch((error) =>
                    console.error("Error on marking all as read:", error),
                );
        });
    }

    // --- ANNOUNCEMENT PAGE: Modal Logic ---
    const announcementModal = document.getElementById("announcementModal");
    if (announcementModal) {
        const modalTitle = document.getElementById("modalTitle");
        const modalMeta = document.getElementById("modalMeta");
        const modalContent = document.getElementById("modalContent");
        const modalImage = document.getElementById("modalImage");
        const closeBtn = announcementModal.querySelector(
            ".announcement-close-btn",
        );
        let readAnnouncements =
            JSON.parse(localStorage.getItem("readAnnouncements")) || [];
        const announcementCards =
            document.querySelectorAll(".announcement-card");

        announcementCards.forEach((card) => {
            const announcementId = card.getAttribute("data-id");
            if (!readAnnouncements.includes(announcementId)) {
                card.classList.add("unread");
            }
        });

        function openAnnouncementModal() {
            announcementModal.classList.add("show");
        }
        function closeAnnouncementModal() {
            announcementModal.classList.remove("show");
        }

        if (closeBtn)
            closeBtn.addEventListener("click", closeAnnouncementModal);
        window.addEventListener("click", function (event) {
            if (event.target == announcementModal) {
                closeAnnouncementModal();
            }
        });

        announcementCards.forEach((card) => {
            card.addEventListener("click", function () {
                const announcementId = this.getAttribute("data-id");
                if (!readAnnouncements.includes(announcementId)) {
                    readAnnouncements.push(announcementId);
                    localStorage.setItem(
                        "readAnnouncements",
                        JSON.stringify(readAnnouncements),
                    );
                    this.classList.remove("unread");
                }
                modalTitle.textContent = "Loading...";
                modalMeta.innerHTML = "";
                modalContent.innerHTML = "";
                if (modalImage) {
                    modalImage.style.display = "none";
                    modalImage.src = "";
                }

                openAnnouncementModal();
                fetch(
                    `student_announcements.php?action=get_announcement&id=${announcementId}`,
                )
                    .then((response) => response.json())
                    .then((result) => {
                        if (result.success) {
                            const data = result.data;
                            modalTitle.textContent = data.title;
                            modalMeta.innerHTML = `<span class="meta-item"><svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2m0 10c2.7 0 5.8 1.29 6 2H6c.23-.72 3.31-2 6-2m0-12C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79-4-4-1.79-4-4-4zm0 10c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> By ${
                                data.author_name || "Admin"
                            }</span><span class="meta-item"><svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg> ${
                                data.created_at_formatted
                            }</span>`;

                            if (data.attachment_path && modalImage) {
                                modalImage.src = `../uploads/announcements/${data.attachment_path}`;
                                modalImage.style.display = "block";
                            }

                            modalContent.innerHTML = data.content;
                        } else {
                            modalTitle.textContent = "Error";
                            modalContent.textContent =
                                "Could not load the announcement.";
                        }
                    })
                    .catch((error) => {
                        console.error("Fetch error:", error);
                        modalTitle.textContent = "Error";
                        modalContent.textContent =
                            "An error occurred while fetching the announcement.";
                    });
            });
        });
    }

    // --- RECORD PAGE: Sanction Request ---
    const requestButton = document.getElementById("requestSanctionButton");
    const overlay = document.getElementById("confirmationOverlay");
    const closeButton = document.getElementById("closeOverlayButton");
    const overlayMessage = overlay ? overlay.querySelector("p") : null;

    if (requestButton) {
        requestButton.addEventListener("click", function () {
            if (this.disabled) return;
            this.disabled = true;
            this.textContent = "Submitting...";
            const formData = new FormData();
            formData.append("action", "request_sanction");
            fetch("student_record.php", { method: "POST", body: formData })
                .then((response) => response.json())
                .then((data) => {
                    if (overlayMessage) {
                        overlayMessage.textContent = data.message;
                    }
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    if (data.success) {
                        requestButton.textContent = "Request Sent";
                    } else {
                        requestButton.disabled = false;
                        requestButton.textContent = "Request Sanction";
                    }
                })
                .catch((error) => {
                    console.error("Error:", error);
                    if (overlayMessage) {
                        overlayMessage.textContent =
                            "An network error occurred. Please try again.";
                    }
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    this.disabled = false;
                    this.textContent = "Request Sanction";
                });
        });
        if (closeButton) {
            closeButton.addEventListener("click", function () {
                if (overlay) overlay.style.display = "none";
            });
        }
        if (overlay) {
            overlay.addEventListener("click", function (event) {
                if (event.target === overlay) {
                    overlay.style.display = "none";
                }
            });
        }
    }

    // --- RECORD PAGE: Tabs ---
    const tabButtons = document.querySelectorAll(
        ".tabs-navigation .tab-button",
    );
    if (tabButtons.length > 0) {
        const tabContents = document.querySelectorAll(".tab-content");
        tabButtons.forEach((button) => {
            button.addEventListener("click", function () {
                const targetTabId = this.dataset.tab;
                tabButtons.forEach((btn) =>
                    btn.classList.remove("active-tab-button"),
                );
                tabContents.forEach((content) =>
                    content.classList.remove("active-tab"),
                );
                this.classList.add("active-tab-button");
                const targetTabContent = document.getElementById(targetTabId);
                if (targetTabContent) {
                    targetTabContent.classList.add("active-tab");
                }
            });
        });
    }

    // --- RECORD PAGE: Mobile Accordion for Tables ---
    const recordTableRows = document.querySelectorAll(
        ".record-wrapper .mobile-accordion-row",
    );
    if (recordTableRows.length > 0 && window.innerWidth <= 992) {
        recordTableRows.forEach((acc) => {
            acc.addEventListener("click", (e) => {
                if (e.target.tagName === "A" || e.target.closest("a")) return;
                acc.classList.toggle("is-open");
            });
        });
    }

    // --- DASHBOARD PAGE: Handbook Search ---
    const searchInput = document.getElementById("handbook-search-input");
    const accordionContainer = document.querySelector(".accordion-container");
    if (searchInput && accordionContainer) {
        const noResultsMessage = document.getElementById("no-results-message");
        const categories =
            accordionContainer.querySelectorAll(".accordion-item");

        searchInput.addEventListener("input", function () {
            const searchTerm = this.value.trim().toLowerCase();
            let anyCategoryVisible = false;

            categories.forEach((category) => {
                const categoryHeader = category.querySelector(
                    ".accordion-header .category-name",
                );
                const violationTypes = category.querySelectorAll(
                    ".violation-type-item",
                );
                let isCategoryVisible = false;

                const categoryHeaderText = categoryHeader.textContent
                    .trim()
                    .toLowerCase();
                if (categoryHeaderText.includes(searchTerm)) {
                    isCategoryVisible = true;
                }

                violationTypes.forEach((violation) => {
                    const violationHeader = violation.querySelector(
                        ".violation-type-header",
                    );
                    const sanctions =
                        violation.querySelectorAll(".sanction-item");
                    let isViolationVisible = false;

                    const violationHeaderText = violationHeader.textContent
                        .trim()
                        .toLowerCase();
                    if (violationHeaderText.includes(searchTerm)) {
                        isViolationVisible = true;
                    }

                    sanctions.forEach((sanction) => {
                        const sanctionText = sanction.textContent
                            .trim()
                            .toLowerCase();
                        if (sanctionText.includes(searchTerm)) {
                            isViolationVisible = true;
                            sanction.style.display = "";
                        } else {
                            sanction.style.display = searchTerm ? "none" : "";
                        }
                    });

                    if (isViolationVisible) {
                        violation.style.display = "block";
                        if (searchTerm) violation.open = true;
                        isCategoryVisible = true;
                    } else {
                        violation.style.display = "none";
                    }
                });

                if (isCategoryVisible) {
                    category.style.display = "block";
                    if (searchTerm) category.open = true;
                    anyCategoryVisible = true;
                } else {
                    category.style.display = "none";
                }
            });

            if (noResultsMessage) {
                noResultsMessage.style.display = anyCategoryVisible
                    ? "none"
                    : "block";
            }
        });
    }

    // --- ACCOUNT PAGE: Buttons & Modals ---
    const signOutButton = document.getElementById("signOutBtn");
    if (signOutButton) {
        signOutButton.addEventListener("click", function (event) {
            event.preventDefault();
            window.location.href = this.href;
        });
    }

    const changePasswordModal = document.getElementById("changePasswordModal");
    if (changePasswordModal) {
        const openModalBtn = document.getElementById("changePasswordBtn");
        const closeModalBtns = changePasswordModal.querySelectorAll(
            ".modal-close-btn, .cancel-btn",
        );

        const showPasswordModal = () =>
            changePasswordModal.classList.add("show");
        const hidePasswordModal = () => {
            changePasswordModal.classList.remove("show");
            const messageDiv = document.getElementById("form-message");
            if (messageDiv) {
                messageDiv.style.display = "none";
                messageDiv.textContent = "";
                messageDiv.className = "message";
            }
            const form = document.getElementById("changePasswordForm");
            if (form) form.reset();
        };

        if (openModalBtn) {
            openModalBtn.addEventListener("click", showPasswordModal);
        }

        closeModalBtns.forEach((btn) =>
            btn.addEventListener("click", hidePasswordModal),
        );

        changePasswordModal.addEventListener("click", (e) => {
            if (e.target === changePasswordModal) {
                hidePasswordModal();
            }
        });

        const newPasswordInput = document.getElementById("new_password");
        const reqList = document.getElementById("password-requirements");

        if (newPasswordInput && reqList) {
            newPasswordInput.addEventListener("focus", function () {
                reqList.classList.add("show-reqs");
            });

            newPasswordInput.addEventListener("blur", function () {
                if (this.value === "") {
                    reqList.classList.remove("show-reqs");
                }
            });

            newPasswordInput.addEventListener("input", function () {
                const val = this.value;

                const toggleValid = (id, isValid) => {
                    const el = document.getElementById(id);
                    if (el) {
                        if (isValid) {
                            el.classList.add("valid");
                        } else {
                            el.classList.remove("valid");
                        }
                    }
                };

                toggleValid("req-length", val.length >= 8);
                toggleValid("req-upper", /[A-Z]/.test(val));
                toggleValid("req-lower", /[a-z]/.test(val));
                toggleValid("req-number", /[0-9]/.test(val));
                toggleValid("req-special", /[^\w\s]/.test(val));
            });
        }

        const form = document.getElementById("changePasswordForm");
        const messageDiv = document.getElementById("form-message");

        if (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();

                const newPassword =
                    document.getElementById("new_password").value;
                const confirmPassword = document.getElementById(
                    "confirm_new_password",
                ).value;

                if (newPassword !== confirmPassword) {
                    alert("Your new passwords do not match! Please try again.");
                    return;
                }

                const strongRegex =
                    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{8,}$/;
                if (!strongRegex.test(newPassword)) {
                    alert(
                        "Your password is too weak!\n\nIt must contain:\n- At least 8 characters\n- One uppercase letter\n- One lowercase letter\n- One number\n- One special character (!@#$%^&*)",
                    );
                    return;
                }

                messageDiv.style.display = "none";
                messageDiv.textContent = "";
                messageDiv.className = "message";

                const formData = new FormData(form);

                fetch("./change_password_handler.php", {
                    method: "POST",
                    body: formData,
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error("Network response was not ok");
                        }
                        return response.json();
                    })
                    .then((data) => {
                        messageDiv.textContent = data.message;
                        messageDiv.classList.add(
                            data.success ? "success" : "error",
                        );
                        messageDiv.style.display = "block";

                        if (data.success) {
                            form.reset();
                            setTimeout(hidePasswordModal, 2000);
                        }
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                        messageDiv.textContent =
                            "An error occurred. Please check the console for details.";
                        messageDiv.classList.add("error");
                        messageDiv.style.display = "block";
                    });
            });
        }
    }
});
