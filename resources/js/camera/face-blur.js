import { FaceDetector, FilesetResolver } from "@mediapipe/tasks-vision";

// MediaPipe WASM + model sources.
const WASM_BASE_URL =
    "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1/wasm";
const MODEL_ASSET_PATH =
    "/vendor/mediapipe/models/blaze_face_short_range.tflite";

// ---------------------------------------------------------------------------
// Detector lifecycle
// ---------------------------------------------------------------------------
// A single detector instance is shared across every capture. Initialization is
// guarded by one in-flight promise so concurrent captures / camera switches can
// never spin up multiple detectors or overwrite the shared instance with a
// half-initialized one.
let faceDetector = null;
let faceDetectorInitPromise = null;

/**
 * Create a detector with the requested delegate ("GPU" or "CPU").
 */
async function createDetector(delegate) {
    const vision = await FilesetResolver.forVisionTasks(WASM_BASE_URL);

    return FaceDetector.createFromOptions(vision, {
        baseOptions: {
            modelAssetPath: MODEL_ASSET_PATH,
            delegate,
        },
        runningMode: "IMAGE",
        minDetectionConfidence: 0.35,
    });
}

/**
 * Initialize (or return) the shared MediaPipe face detector.
 *
 * - Concurrent callers share one initialization promise (no duplicate init).
 * - GPU is preferred; if GPU initialization fails, the detector transparently
 *   falls back to CPU so detection still works on devices without WebGL.
 */
export async function initializeFaceDetector() {
    if (faceDetector) {
        return faceDetector;
    }

    if (faceDetectorInitPromise) {
        return faceDetectorInitPromise;
    }

    faceDetectorInitPromise = (async () => {
        try {
            faceDetector = await createDetector("GPU");
        } catch (gpuError) {
            faceDetector = await createDetector("CPU");
        }
        return faceDetector;
    })();

    return faceDetectorInitPromise;
}

/**
 * Dispose and forget the current detector so the next initialize call rebuilds
 * it. Used to recover from a degraded GPU / detection state between retries.
 */
export async function resetFaceDetector() {
    const detector = faceDetector;
    faceDetector = null;
    faceDetectorInitPromise = null;

    if (detector && typeof detector.close === "function") {
        try {
            detector.close();
        } catch (e) {
            // Ignore close failures; the shared reference is already dropped.
        }
    }
}

// ---------------------------------------------------------------------------
// Face detection
// ---------------------------------------------------------------------------
const DETECTION_SCALE = 2;
const HORIZONTAL_TILES = 3;
const VERTICAL_TILES = 2;

