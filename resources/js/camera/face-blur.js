import { FaceDetector, FilesetResolver } from "@mediapipe/tasks-vision";

const WASM_URL =
    "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1/wasm";
const MODEL_URL = "/vendor/mediapipe/models/blaze_face_short_range.tflite";
const MAX_DETECTION_DIMENSION = 1280;
const MAX_UPSCALE = 2;
const IOU_THRESHOLD = 0.45;
const FACE_HORIZONTAL_PADDING = 0.08;
const FACE_VERTICAL_PADDING = 0.2;
const BLUR_STRENGTH = 32;
const BLUR_PADDING = 45;
const FEATHER_START = 0.68;
// Diagnostics are OFF in normal use. Enable them on a device for
// troubleshooting without a code change by loading the page with
// `?cameraDebug=1`. Logging is metadata-only: image bytes, canvas
// pixels, and student data are never logged.
const CAMERA_DEBUG_ENABLED =
    typeof location !== "undefined" &&
    typeof URLSearchParams !== "undefined" &&
    new URLSearchParams(location.search).has("cameraDebug");

const CAMERA_DEBUG_PREFIX = "[PUPTracker Camera Debug]";

let faceDetector = null;
let faceDetectorInitPromise = null;
let forceCpu = false;
let currentDelegate = null;

export function logCameraDebug(message, details = {}) {
    if (!CAMERA_DEBUG_ENABLED) return;
    // Deliberately log metadata only; never log image, canvas, or student data.
    console.debug(`${CAMERA_DEBUG_PREFIX} ${message}`, details);
}

