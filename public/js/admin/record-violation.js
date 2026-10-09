document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("addViolationModal");
    const studentStep = document.getElementById("studentStep");
    const violationStep = document.getElementById("violationStep");
    const evidenceStep = document.getElementById("evidenceStep");
    const reviewStep = document.getElementById("reviewStep");
    const nextBtn =
        document.getElementById("nextStep") ||
        document.getElementById("continueStep");
    const backBtn = document.getElementById("backStep");
    const saveBtn = document.getElementById("saveViolation");
    const studentIndicator = document.getElementById("studentStepIndicator");
    const violationIndicator = document.getElementById(
        "violationStepIndicator",
    );
    const evidenceIndicator = document.getElementById("evidenceStepIndicator");
    const reviewIndicator = document.getElementById("reviewStepIndicator");
    const searchBox = document.getElementById("studentSearch");
    const resultBox = document.getElementById("studentResults");
    const categorySelect = document.getElementById("violationCategory");
    const violationTypeSelect = document.getElementById("violationType");
    const evidenceInput = document.getElementById("evidenceInput");
    const identityConfirmed = document.getElementById("identityConfirmed");

    const securityNoticeModalEl = document.getElementById(
        "securityNoticeModal",
    );
    const securityNoticeAck = document.getElementById("securityNoticeAck");
    const securityNoticeStartBtn = document.getElementById(
        "securityNoticeStartBtn",
    );
    const securityNoticeCancelBtn = document.getElementById(
        "securityNoticeCancelBtn",
    );
    const openCameraBtn = document.getElementById("openCameraBtn");
    const cameraIdlePanel = document.getElementById("cameraIdlePanel");
    const cameraLivePanel = document.getElementById("cameraLivePanel");
    const captureReviewPanel = document.getElementById("captureReviewPanel");
    const cameraWrap = document.getElementById("cameraWrap");
    const cameraPreview = document.getElementById("cameraPreview");
    const flipCameraBtn = document.getElementById("flipCameraBtn");
    const liveWatermarkStudent = document.getElementById(
        "liveWatermarkStudent",
    );
    const liveWatermarkTime = document.getElementById("liveWatermarkTime");
    const capturePhotoBtn = document.getElementById("capturePhotoBtn");
    const closeCameraBtn = document.getElementById("closeCameraBtn");
    const retakePhotoBtn = document.getElementById("retakePhotoBtn");
    const usePhotoBtn = document.getElementById("usePhotoBtn");
    const captureReviewImg = document.getElementById("captureReviewImg");
    const captureReviewType = document.getElementById("captureReviewType");
    const captureReviewSize = document.getElementById("captureReviewSize");
    const cameraInterruptedOverlay = document.getElementById(
        "cameraInterruptedOverlay",
    );

    if (
        !modalElement ||
        !studentStep ||
        !violationStep ||
        !evidenceStep ||
        !reviewStep ||
        !nextBtn ||
        !backBtn ||
        !saveBtn ||
        !studentIndicator ||
        !violationIndicator ||
        !evidenceIndicator ||
        !reviewIndicator ||
        !searchBox ||
        !resultBox ||
        !categorySelect ||
        !violationTypeSelect ||
        !evidenceInput ||
        !identityConfirmed
    ) {
        return;
    }

    const showAlert = (icon, title, text) => {
        Swal.fire({ icon, title, text });
    };

    const stepPanels = [studentStep, violationStep, evidenceStep, reviewStep];
    const indicators = [
        studentIndicator,
        violationIndicator,
        evidenceIndicator,
        reviewIndicator,
    ];

    let currentStep = 1; // 1..4
    // Staged evidence items: { file, name, method ('camera'|'upload'),
    //   capturedAt (ISO|null), watermarked (bool), size }
    let evidenceItems = [];
    const MAX_EVIDENCE = 5;
    const MAX_FILE_BYTES = 5 * 1024 * 1024;

    // Correlate every browser-monitor event of one capture attempt to a single
    // camera/capture session (CAM-SESSION-YYYY-NNNNNN). Generated when the
    // camera portal opens and sent with each security event + on submit.
    let captureSessionId = null;

    // ------------------------------------------------------------------
    // Security event reporting (browser monitor → server).
    // These are DETECTION / DETERRENCE indicators only — never a claim that
    // a screenshot "confirmed" occurred (browsers cannot prove that).
    // ------------------------------------------------------------------
    const beginCaptureSession = () => {
        if (!captureSessionId) {
            // Best-effort unique id; the server stores it as-is. This is an
            // audit correlation key, never a security boundary.
            const d = new Date();
            const pad = (n) => String(n).padStart(2, "0");
            const stamp = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(
                d.getDate(),
            )}${pad(d.getHours())}${pad(d.getMinutes())}${pad(
                d.getSeconds(),
            )}${String(d.getMilliseconds()).padStart(3, "0")}`;
            captureSessionId = `CAM-SESSION-${stamp}`;
        }
        return captureSessionId;
    };

    const reportSecurityEvent = (eventType, meta, severity) => {
        if (!window.ViolationRoutes || !window.ViolationRoutes.securityEvent) {
            return;
        }
        const payload = { event_type: eventType, severity: severity || "info" };
        if (captureSessionId) {
            payload.capture_session_id = captureSessionId;
        }
        if (meta && typeof meta === "object") {
            payload.meta = meta;
        }
        fetch(window.ViolationRoutes.securityEvent, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: JSON.stringify(payload),
        }).catch(() => {
            // Security logging must never break the capture flow.
        });
    };

    const setStep = (stepNumber) => {
        currentStep = stepNumber;

        stepPanels.forEach(function (panel, i) {
            panel.classList.toggle("d-none", i !== stepNumber - 1);
        });

        indicators.forEach(function (ind, i) {
            // PUPTracker palette ONLY: maroon + gold. Never green/blue/purple.
            //   completed -> gold (the "done" milestone tone)
            //   current   -> maroon (the active step, strongest tone)
            //   upcoming  -> muted/pale treatment that stays inside the palette
            ind.classList.remove(
                "wizard-step-completed",
                "wizard-step-current",
                "wizard-step-upcoming",
                "bg-danger",
                "bg-goldenrod",
                "bg-success",
                "bg-secondary",
            );
            if (i < stepNumber - 1) {
                ind.classList.add("wizard-step-completed");
            } else if (i === stepNumber - 1) {
                ind.classList.add("wizard-step-current");
            } else {
                ind.classList.add("wizard-step-upcoming");
            }
        });

        backBtn.classList.toggle("d-none", stepNumber === 1);
        nextBtn.classList.toggle("d-none", stepNumber === 4);
        saveBtn.classList.toggle("d-none", stepNumber !== 4);

        // Leaving the evidence step (forward or back) pauses the camera.
        if (stepNumber !== 3) {
            stopCamera();
        } else {
            updateEvidenceCount();
        }
    };

    // ------------------------------------------------------------------
    // Camera lifecycle
    // ------------------------------------------------------------------
    let cameraStream = null;
    let cameraActive = false;
    let cameraPausedByInterruption = false;
    let cameraNoticeAccepted = false;
    let capturedImageBlob = null;
    let capturedImageObjectUrl = null;
    let liveClockInterval = null;
    // Current camera facing mode: 'user' (front) or 'environment' (back).
    // Defaults to the front camera; the flip button switches between them.
    let cameraFacingMode = "user";
    // Optional explicit deviceId (when the browser exposes multiple cameras
    // that are not cleanly split into front/back via facingMode).
    let cameraDeviceId = null;

    const phNow = () => {
        return new Date(
            new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }),
        );
    };

    const formatPhTimestamp = (date) => {
        const d = date || new Date();
        const pad = (n) => String(n).padStart(2, "0");
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
            d.getDate(),
        )} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };

    const currentStudentNumber = () =>
        document.getElementById("selectedStudentNumber").value || "";
    // ---------------------------------------------------------------
    // Privacy face-blur protection
    // Uses the same MediaPipe face-blur module as Security.
    // ---------------------------------------------------------------
    const getFaceBlur = () => window.PUPTrackerFaceBlur || null;

    const initializePrivacyFaceBlur = async () => {
        const faceBlur = getFaceBlur();

        if (!faceBlur) {
            throw new Error(
                "The privacy face-blur module is not available. Capture is disabled.",
            );
        }

        await faceBlur.initializeFaceDetector();

        return faceBlur;
    };
    const updateLiveWatermark = () => {
        if (liveWatermarkStudent) {
            liveWatermarkStudent.textContent =
                currentStudentNumber() || "NO-STUDENT";
        }
        if (liveWatermarkTime) {
            liveWatermarkTime.textContent = formatPhTimestamp();
        }
    };

    const startLiveClock = () => {
        if (liveClockInterval) {
            clearInterval(liveClockInterval);
        }
        liveClockInterval = setInterval(updateLiveWatermark, 1000);
    };

    const stopLiveClock = () => {
        if (liveClockInterval) {
            clearInterval(liveClockInterval);
            liveClockInterval = null;
        }
    };

    const showCameraIdle = () => {
        if (cameraIdlePanel) {
            cameraIdlePanel.classList.remove("d-none");
        }
        if (cameraLivePanel) {
            cameraLivePanel.classList.add("d-none");
        }
        if (captureReviewPanel) {
            captureReviewPanel.classList.add("d-none");
        }
        if (flipCameraBtn) {
            flipCameraBtn.classList.add("d-none");
        }
    };

    const showCameraLive = () => {
        if (cameraIdlePanel) {
            cameraIdlePanel.classList.add("d-none");
        }
        if (captureReviewPanel) {
            captureReviewPanel.classList.add("d-none");
        }
        if (cameraLivePanel) {
            cameraLivePanel.classList.remove("d-none");
        }
        // The flip control is only meaningful while the stream is live.
        if (flipCameraBtn) {
            flipCameraBtn.classList.add("d-flex");
            flipCameraBtn.classList.remove("d-none");
        }
    };

    const showCaptureReview = (blob, objectUrl) => {
        if (cameraIdlePanel) {
            cameraIdlePanel.classList.add("d-none");
        }
        if (cameraLivePanel) {
            cameraLivePanel.classList.add("d-none");
        }
        if (captureReviewPanel) {
            captureReviewPanel.classList.remove("d-none");
        }
        if (captureReviewImg) {
            captureReviewImg.src = objectUrl;
        }
        if (captureReviewType) {
            captureReviewType.textContent = "JPEG";
        }
        if (captureReviewSize) {
            captureReviewSize.textContent = `${(blob.size / 1024).toFixed(0)} KB`;
        }
    };

    const resetCameraPanels = () => {
        showCameraIdle();
        if (cameraWrap) {
            cameraWrap.classList.remove("video-interrupted");
        }
        if (cameraInterruptedOverlay) {
            cameraInterruptedOverlay.classList.add("d-none");
            cameraInterruptedOverlay.classList.remove("d-flex");
        }
        if (cameraPreview) {
            cameraPreview.classList.remove("camera-obscured");
        }
    };

    // Open the security-notice modal (mandatory before camera init).
    const openSecurityNotice = () => {
        resetCameraPanels();
        // Fresh capture session for THIS portal open so every monitor event
        // (open, blur, focus, capture, abandon) is correlated together.
        beginCaptureSession();
        if (!securityNoticeModalEl || !securityNoticeAck) {
            // Modal missing — still try to start (defensive).
            startCamera();
            return;
        }
        securityNoticeAck.checked = false;
        securityNoticeStartBtn.disabled = true;
        const noticeModal = bootstrap.Modal.getOrCreateInstance(
            securityNoticeModalEl,
        );
        noticeModal.show();
        reportSecurityEvent("CAMERA_PORTAL_OPENED", null, "info");
    };

    securityNoticeAck &&
        securityNoticeAck.addEventListener("change", function () {
            securityNoticeStartBtn.disabled = !this.checked;
        });

    securityNoticeStartBtn &&
        securityNoticeStartBtn.addEventListener("click", function () {
            const noticeModal = bootstrap.Modal.getInstance(
                securityNoticeModalEl,
            );
            if (noticeModal) {
                noticeModal.hide();
            }
            cameraNoticeAccepted = true;
            reportSecurityEvent("SECURITY_NOTICE_ACCEPTED", null, "info");
            startCamera();
        });

    securityNoticeCancelBtn &&
        securityNoticeCancelBtn.addEventListener("click", function () {
            const noticeModal = bootstrap.Modal.getInstance(
                securityNoticeModalEl,
            );
            if (noticeModal) {
                noticeModal.hide();
            }
            reportSecurityEvent(
                "CAPTURE_ABANDONED",
                { stage: "notice" },
                "info",
            );
            showCameraIdle();
        });

    openCameraBtn &&
        openCameraBtn.addEventListener("click", openSecurityNotice);

    // Core camera start. `deviceId` (when provided) pins an exact camera;
    // otherwise `facingMode` ('user' | 'environment') is requested as ideal.
    const startStream = (facingMode, deviceId) => {
        if (facingMode) {
            cameraFacingMode = facingMode;
        }
        showCameraLive();
        if (capturePhotoBtn) {
            capturePhotoBtn.disabled = true; // enabled only after stream is live
        }

        const videoConstraint = deviceId
            ? { deviceId: { exact: deviceId } }
            : { facingMode: { ideal: cameraFacingMode } };

        navigator.mediaDevices
            .getUserMedia({
                video: videoConstraint,
                audio: false,
            })
            .then(async function (stream) {
                cameraStream = stream;
                cameraActive = true;
                cameraPausedByInterruption = false;
                cameraDeviceId = deviceId || null;

                if (cameraPreview) {
                    cameraPreview.srcObject = stream;
                }

                // ---------------------------------------------------------------
                // Privacy protection MUST initialize before capture is enabled.
                // If face detection cannot initialize, the system will not allow
                // an evidence photograph to be captured.
                // ---------------------------------------------------------------
                try {
                    await initializePrivacyFaceBlur();
                } catch (error) {
                    stream.getTracks().forEach(function (track) {
                        track.stop();
                    });

                    cameraStream = null;
                    cameraActive = false;

                    if (cameraPreview) {
                        cameraPreview.srcObject = null;
                    }

                    showCameraIdle();

                    reportSecurityEvent(
                        "CAMERA_ERROR",
                        {
                            reason: "face blur initialization failed",
                        },
                        "warning",
                    );

                    showAlert(
                        "error",
                        "Privacy protection unavailable",
                        "The face-blur protection could not be initialized. The photo was not captured. Please try again.",
                    );

                    return;
                }

                // Capture is enabled ONLY after both the camera and privacy
                // protection are ready.
                if (capturePhotoBtn) {
                    capturePhotoBtn.disabled = false;
                }

                updateLiveWatermark();
                startLiveClock();

                reportSecurityEvent("CAMERA_INITIALIZED", null, "info");
            })
            .catch(function (error) {
                cameraActive = false;
                if (error && error.name === "OverconstrainedError") {
                    // The requested facingMode / device is not available here.
                    // Enumerate and pick a concrete camera.
                    reportSecurityEvent(
                        "CAMERA_ERROR",
                        { reason: String(error.name) },
                        "warning",
                    );
                    enumerateAndStartFallback();
                    return;
                }
                let reason = "Camera unavailable";
                if (error && error.name === "NotAllowedError") {
                    reason = "Permission denied";
                    reportSecurityEvent(
                        "CAMERA_PERMISSION_DENIED",
                        { reason: String(error.name) },
                        "warning",
                    );
                } else if (error && error.name === "NotFoundError") {
                    reason = "Camera hardware not found";
                    reportSecurityEvent(
                        "CAMERA_ERROR",
                        { reason: String(error.name) },
                        "warning",
                    );
                } else if (error && error.name === "NotReadableError") {
                    reason = "Camera is in use by another application";
                    reportSecurityEvent(
                        "CAMERA_ERROR",
                        { reason: String(error.name) },
                        "warning",
                    );
                } else {
                    reason = "Unable to access the camera";
                    reportSecurityEvent(
                        "CAMERA_ERROR",
                        {
                            reason: error
                                ? String(error.name || error.message)
                                : "unknown",
                        },
                        "warning",
                    );
                }
                showAlert("error", "Camera error", reason);
                showCameraIdle();
            });
    };

    // Public entry point used by the Open Camera / retake / initial flows.
    // Clears any pinned deviceId so the browser resolves via facingMode.
    const startCamera = (facingMode) => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            reportSecurityEvent(
                "CAMERA_ERROR",
                { reason: "API unsupported" },
                "warning",
            );
            showAlert(
                "error",
                "Camera unsupported",
                "This browser does not support camera access (getUserMedia). You can still attach a PDF document.",
            );
            showCameraIdle();
            return;
        }
        cameraDeviceId = null;
        startStream(facingMode || cameraFacingMode, null);
    };

    // Enumerate available video inputs and start with a concrete device,
    // falling back gracefully when facingMode is not honoured (e.g.
    // single-camera desktops or unusual mobile browsers). When a flip is
    // requested we rotate to the NEXT video input so the button still works on
    // devices that only expose several generic cameras without a usable
    // facingMode label.
    const enumerateAndStartFallback = () => {
        if (
            !navigator.mediaDevices ||
            !navigator.mediaDevices.enumerateDevices
        ) {
            showCameraIdle();
            return;
        }
        navigator.mediaDevices
            .enumerateDevices()
            .then(function (devices) {
                const videoInputs = devices.filter(function (device) {
                    return device.kind === "videoinput";
                });
                if (videoInputs.length === 0) {
                    showCameraIdle();
                    return;
                }
                // If we already have an active deviceId, try the next camera in
                // the list so a flip visibly switches on multi-camera devices
                // that do not expose a facingMode label.
                let preferred = null;
                if (cameraDeviceId) {
                    const currentIndex = videoInputs.findIndex(
                        function (device) {
                            return device.deviceId === cameraDeviceId;
                        },
                    );
                    const nextDevice =
                        videoInputs[(currentIndex + 1) % videoInputs.length];
                    if (nextDevice) {
                        preferred = nextDevice;
                    }
                }
                if (!preferred) {
                    // Otherwise prefer a labeled front/back match for the
                    // current facingMode, falling back to the first camera.
                    preferred =
                        videoInputs.find(function (device) {
                            return (
                                device.label &&
                                (cameraFacingMode === "environment"
                                    ? /back|rear|environment/i.test(
                                          device.label,
                                      )
                                    : /front|user/i.test(device.label))
                            );
                        }) || videoInputs[0];
                }

                const chosenDeviceId =
                    preferred && preferred.deviceId ? preferred.deviceId : null;

                if (!chosenDeviceId) {
                    showCameraIdle();
                    return;
                }

                startStream(cameraFacingMode, chosenDeviceId);
            })
            .catch(function () {
                showCameraIdle();
            });
    };

    const stopCamera = () => {
        stopLiveClock();
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            cameraStream = null;
        }
        cameraActive = false;
        cameraPausedByInterruption = false;
        if (cameraPreview) {
            cameraPreview.srcObject = null;
        }
        if (capturePhotoBtn) {
            capturePhotoBtn.disabled = true;
        }
        resetCameraPanels();
    };

    closeCameraBtn &&
        closeCameraBtn.addEventListener("click", function () {
            reportSecurityEvent(
                "CAPTURE_ABANDONED",
                { stage: "camera" },
                "info",
            );
            stopCamera();
        });

    // Flip between the front and back camera. The most reliable approach is to
    // enumerate the physical video inputs and start the NEXT one (rotating).
    // Most phones expose a front and a back camera, so each tap swaps them.
    // On a single-camera desktop the enumeration yields one device and the tap
    // simply restarts that same camera (no visible effect, which is expected).
    flipCameraBtn &&
        flipCameraBtn.addEventListener("click", function () {
            // Cannot flip while paused for a possible screen-capture /
            // interruption — the user must return to the window first.
            if (!cameraActive || cameraPausedByInterruption) {
                return;
            }
            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.enumerateDevices
            ) {
                return;
            }
            reportSecurityEvent(
                "CAMERA_FLIP",
                { from: cameraDeviceId || cameraFacingMode },
                "info",
            );
            navigator.mediaDevices
                .enumerateDevices()
                .then(function (devices) {
                    const videoInputs = devices.filter(function (device) {
                        return device.kind === "videoinput";
                    });
                    if (videoInputs.length === 0) {
                        return;
                    }

                    // Determine the current index. When a concrete device was
                    // active use it; otherwise derive from facingMode label.
                    let currentIndex = -1;
                    if (cameraDeviceId) {
                        currentIndex = videoInputs.findIndex(function (device) {
                            return device.deviceId === cameraDeviceId;
                        });
                    }
                    if (currentIndex === -1) {
                        currentIndex = videoInputs.findIndex(function (device) {
                            return (
                                device.label &&
                                (cameraFacingMode === "environment"
                                    ? /back|rear|environment/i.test(
                                          device.label,
                                      )
                                    : /front|user/i.test(device.label))
                            );
                        });
                    }
                    // Rotate to the next camera in the list.
                    const nextIndex = (currentIndex + 1) % videoInputs.length;
                    const nextDevice = videoInputs[nextIndex];
                    if (!nextDevice || !nextDevice.deviceId) {
                        return;
                    }

                    stopCamera();
                    // Flip the facing-mode bookkeeping too so the watermark /
                    // labels stay consistent with the likely camera.
                    cameraFacingMode =
                        cameraFacingMode === "environment"
                            ? "user"
                            : "environment";
                    startStream(cameraFacingMode, nextDevice.deviceId);
                })
                .catch(function () {
                    // If enumeration fails, fall back to a facingMode toggle.
                    stopCamera();
                    const nextMode =
                        cameraFacingMode === "environment"
                            ? "user"
                            : "environment";
                    startStream(nextMode, null);
                });
        });

    // Interruption handling — pause capture, blur/hide the preview, log event.
    const handleInterruption = (kind, meta) => {
        const isPrtSc = kind === "prtsc";
        if (cameraActive && !cameraPausedByInterruption) {
            cameraPausedByInterruption = true;
            if (capturePhotoBtn) {
                capturePhotoBtn.disabled = true;
            }
            if (flipCameraBtn) {
                flipCameraBtn.classList.add("d-none");
                flipCameraBtn.classList.remove("d-flex");
            }
            if (cameraPreview) {
                cameraPreview.classList.add("camera-obscured");
            }
            if (cameraWrap) {
                cameraWrap.classList.add("video-interrupted");
            }
            if (cameraInterruptedOverlay) {
                cameraInterruptedOverlay.classList.remove("d-none");
                cameraInterruptedOverlay.classList.add("d-flex");
            }
        }
        // Terminology is deliberately "possible screen capture or browser
        // interruption" — NOT "screenshot confirmed".
        reportSecurityEvent(
            isPrtSc ? "PRINTSCREEN_DETECTED" : "POSSIBLE_SCREEN_CAPTURE",
            meta || {},
            "warning",
        );
    };

    const restoreAfterInterruption = () => {
        if (cameraPausedByInterruption && cameraActive) {
            cameraPausedByInterruption = false;
            if (cameraPreview) {
                cameraPreview.classList.remove("camera-obscured");
            }
            if (cameraWrap) {
                cameraWrap.classList.remove("video-interrupted");
            }
            if (cameraInterruptedOverlay) {
                cameraInterruptedOverlay.classList.add("d-none");
                cameraInterruptedOverlay.classList.remove("d-flex");
            }
            if (flipCameraBtn) {
                flipCameraBtn.classList.remove("d-none");
                flipCameraBtn.classList.add("d-flex");
            }
            // Capture re-enabled because the page is active again.
            if (capturePhotoBtn && cameraActive) {
                capturePhotoBtn.disabled = false;
            }
            reportSecurityEvent("PAGE_FOCUS_RESTORED", null, "info");
        }
    };

    // ------------------------------------------------------------------
    // Browser focus / visibility / PrintScreen monitoring
    // (DETECTION ONLY — the watermark is the primary protection.)
    // ------------------------------------------------------------------
    const onWindowBlur = () => {
        if (currentStep === 3) {
            handleInterruption("blur", { source: "window blur" });
        }
    };

    const onWindowFocus = () => {
        restoreAfterInterruption();
    };

    const onVisibilityChange = () => {
        if (currentStep !== 3) {
            return;
        }
        reportSecurityEvent(
            "PAGE_VISIBILITY_CHANGED",
            { hidden: document.hidden },
            "info",
        );
        if (document.hidden) {
            handleInterruption("visibility", {
                source: "visibilitychange hidden",
            });
        } else {
            restoreAfterInterruption();
        }
    };

    const onKeyDown = (event) => {
        if (currentStep !== 3) {
            return;
        }
        const key = event.key || "";
        const isPrintScreen = key === "PrintScreen";
        // macOS: Cmd+Shift+3 / Cmd+Shift+4 are handled by the OS; the browser
        // typically does not see them. We only catch what the browser exposes.
        const isMacShot = event.metaKey && event.shiftKey && /^[34]$/.test(key);

        if (isPrintScreen || isMacShot) {
            handleInterruption("prtsc", { key });
        }
    };

    window.addEventListener("blur", onWindowBlur);
    window.addEventListener("focus", onWindowFocus);
    document.addEventListener("visibilitychange", onVisibilityChange);
    document.addEventListener("keydown", onKeyDown);

    // ------------------------------------------------------------------
    // Canvas watermark — the watermark is BAKED into the captured image.
    // ------------------------------------------------------------------
    const drawWatermarkedCanvas = async (video) => {
        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 720;

        const canvas = document.createElement("canvas");

        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext("2d");

        if (!ctx) {
            throw new Error("Unable to create capture canvas.");
        }

        // ---------------------------------------------------------------
        // 1. Draw the raw camera frame.
        // ---------------------------------------------------------------
        ctx.drawImage(video, 0, 0, width, height);

        // ---------------------------------------------------------------
        // 2. Apply face blur BEFORE drawing the watermark.
        //
        // This uses the same face-blur implementation already used
        // by the Security module.
        // ---------------------------------------------------------------
        const faceBlur = await initializePrivacyFaceBlur();

        await faceBlur.blurFacesOnCanvas(canvas);

        // ---------------------------------------------------------------
        // 3. Draw the watermark AFTER face anonymization.
        //
        // Student ID is intentionally NOT placed in the watermark.
        // It remains available to the backend/database.
        // ---------------------------------------------------------------
        const lines = [
            "PUPTracker",
            "Captured: " + formatPhTimestamp(),
            "For Official Use Only",
        ];

        let fontSize = 26;

        const maxLineWidth = Math.round(width * 0.86);

        ctx.font = "600 " + fontSize + "px Arial, sans-serif";

        while (
            fontSize > 12 &&
            ctx.measureText(lines[1] + "  " + lines[2]).width > maxLineWidth
        ) {
            fontSize -= 1;

            ctx.font = "600 " + fontSize + "px Arial, sans-serif";
        }

        const padX = 16;

        const lineHeight = Math.round(fontSize * 1.45);

        const blockTop = 14;

        const blockBottom = blockTop + lines.length * lineHeight + 16;

        const blockLeft = padX;

        // Semi-transparent watermark background.
        ctx.fillStyle = "rgba(0,0,0,0.55)";

        ctx.fillRect(0, blockTop - 6, width, blockBottom);

        ctx.textBaseline = "top";
        ctx.textAlign = "left";

        ctx.fillStyle = "#ffffff";

        ctx.shadowColor = "rgba(0,0,0,0.8)";
        ctx.shadowBlur = 4;

        lines.forEach(function (line, i) {
            ctx.fillText(line, blockLeft, blockTop + i * lineHeight);
        });

        ctx.shadowBlur = 0;

        return canvas;
    };

    capturePhotoBtn &&
        capturePhotoBtn.addEventListener("click", async function () {
            if (!cameraActive || cameraPausedByInterruption) {
                return; // Capture disabled while inactive/paused.
            }
            if (!cameraPreview) {
                return;
            }

            let canvas;

            try {
                // Face blur must succeed before the image can become evidence.
                canvas = await drawWatermarkedCanvas(cameraPreview);
            } catch (error) {
                reportSecurityEvent(
                    "CAMERA_ERROR",
                    {
                        reason: "face blur processing failed",
                    },
                    "warning",
                );

                showAlert(
                    "error",
                    "Privacy protection unavailable",
                    "The face-blur protection could not process this image. The photo was not captured. Please try again.",
                );

                return;
            }

            // Stop live camera while reviewing the captured photo.
            stopCamera();

            canvas.toBlob(
                function (blob) {
                    if (!blob) {
                        showAlert(
                            "error",
                            "Capture failed",
                            "Unable to encode the captured photo. Please try again.",
                        );
                        reportSecurityEvent(
                            "CAMERA_ERROR",
                            { reason: "canvas toBlob failed" },
                            "warning",
                        );
                        return;
                    }
                    capturedImageBlob = blob;
                    if (capturedImageObjectUrl) {
                        URL.revokeObjectURL(capturedImageObjectUrl);
                    }
                    capturedImageObjectUrl = URL.createObjectURL(blob);
                    showCaptureReview(blob, capturedImageObjectUrl);
                    reportSecurityEvent(
                        "EVIDENCE_CAPTURED",
                        { bytes: blob.size },
                        "info",
                    );
                },
                "image/jpeg",
                0.92,
            );
        });

    retakePhotoBtn &&
        retakePhotoBtn.addEventListener("click", function () {
            // Retake → return to live camera (security notice already accepted).
            if (capturedImageObjectUrl) {
                URL.revokeObjectURL(capturedImageObjectUrl);
                capturedImageObjectUrl = null;
            }
            capturedImageBlob = null;
            reportSecurityEvent(
                "CAPTURE_ABANDONED",
                { stage: "retake" },
                "info",
            );
            startCamera();
        });

    usePhotoBtn &&
        usePhotoBtn.addEventListener("click", function () {
            if (!capturedImageBlob) {
                return;
            }
            // Derive a display filename for the staged item; the actual disk
            // name is generated server-side from the evidence code.
            const capturedAt = new Date();
            const stamp = formatPhTimestamp(capturedAt).replace(/[^\d]/g, "");
            const fakeName = `EVD_${stamp}.jpg`;
            const file = new File([capturedImageBlob], fakeName, {
                type: "image/jpeg",
                lastModified: capturedAt.getTime(),
            });

            addEvidenceItem({
                file: file,
                method: "camera",
                capturedAt: capturedAt.toISOString(),
                watermarked: true,
            });

            if (capturedImageObjectUrl) {
                URL.revokeObjectURL(capturedImageObjectUrl);
                capturedImageObjectUrl = null;
            }
            capturedImageBlob = null;
            resetCameraPanels();
        });

    // ------------------------------------------------------------------
    // Staged evidence list (camera photos + secure PDF uploads)
    // ------------------------------------------------------------------
    const updateEvidenceCount = () => {
        const badge = document.getElementById("evidenceCountBadge");
        if (badge) {
            badge.textContent = `${evidenceItems.length} / ${MAX_EVIDENCE} files`;
        }
    };

    const addEvidenceItem = (item) => {
        if (evidenceItems.length >= MAX_EVIDENCE) {
            showAlert(
                "warning",
                "Too many files",
                `You may attach at most ${MAX_EVIDENCE} evidence files.`,
            );
            return;
        }
        if (item.file.size > MAX_FILE_BYTES) {
            showAlert(
                "warning",
                "File too large",
                `${item.file.name} exceeds the 5 MB limit.`,
            );
            return;
        }
        evidenceItems.push(item);
        renderEvidenceList();
        updateEvidenceCount();
    };

    const renderEvidenceList = () => {
        const container = document.getElementById("evidencePreviewList");
        if (!container) {
            return;
        }

        if (!evidenceItems.length) {
            container.innerHTML = "";
            return;
        }

        container.innerHTML = evidenceItems
            .map(function (item, index) {
                const isCamera = item.method === "camera";
                const isPdf = !isCamera && item.file.type === "application/pdf";
                const icon = isPdf
                    ? '<i class="bi bi-file-earmark-pdf" style="font-size:1.6rem;color:#b91c1c"></i>'
                    : isCamera
                      ? '<i class="bi bi-camera-fill" style="font-size:1.6rem;color:#7a0f1e"></i>'
                      : '<i class="bi bi-file-earmark-image" style="font-size:1.6rem;color:#7a0f1e"></i>';
                const tag = isCamera
                    ? '<span class="badge text-bg-light border ms-1">watermarked</span>'
                    : isPdf
                      ? '<span class="badge text-bg-light border ms-1">PDF</span>'
                      : '<span class="badge text-bg-light border ms-1">upload</span>';
                return `<div class="border rounded p-2 d-flex align-items-center gap-2" style="max-width:300px">
                    ${icon}
                    <div class="flex-grow-1">
                        <div class="small text-truncate" style="max-width:140px" title="${item.file.name}">${item.file.name}</div>
                        <div class="small text-muted">${(item.file.size / 1024).toFixed(0)} KB ${tag}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-evidence-index="${index}" aria-label="Remove">&times;</button>
                </div>`;
            })
            .join("");
    };

    // PDF / document secure upload.
    evidenceInput &&
        evidenceInput.addEventListener("change", function () {
            const incoming = Array.from(this.files || []);
            incoming.forEach(function (file) {
                addEvidenceItem({
                    file: file,
                    method: "upload",
                    capturedAt: null,
                    watermarked: false,
                });
            });
            this.value = "";
        });

    const evidenceListEl = document.getElementById("evidencePreviewList");
    if (evidenceListEl) {
        evidenceListEl.addEventListener("click", function (event) {
            const btn = event.target.closest("[data-evidence-index]");
            if (!btn) {
                return;
            }
            const index = Number(btn.dataset.evidenceIndex);
            evidenceItems.splice(index, 1);
            renderEvidenceList();
            updateEvidenceCount();
        });
    }

    // ------------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------------
    const resetModal = () => {
        stopCamera();
        document.getElementById("violationForm").reset();
        document.getElementById("selectedStudentNumber").value = "";
        document.getElementById("previewCategory").textContent = "-";
        document.getElementById("previewViolationType").textContent = "-";
        document.getElementById("previewOffenseLevelDisplay").textContent = "-";
        document.getElementById("previewOffenseLevel").value = "";
        document.getElementById("previewSanction").textContent = "-";

        resultBox.innerHTML = "";
        violationTypeSelect.innerHTML =
            '<option value="">Select Category First</option>';
        violationTypeSelect.disabled = true;

        document.getElementById("selectedStudentInfo").classList.add("d-none");
        document.getElementById("noStudentSelected").classList.remove("d-none");

        evidenceItems = [];
        if (identityConfirmed) {
            identityConfirmed.checked = false;
        }
        if (evidenceInput) {
            evidenceInput.value = "";
        }
        const evList = document.getElementById("evidencePreviewList");
        if (evList) {
            evList.innerHTML = "";
        }
        updateEvidenceCount();
        cameraNoticeAccepted = false;
        // Each modal open starts a NEW capture session.
        captureSessionId = null;
        if (capturedImageObjectUrl) {
            URL.revokeObjectURL(capturedImageObjectUrl);
            capturedImageObjectUrl = null;
        }
        capturedImageBlob = null;

        setStep(1);
    };

    // Set the violation date/time input to the current Philippine time (PHT).
    const setCurrentPhTime = () => {
        const violationDateInput = document.getElementById("violationDate");
        if (!violationDateInput) {
            return;
        }

        const now = new Date();
        const parts = new Intl.DateTimeFormat("en-CA", {
            timeZone: "Asia/Manila",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hourCycle: "h23",
        }).formatToParts(now);

        const get = (type) =>
            (parts.find((p) => p.type === type) || {}).value || "00";

        // en-CA gives YYYY-MM-DD style; build a datetime-local value.
        const value = `${get("year")}-${get("month")}-${get("day")}T${get(
            "hour",
        )}:${get("minute")}`;

        if (violationDateInput.value !== value) {
            violationDateInput.value = value;
        }
    };

    let phTimeInterval = null;

    // Start/stop a live clock so the default time stays current while open.
    modalElement.addEventListener("show.bs.modal", function () {
        // Every open is a fresh Record Violation wizard — always begin at the
        // Student step so the Back/Next/Submit footer buttons are shown with the
        // correct visibility (they start hidden in the markup).
        setStep(1);
        setCurrentPhTime();
        if (phTimeInterval) {
            clearInterval(phTimeInterval);
        }
        phTimeInterval = setInterval(setCurrentPhTime, 1000);
    });

    modalElement.addEventListener("hidden.bs.modal", function () {
        if (phTimeInterval) {
            clearInterval(phTimeInterval);
            phTimeInterval = null;
        }
        stopCamera();
    });

    const validateStep = (stepNumber) => {
        if (stepNumber === 1) {
            if (!document.getElementById("selectedStudentNumber").value) {
                showAlert(
                    "warning",
                    "Select a student",
                    "Choose a student before continuing.",
                );
                return false;
            }
            return true;
        }
        if (stepNumber === 2) {
            if (!violationTypeSelect.value) {
                showAlert(
                    "warning",
                    "Select a violation type",
                    "Please choose a violation type.",
                );
                return false;
            }
            if (!document.getElementById("violationDate").value) {
                showAlert(
                    "warning",
                    "Select a date",
                    "Please set the violation date & time.",
                );
                return false;
            }
            if (!document.getElementById("description").value.trim()) {
                showAlert(
                    "warning",
                    "Enter a description",
                    "Please describe the violation.",
                );
                return false;
            }
            return true;
        }
        if (stepNumber === 3) {
            if (evidenceItems.length > MAX_EVIDENCE) {
                showAlert(
                    "warning",
                    "Too many files",
                    `You may attach at most ${MAX_EVIDENCE} evidence files.`,
                );
                return false;
            }
            for (const item of evidenceItems) {
                if (item.file.size > MAX_FILE_BYTES) {
                    showAlert(
                        "warning",
                        "File too large",
                        `${item.file.name} exceeds the 5 MB limit.`,
                    );
                    return false;
                }
                const allowed = [
                    "image/jpeg",
                    "image/png",
                    "image/jpg",
                    "application/pdf",
                ];
                if (!allowed.includes(item.file.type)) {
                    showAlert(
                        "warning",
                        "Unsupported file",
                        `${item.file.name} must be a JPG, PNG, or PDF.`,
                    );
                    return false;
                }
            }
            return true;
        }
        return true;
    };

    const populateReview = () => {
        document.getElementById("reviewStudentNumber").textContent =
            document.getElementById("selectedStudentNumber").value || "-";
        document.getElementById("reviewStudentName").textContent =
            document.getElementById("studentName").value || "-";
        document.getElementById("reviewStudentProgram").textContent =
            document.getElementById("studentProgram").value || "-";
        document.getElementById("reviewStudentYear").textContent =
            document.getElementById("studentYear").value || "-";
        document.getElementById("reviewStudentSection").textContent =
            document.getElementById("studentSection").value || "-";
        document.getElementById("reviewCategory").textContent =
            document.getElementById("previewCategory").textContent || "-";
        document.getElementById("reviewViolationType").textContent =
            document.getElementById("previewViolationType").textContent || "-";
        document.getElementById("reviewDate").textContent = formatReviewDate(
            document.getElementById("violationDate").value,
        );
        document.getElementById("reviewOffenseLevel").textContent =
            document.getElementById("previewOffenseLevelDisplay").textContent ||
            "-";
        document.getElementById("reviewSanction").textContent =
            document.getElementById("previewSanction").textContent || "-";
        document.getElementById("reviewDescription").textContent =
            document.getElementById("description").value.trim() || "-";

        if (evidenceItems.length) {
            const labels = evidenceItems.map(function (item) {
                return (
                    item.file.name +
                    (item.method === "camera" ? " [watermarked]" : "")
                );
            });
            document.getElementById("reviewEvidenceSummary").textContent =
                evidenceItems.length +
                " file(s) attached: " +
                labels.join(", ");
        } else {
            document.getElementById("reviewEvidenceSummary").textContent =
                "No evidence attached.";
        }
    };

    const formatReviewDate = (value) => {
        if (!value) {
            return "-";
        }
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) {
            return value;
        }
        return d.toLocaleString("en-PH", {
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
            timeZone: "Asia/Manila",
        });
    };

    nextBtn.addEventListener("click", function () {
        if (!validateStep(currentStep)) {
            return;
        }
        if (currentStep === 3) {
            populateReview();
        }
        setStep(currentStep + 1);
    });

    backBtn.addEventListener("click", function () {
        if (currentStep > 1) {
            setStep(currentStep - 1);
        }
    });

    const selectStudent = (student) => {
        // An inactive PUPTracker account may not receive a NEW violation.
        // Refuse to select it so the operator is told immediately instead of at
        // the end of the wizard. The server enforces the same rule on submit,
        // so this is a UX guard and NOT the security boundary.
        if (student.account_active === false) {
            showAlert(
                "error",
                "Student account inactive",
                "Violation cannot be recorded because this student account is inactive.",
            );
            return;
        }

        document.getElementById("selectedStudentNumber").value =
            student.student_number;
        document.getElementById("studentNumber").value = student.student_number;
        document.getElementById("studentName").value =
            `${student.last_name}, ${student.first_name}`;
        document.getElementById("studentProgram").value =
            student.program?.program_name ?? "-";
        document.getElementById("studentYear").value =
            student.year?.year_name ?? "-";
        const sectionName =
            student.section?.section_name ?? student.section_name ?? "-";

        document.getElementById("studentSection").value = sectionName;

        document.getElementById("noStudentSelected").classList.add("d-none");
        document
            .getElementById("selectedStudentInfo")
            .classList.remove("d-none");

        resultBox.innerHTML = "";
        searchBox.value = `${student.student_number} - ${student.last_name}, ${student.first_name}`;
        updateLiveWatermark();
    };

    let searchTimer;
    searchBox.addEventListener("keyup", function () {
        const keyword = this.value.trim();

        clearTimeout(searchTimer);

        if (keyword.length < 2) {
            resultBox.innerHTML = "";
            return;
        }

        searchTimer = setTimeout(() => {
            fetch(
                `${window.ViolationRoutes.searchStudent}?search=${encodeURIComponent(keyword)}`,
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error("Unable to search students.");
                    }

                    return response.json();
                })
                .then((data) => {
                    resultBox.innerHTML = "";

                    if (!Array.isArray(data) || data.length === 0) {
                        resultBox.innerHTML =
                            '<div class="list-group-item text-muted">No matching students found.</div>';
                        return;
                    }

                    data.forEach((student) => {
                        const item = document.createElement("button");
                        item.type = "button";
                        item.className =
                            "list-group-item list-group-item-action";

                        // Inactive accounts are surfaced here so the operator
                        // learns BEFORE filling in the whole wizard. This is a
                        // convenience only — the server refuses the submission
                        // regardless (StoreViolationRequest re-checks status).
                        const inactive = student.account_active === false;
                        const badge = inactive
                            ? ' <span class="admin-badge" style="background:#fdecec;color:#8a1c1c;">Inactive account — cannot record</span>'
                            : "";

                        item.innerHTML = `<strong>${student.student_number}</strong>${badge}<br>${student.last_name}, ${student.first_name}`;
                        item.addEventListener("click", function () {
                            selectStudent(student);
                        });
                        resultBox.appendChild(item);
                    });
                })
                .catch(() => {
                    showAlert(
                        "error",
                        "Search failed",
                        "Unable to search students right now.",
                    );
                });
        }, 250);
    });

    categorySelect.addEventListener("change", function () {
        const categoryId = this.value;

        violationTypeSelect.innerHTML = '<option value="">Loading...</option>';
        violationTypeSelect.disabled = true;

        if (!categoryId) {
            violationTypeSelect.innerHTML =
                '<option value="">Select Violation Type</option>';
            return;
        }

        fetch(
            `${window.ViolationRoutes.violationTypes}?category_id=${categoryId}`,
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error("Unable to load violation types.");
                }

                return response.json();
            })
            .then((types) => {
                violationTypeSelect.innerHTML =
                    '<option value="">Select Violation Type</option>';

                types.forEach((type) => {
                    violationTypeSelect.innerHTML += `<option value="${type.violation_type_id}">${type.violation_type}</option>`;
                });

                violationTypeSelect.disabled = false;
            })
            .catch(() => {
                violationTypeSelect.innerHTML =
                    '<option value="">Unable to load</option>';
                showAlert(
                    "error",
                    "Load failed",
                    "Unable to load violation types.",
                );
            });
    });

    violationTypeSelect.addEventListener("change", function () {
        const studentNumber = document.getElementById(
            "selectedStudentNumber",
        ).value;
        const violationTypeId = this.value;

        if (!studentNumber || !violationTypeId) {
            return;
        }

        fetch(window.ViolationRoutes.previewViolation, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: JSON.stringify({
                student_number: studentNumber,
                violation_type_id: violationTypeId,
            }),
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message || "Unable to preview violation details.",
                    );
                }

                return data;
            })
            .then((data) => {
                document.getElementById("previewOffenseLevel").value =
                    data.offense_level ?? "-";
                document.getElementById("previewCategory").textContent =
                    data.category ?? "-";
                document.getElementById("previewViolationType").textContent =
                    data.violation_type ?? "-";
                document.getElementById(
                    "previewOffenseLevelDisplay",
                ).textContent = data.offense_level ?? "-";
                document.getElementById("previewSanction").textContent =
                    data.sanction ?? "-";
            })
            .catch((error) => {
                showAlert("error", "Preview failed", error.message);
            });
    });

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------
    saveBtn.addEventListener("click", function () {
        const studentNumber = document.getElementById(
            "selectedStudentNumber",
        ).value;
        const violationTypeId = document.getElementById("violationType").value;
        const violationDate = document.getElementById("violationDate").value;
        const description = document.getElementById("description").value.trim();

        if (
            !studentNumber ||
            !violationTypeId ||
            !violationDate ||
            !description
        ) {
            showAlert(
                "warning",
                "Missing required fields",
                "Complete all required fields before saving.",
            );
            return;
        }

        if (!identityConfirmed.checked) {
            showAlert(
                "warning",
                "Confirm Student Identity",
                "You must confirm the student identity before recording the violation.",
            );
            return;
        }

        const formData = new FormData();
        formData.append("student_number", studentNumber);
        formData.append("violation_type_id", violationTypeId);
        formData.append("violation_date", violationDate);
        formData.append("description", description);
        formData.append(
            "identity_confirmed",
            identityConfirmed.checked ? "1" : "0",
        );
        // Correlate the submitted evidence to the camera/capture session.
        if (captureSessionId) {
            formData.append("capture_session_id", captureSessionId);
        }
        evidenceItems.forEach(function (item, index) {
            formData.append("evidence[]", item.file, item.file.name);
            formData.append("capture_methods[" + index + "]", item.method);
            formData.append(
                "captured_ats[" + index + "]",
                item.capturedAt || "",
            );
            formData.append(
                "watermarked[" + index + "]",
                item.watermarked ? "1" : "0",
            );
        });

        saveBtn.disabled = true;

        fetch(window.ViolationRoutes.store, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: formData,
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    console.error("Violation submission failed:", data);

                    throw new Error(
                        data.error
                            ? `${data.message || "Unable to submit violation."} ${data.error}`
                            : data.message || "Unable to submit violation.",
                    );
                }
                const modal = bootstrap.Modal.getInstance(modalElement);
                modal.hide();

                await Swal.fire({
                    icon: "success",
                    title: "Violation recorded",
                    text:
                        data.message ||
                        "The violation was recorded successfully.",
                });

                loadManagementTable();
            })
            .catch((error) => {
                showAlert("error", "Submit failed", error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
            });
    });

    modalElement.addEventListener("hidden.bs.modal", resetModal);

    function loadManagementTable() {
        fetch(window.location.href)
            .then((response) => response.text())
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, "text/html");
                const newTable = doc.querySelector("#managementTableContainer");
                const currentTable = document.querySelector(
                    "#managementTableContainer",
                );

                if (newTable && currentTable) {
                    currentTable.innerHTML = newTable.innerHTML;
                } else {
                    window.location.reload();
                }
            })
            .catch(() => {
                window.location.reload();
            });
    }
});