function detectFaces(detector, sourceCanvas) {
    const detections = [];

    const detectRegion = (regionX, regionY, regionWidth, regionHeight) => {
        const detectionCanvas = document.createElement("canvas");

        detectionCanvas.width = Math.max(
            1,
            Math.round(regionWidth * DETECTION_SCALE),
        );
        detectionCanvas.height = Math.max(
            1,
            Math.round(regionHeight * DETECTION_SCALE),
        );

        const detectionCtx = detectionCanvas.getContext("2d");

        if (!detectionCtx) {
            return;
        }

        detectionCtx.drawImage(
            sourceCanvas,
            regionX,
            regionY,
            regionWidth,
            regionHeight,
            0,
            0,
            detectionCanvas.width,
            detectionCanvas.height,
        );

        const result = detector.detect(detectionCanvas);

        for (const detection of result.detections ?? []) {
            const box = detection.boundingBox;

            if (!box) {
                continue;
            }

            const originalX = regionX + box.originX / DETECTION_SCALE;
            const originalY = regionY + box.originY / DETECTION_SCALE;
            const originalWidth = box.width / DETECTION_SCALE;
            const originalHeight = box.height / DETECTION_SCALE;

            if (originalWidth <= 0 || originalHeight <= 0) {
                continue;
            }

            const left = Math.max(0, originalX);
            const top = Math.max(0, originalY);
            const right = Math.min(
                sourceCanvas.width,
                originalX + originalWidth,
            );
            const bottom = Math.min(
                sourceCanvas.height,
                originalY + originalHeight,
            );

            const width = right - left;
            const height = bottom - top;

            if (width <= 0 || height <= 0) {
                continue;
            }

            detections.push({
                boundingBox: {
                    originX: left,
                    originY: top,
                    width,
                    height,
                },
            });
        }

        detectionCanvas.width = 1;
        detectionCanvas.height = 1;
    };

    // Pass 1 — full image.
    detectRegion(0, 0, sourceCanvas.width, sourceCanvas.height);

    // Pass 2 — overlapping regions for small / edge / partial faces.
    const tileWidth = sourceCanvas.width / 2;
    const tileHeight = sourceCanvas.height / 1.5;

    const horizontalStep =
        (sourceCanvas.width - tileWidth) / (HORIZONTAL_TILES - 1);
    const verticalStep =
        (sourceCanvas.height - tileHeight) / (VERTICAL_TILES - 1);

    for (let row = 0; row < VERTICAL_TILES; row++) {
        for (let column = 0; column < HORIZONTAL_TILES; column++) {
            const x = Math.max(0, Math.round(column * horizontalStep));
            const y = Math.max(0, Math.round(row * verticalStep));
            const width = Math.min(tileWidth, sourceCanvas.width - x);
            const height = Math.min(tileHeight, sourceCanvas.height - y);

            if (width <= 0 || height <= 0) {
                continue;
            }

            detectRegion(x, y, width, height);
        }
    }

    // Remove duplicate detections from overlapping regions.
    const uniqueDetections = [];

    const intersectionOverUnion = (a, b) => {
        const ax1 = a.originX;
        const ay1 = a.originY;
        const ax2 = a.originX + a.width;
        const ay2 = a.originY + a.height;

        const bx1 = b.originX;
        const by1 = b.originY;
        const bx2 = b.originX + b.width;
        const by2 = b.originY + b.height;

        const intersectionLeft = Math.max(ax1, bx1);
        const intersectionTop = Math.max(ay1, by1);
        const intersectionRight = Math.min(ax2, bx2);
        const intersectionBottom = Math.min(ay2, by2);

        const intersectionWidth = Math.max(
            0,
            intersectionRight - intersectionLeft,
        );
        const intersectionHeight = Math.max(
            0,
            intersectionBottom - intersectionTop,
        );

        const intersectionArea = intersectionWidth * intersectionHeight;
        const areaA = a.width * a.height;
        const areaB = b.width * b.height;
        const unionArea = areaA + areaB - intersectionArea;

        if (unionArea <= 0) {
            return 0;
        }

        return intersectionArea / unionArea;
    };

    for (const detection of detections) {
        const box = detection.boundingBox;

        const duplicate = uniqueDetections.some(
            (existing) =>
                intersectionOverUnion(existing.boundingBox, box) > 0.45,
        );

        if (!duplicate) {
            uniqueDetections.push(detection);
        }
    }

    return uniqueDetections;
}

// ---------------------------------------------------------------------------
// Face blur (privacy) rendering
// ---------------------------------------------------------------------------
const FACE_HORIZONTAL_PADDING = 0.08;
const FACE_VERTICAL_PADDING = 0.2;
const BLUR_STRENGTH = 32;
const BLUR_PADDING = 45;
const FEATHER_START = 0.68;
const FEATHER_END = 1.0;