function classifyEnvironment() {
    const ua = typeof navigator !== "undefined" ? navigator.userAgent : "";
    const isMobile = /Android|iPhone|iPad|iPod|Mobile|Mobi/i.test(ua);

    let browser = "unknown";
    if (/Edg\//.test(ua)) browser = "Edge";
    else if (/Chrome\//.test(ua)) browser = "Chrome";
    else if (/Safari\//.test(ua)) browser = "Safari";
    else if (/Firefox\//.test(ua)) browser = "Firefox";

    let os = "unknown";
    if (/Windows/.test(ua)) os = "Windows";
    else if (/Android/.test(ua)) os = "Android";
    else if (/iPhone|iPad|iPod/.test(ua)) os = "iOS";
    else if (/Mac OS X/.test(ua)) os = "macOS";
    else if (/Linux/.test(ua)) os = "Linux";

    return { userAgent: ua, browser, os, isMobile };
}

export function logCameraEnvironment() {
    if (!CAMERA_DEBUG_ENABLED) return;
    logCameraDebug("environment", {
        ...classifyEnvironment(),
        deviceMemory: navigator.deviceMemory ?? null,
        hardwareConcurrency: navigator.hardwareConcurrency ?? null,
        secureContext: window.isSecureContext,
        maxTouchPoints: navigator.maxTouchPoints ?? null,
    });
}

async function createDetector(delegate) {
    const vision = await FilesetResolver.forVisionTasks(WASM_URL);
    return FaceDetector.createFromOptions(vision, {
        baseOptions: { modelAssetPath: MODEL_URL, delegate },
        runningMode: "IMAGE",
        minDetectionConfidence: 0.35,
    });
}

/** Initialize the detector, preferring GPU and falling back to CPU. */
export async function initializeFaceDetector() {
    if (faceDetector) return faceDetector;
    if (faceDetectorInitPromise) return faceDetectorInitPromise;

    faceDetectorInitPromise = (async () => {
        if (!forceCpu) {
            try {
                faceDetector = await createDetector("GPU");
                currentDelegate = "GPU";
                logCameraDebug("detector initialized", {
                    delegate: currentDelegate,
                });
                return faceDetector;
            } catch (gpuError) {
                forceCpu = true;
                logCameraDebug("GPU initialization failed; using CPU", {
                    message: String(gpuError?.message || gpuError),
                });
            }
        }

        faceDetector = await createDetector("CPU");
        currentDelegate = "CPU";
        logCameraDebug("detector initialized", { delegate: currentDelegate });
        return faceDetector;
    })();

    try {
        return await faceDetectorInitPromise;
    } catch (error) {
        faceDetector = null;
        currentDelegate = null;
        throw error;
    } finally {
        faceDetectorInitPromise = null;
    }
}

/** Reset detector state after a runtime GPU failure. */
export async function resetFaceDetector() {
    const previous = faceDetector;
    faceDetector = null;
    faceDetectorInitPromise = null;
    currentDelegate = null;
    forceCpu = true;

    if (previous && typeof previous.close === "function") {
        try {
            previous.close();
        } catch (error) {
            logCameraDebug("detector close failed", {
                message: String(error),
            });
        }
    }
}

function intersectionOverUnion(a, b) {
    const left = Math.max(a.originX, b.originX);
    const top = Math.max(a.originY, b.originY);
    const right = Math.min(a.originX + a.width, b.originX + b.width);
    const bottom = Math.min(a.originY + a.height, b.originY + b.height);

    const intersection = Math.max(0, right - left) * Math.max(0, bottom - top);

    const union = a.width * a.height + b.width * b.height - intersection;

    return union > 0 ? intersection / union : 0;
}

function detectFacesInCanvas(detector, sourceCanvas) {
    const sourceWidth = sourceCanvas.width;
    const sourceHeight = sourceCanvas.height;

    if (!sourceWidth || !sourceHeight) {
        throw new Error("The camera frame is empty.");
    }

    const scale = Math.min(
        MAX_UPSCALE,
        MAX_DETECTION_DIMENSION / Math.max(sourceWidth, sourceHeight),
    );

    logCameraDebug("detection resolution", {
        sourceWidth,
        sourceHeight,
        scale,
        detectionWidth: Math.max(1, Math.round(sourceWidth * scale)),
        detectionHeight: Math.max(1, Math.round(sourceHeight * scale)),
        delegate: currentDelegate,
    });

    const detections = [];

    const detectRegion = (x, y, width, height) => {
        const detectionCanvas = document.createElement("canvas");

        detectionCanvas.width = Math.max(1, Math.round(width * scale));
        detectionCanvas.height = Math.max(1, Math.round(height * scale));

        const detectionContext = detectionCanvas.getContext("2d", {
            willReadFrequently: true,
        });

        if (!detectionContext) {
            throw new Error("Unable to prepare the face-detection canvas.");
        }

        try {
            detectionContext.drawImage(
                sourceCanvas,
                x,
                y,
                width,
                height,
                0,
                0,
                detectionCanvas.width,
                detectionCanvas.height,
            );

            const result = detector.detect(detectionCanvas);

            for (const item of result.detections ?? []) {
                const box = item.boundingBox;
                if (!box) continue;

                const left = Math.max(0, x + box.originX / scale);
                const top = Math.max(0, y + box.originY / scale);
                const right = Math.min(
                    sourceWidth,
                    x + (box.originX + box.width) / scale,
                );
                const bottom = Math.min(
                    sourceHeight,
                    y + (box.originY + box.height) / scale,
                );

                if (right > left && bottom > top) {
                    detections.push({
                        originX: left,
                        originY: top,
                        width: right - left,
                        height: bottom - top,
                    });
                }
            }
        } finally {
            detectionCanvas.width = 1;
            detectionCanvas.height = 1;
        }
    };

    detectRegion(0, 0, sourceWidth, sourceHeight);

    // Six overlapping regions improve coverage for small and edge-positioned faces.
    const tileWidth = sourceWidth / 2;
    const tileHeight = sourceHeight / 1.5;
    const horizontalStep = (sourceWidth - tileWidth) / 2;
    const verticalStep = sourceHeight - tileHeight;

    for (let row = 0; row < 2; row++) {
        for (let column = 0; column < 3; column++) {
            const x = Math.round(column * horizontalStep);
            const y = Math.round(row * verticalStep);

            detectRegion(
                x,
                y,
                Math.min(tileWidth, sourceWidth - x),
                Math.min(tileHeight, sourceHeight - y),
            );
        }
    }

    const unique = [];

    for (const box of detections) {
        if (
            !unique.some(
                (existing) =>
                    intersectionOverUnion(existing, box) > IOU_THRESHOLD,
            )
        ) {
            unique.push(box);
        }
    }

    return unique;
}

function blurDetectedFaces(sourceCanvas, boxes) {
    const context = sourceCanvas.getContext("2d");

    if (!context) {
        throw new Error("Unable to access the evidence canvas.");
    }

    for (const box of boxes) {
        const faceWidth = box.width * (1 + FACE_HORIZONTAL_PADDING * 2);

        const faceHeight = box.height * (1 + FACE_VERTICAL_PADDING * 2);

        const centerX = box.originX + box.width / 2;
        const centerY = box.originY + box.height / 2;

        const ellipseLeft = Math.max(0, Math.floor(centerX - faceWidth / 2));

        const ellipseTop = Math.max(0, Math.floor(centerY - faceHeight / 2));

        const ellipseRight = Math.min(
            sourceCanvas.width,
            Math.ceil(centerX + faceWidth / 2),
        );

        const ellipseBottom = Math.min(
            sourceCanvas.height,
            Math.ceil(centerY + faceHeight / 2),
        );

        const ellipseWidth = ellipseRight - ellipseLeft;
        const ellipseHeight = ellipseBottom - ellipseTop;

        if (ellipseWidth <= 0 || ellipseHeight <= 0) continue;

        const sourceLeft = Math.max(0, ellipseLeft - BLUR_PADDING);
        const sourceTop = Math.max(0, ellipseTop - BLUR_PADDING);

        const sourceRight = Math.min(
            sourceCanvas.width,
            ellipseRight + BLUR_PADDING,
        );

        const sourceBottom = Math.min(
            sourceCanvas.height,
            ellipseBottom + BLUR_PADDING,
        );

        const width = sourceRight - sourceLeft;
        const height = sourceBottom - sourceTop;

        const original = document.createElement("canvas");
        const blurred = document.createElement("canvas");
        const mask = document.createElement("canvas");
        const output = document.createElement("canvas");

        for (const canvas of [original, blurred, mask, output]) {
            canvas.width = width;
            canvas.height = height;
        }

        const originalContext = original.getContext("2d");
        const blurredContext = blurred.getContext("2d");
        const maskContext = mask.getContext("2d");
        const outputContext = output.getContext("2d");

        if (
            !originalContext ||
            !blurredContext ||
            !maskContext ||
            !outputContext
        ) {
            throw new Error("Unable to prepare the privacy-blur canvas.");
        }

        try {
            originalContext.drawImage(
                sourceCanvas,
                sourceLeft,
                sourceTop,
                width,
                height,
                0,
                0,
                width,
                height,
            );

            blurredContext.filter = `blur(${BLUR_STRENGTH}px)`;

            blurredContext.drawImage(
                original,
                -BLUR_PADDING / 2,
                -BLUR_PADDING / 2,
                width + BLUR_PADDING,
                height + BLUR_PADDING,
            );

            const localX = centerX - sourceLeft;
            const localY = centerY - sourceTop;

            maskContext.save();
            maskContext.translate(localX, localY);
            maskContext.scale(ellipseWidth / 2, ellipseHeight / 2);

            const gradient = maskContext.createRadialGradient(0, 0, 0, 0, 0, 1);

            gradient.addColorStop(0, "rgba(255,255,255,1)");
            gradient.addColorStop(FEATHER_START, "rgba(255,255,255,1)");
            gradient.addColorStop(0.86, "rgba(255,255,255,0.75)");
            gradient.addColorStop(0.94, "rgba(255,255,255,0.35)");
            gradient.addColorStop(1, "rgba(255,255,255,0)");

            maskContext.fillStyle = gradient;
            maskContext.beginPath();
            maskContext.arc(0, 0, 1, 0, Math.PI * 2);
            maskContext.fill();
            maskContext.restore();

            outputContext.drawImage(blurred, 0, 0);
            outputContext.globalCompositeOperation = "destination-in";
            outputContext.drawImage(mask, 0, 0);
            outputContext.globalCompositeOperation = "source-over";

            context.drawImage(
                output,
                0,
                0,
                width,
                height,
                sourceLeft,
                sourceTop,
                width,
                height,
            );
        } finally {
            for (const canvas of [original, blurred, mask, output]) {
                canvas.width = 1;
                canvas.height = 1;
            }
        }
    }
}

function canvasHasImageData(canvas) {
    const context = canvas.getContext("2d", {
        willReadFrequently: true,
    });

    if (!context || !canvas.width || !canvas.height) {
        return false;
    }

    const sample = document.createElement("canvas");
    sample.width = 4;
    sample.height = 4;

    const sampleContext = sample.getContext("2d", {
        willReadFrequently: true,
    });

    if (!sampleContext) return false;

    try {
        sampleContext.drawImage(canvas, 0, 0, 4, 4);

        const pixels = sampleContext.getImageData(0, 0, 4, 4).data;

        for (let i = 3; i < pixels.length; i += 4) {
            if (pixels[i] !== 0) return true;
        }

        return false;
    } catch (error) {
        throw new Error(
            `Unable to inspect the camera frame: ${error.message || error}`,
        );
    } finally {
        sample.width = 1;
        sample.height = 1;
    }
}

/**
 * Detect and blur faces.
 *
 * Outcome contract (conditional blur):
 *  - One or more faces detected -> blur every detected face.
 *  - Zero faces detected successfully -> preserve the original frame. This is
 *    NOT a failure; the caller accepts the unblurred photograph.
 *  - Technical failure (detector init fails, or detect() throws on every
 *    available delegate) -> throw so the caller blocks submission. A technical
 *    failure is never mistaken for a legitimate zero-face result.
 *
 * @param {HTMLCanvasElement} sourceCanvas
 * @param {(() => void)|null} sampleFrame
 * Optional callback to redraw a fresh video frame before retries.
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function blurFacesOnCanvas(sourceCanvas, sampleFrame = null) {
    if (!(sourceCanvas instanceof HTMLCanvasElement)) {
        throw new TypeError("A valid evidence canvas is required.");
    }

    if (!canvasHasImageData(sourceCanvas)) {
        throw new Error("The camera frame is blank. Please retake the photo.");
    }

    let detector = await initializeFaceDetector();
    let boxes = [];
    let gpuFailure = false;
    let usedCpuFallback = false;
    let detectionError = null;
    const detectionStartedAt = performance.now();

    if (currentDelegate === "GPU") {
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                if (attempt > 0 && typeof sampleFrame === "function") {
                    sampleFrame();
                }

                boxes = detectFacesInCanvas(detector, sourceCanvas);

                if (boxes.length > 0) break;
            } catch (error) {
                gpuFailure = true;

                logCameraDebug("GPU detection failed", {
                    attempt: attempt + 1,
                    message: String(error?.message || error),
                });

                break;
            }
        }

        // A zero-face GPU result is treated as inconclusive and checked on CPU.
        if (gpuFailure || boxes.length === 0) {
            if (!gpuFailure) {
                logCameraDebug(
                    "GPU detection returned zero faces; switching to CPU delegate",
                    { attempts: 3 },
                );
            }

            usedCpuFallback = true;
            await resetFaceDetector();
            detector = await initializeFaceDetector();

            if (typeof sampleFrame === "function") {
                sampleFrame();
            }

            try {
                boxes = detectFacesInCanvas(detector, sourceCanvas);
            } catch (cpuError) {
                detectionError = cpuError;
                logCameraDebug("CPU detection failed", {
                    message: String(cpuError?.message || cpuError),
                });
                boxes = [];
            }
        }
    } else {
        try {
            boxes = detectFacesInCanvas(detector, sourceCanvas);
        } catch (cpuError) {
            detectionError = cpuError;
            logCameraDebug("CPU detection failed", {
                message: String(cpuError?.message || cpuError),
            });
            boxes = [];
        }
    }

    logCameraDebug("detection complete", {
        delegate: currentDelegate,
        faces: boxes.length,
        durationMs: Math.round(performance.now() - detectionStartedAt),
        cpuFallback: usedCpuFallback,
    });

    // A technical failure is not a zero-face result: block submission.
    if (detectionError) {
        logCameraDebug("face detection technical failure", {
            delegate: currentDelegate,
            message: String(detectionError?.message || detectionError),
        });

        throw new Error(
            "Face detection could not be completed. For privacy, this photo cannot be captured. Please try again.",
        );
    }

    if (boxes.length > 0) {
        blurDetectedFaces(sourceCanvas, boxes);

        logCameraDebug("face blur applied", {
            count: boxes.length,
            delegate: currentDelegate,
            canvasWidth: sourceCanvas.width,
            canvasHeight: sourceCanvas.height,
            cpuFallback: usedCpuFallback,
        });
    } else {
        logCameraDebug("no faces detected; preserving original frame", {
            delegate: currentDelegate,
            cpuFallback: usedCpuFallback,
        });
    }

    return sourceCanvas;
}