function applyBlur(sourceCanvas, ctx, uniqueDetections) {
    for (const detection of uniqueDetections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        const faceWidth = box.width * (1 + FACE_HORIZONTAL_PADDING * 2);
        const faceHeight = box.height * (1 + FACE_VERTICAL_PADDING * 2);
        const faceCenterX = box.originX + box.width / 2;
        const faceCenterY = box.originY + box.height / 2;

        const ellipseLeft = Math.max(
            0,
            Math.floor(faceCenterX - faceWidth / 2),
        );
        const ellipseTop = Math.max(
            0,
            Math.floor(faceCenterY - faceHeight / 2),
        );
        const ellipseRight = Math.min(
            sourceCanvas.width,
            Math.ceil(faceCenterX + faceWidth / 2),
        );
        const ellipseBottom = Math.min(
            sourceCanvas.height,
            Math.ceil(faceCenterY + faceHeight / 2),
        );

        const ellipseWidth = ellipseRight - ellipseLeft;
        const ellipseHeight = ellipseBottom - ellipseTop;

        if (ellipseWidth <= 0 || ellipseHeight <= 0) {
            continue;
        }

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

        const sourceWidth = sourceRight - sourceLeft;
        const sourceHeight = sourceBottom - sourceTop;

        if (sourceWidth <= 0 || sourceHeight <= 0) {
            continue;
        }

        // 1. Copy the original region.
        const originalCanvas = document.createElement("canvas");
        originalCanvas.width = sourceWidth;
        originalCanvas.height = sourceHeight;

        const originalCtx = originalCanvas.getContext("2d");
        if (!originalCtx) {
            continue;
        }

        originalCtx.drawImage(
            sourceCanvas,
            sourceLeft,
            sourceTop,
            sourceWidth,
            sourceHeight,
            0,
            0,
            sourceWidth,
            sourceHeight,
        );

        // 2. Create the blurred version.
        const blurredCanvas = document.createElement("canvas");
        blurredCanvas.width = sourceWidth;
        blurredCanvas.height = sourceHeight;

        const blurredCtx = blurredCanvas.getContext("2d");
        if (!blurredCtx) {
            continue;
        }

        blurredCtx.save();
        blurredCtx.filter = `blur(${BLUR_STRENGTH}px)`;
        blurredCtx.drawImage(
            originalCanvas,
            -BLUR_PADDING / 2,
            -BLUR_PADDING / 2,
            sourceWidth + BLUR_PADDING,
            sourceHeight + BLUR_PADDING,
        );
        blurredCtx.restore();

        // 3. Create the feathered oval mask.
        const maskCanvas = document.createElement("canvas");
        maskCanvas.width = sourceWidth;
        maskCanvas.height = sourceHeight;

        const maskCtx = maskCanvas.getContext("2d");
        if (!maskCtx) {
            continue;
        }

        const localCenterX = faceCenterX - sourceLeft;
        const localCenterY = faceCenterY - sourceTop;

        const radiusX = ellipseWidth / 2;
        const radiusY = ellipseHeight / 2;

        maskCtx.save();
        maskCtx.translate(localCenterX, localCenterY);
        maskCtx.scale(radiusX, radiusY);

        const gradient = maskCtx.createRadialGradient(0, 0, 0, 0, 0, 1);

        gradient.addColorStop(0, "rgba(255,255,255,1)");
        gradient.addColorStop(FEATHER_START, "rgba(255,255,255,1)");
        gradient.addColorStop(0.86, "rgba(255,255,255,0.75)");
        gradient.addColorStop(0.94, "rgba(255,255,255,0.35)");
        gradient.addColorStop(FEATHER_END, "rgba(255,255,255,0)");

        maskCtx.fillStyle = gradient;
        maskCtx.beginPath();
        maskCtx.arc(0, 0, 1, 0, Math.PI * 2);
        maskCtx.fill();
        maskCtx.restore();

        // 4. Apply the mask to the blurred image.
        const maskedBlurCanvas = document.createElement("canvas");
        maskedBlurCanvas.width = sourceWidth;
        maskedBlurCanvas.height = sourceHeight;

        const maskedBlurCtx = maskedBlurCanvas.getContext("2d");
        if (!maskedBlurCtx) {
            continue;
        }

        maskedBlurCtx.drawImage(blurredCanvas, 0, 0);

        maskedBlurCtx.globalCompositeOperation = "destination-in";
        maskedBlurCtx.drawImage(maskCanvas, 0, 0);
        maskedBlurCtx.globalCompositeOperation = "source-over";

        // 5. Composite the feathered blur over the original.
        ctx.drawImage(
            maskedBlurCanvas,
            0,
            0,
            sourceWidth,
            sourceHeight,
            sourceLeft,
            sourceTop,
            sourceWidth,
            sourceHeight,
        );

        // Release temporary canvas memory.
        originalCanvas.width = 1;
        originalCanvas.height = 1;
        blurredCanvas.width = 1;
        blurredCanvas.height = 1;
        maskCanvas.width = 1;
        maskCanvas.height = 1;
        maskedBlurCanvas.width = 1;
        maskedBlurCanvas.height = 1;
    }
}

/**
 * Detect faces and blur them on a canvas.
 *
 * PRIVACY FAIL-SAFE: this function never returns an unblurred canvas. If no
 * face is detected it retries (re-sampling the latest camera frame when a
 * `sampleFrame` callback is supplied, otherwise rebuilding the detector) and,
 * if detection still fails, throws so the caller must block evidence capture.
 *
 * @param {HTMLCanvasElement} sourceCanvas
 * @param {Function} [sampleFrame] Optional () => void that redraws the latest
 *     camera frame into `sourceCanvas`.
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function blurFacesOnCanvas(sourceCanvas, sampleFrame = null) {
    const ctx = sourceCanvas.getContext("2d");

    if (!ctx) {
        throw new Error(
            "Privacy protection could not access the canvas. The photo was not saved.",
        );
    }

    const maxAttempts = typeof sampleFrame === "function" ? 3 : 2;

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        const detector = await initializeFaceDetector();
        const uniqueDetections = detectFaces(detector, sourceCanvas);

        if (uniqueDetections.length > 0) {
            applyBlur(sourceCanvas, ctx, uniqueDetections);
            return sourceCanvas;
        }

        // No faces found on this attempt. Re-sample the latest camera frame so
        // the detector sees fresh pixels, or rebuild a possibly degraded
        // detector, then retry.
        if (typeof sampleFrame === "function") {
            sampleFrame();
        } else {
            await resetFaceDetector();
        }
    }

    throw new Error(
        "Privacy protection could not identify any faces. The photo was not saved because an unblurred image must never be submitted.",
    );
}