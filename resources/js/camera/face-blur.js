import { FaceDetector, FilesetResolver } from "@mediapipe/tasks-vision";

let faceDetector = null;

/**
 * Initialize MediaPipe Face Detector.
 */
export async function initializeFaceDetector() {
    if (faceDetector) {
        return faceDetector;
    }

    const vision = await FilesetResolver.forVisionTasks(
        "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1/wasm",
    );

    faceDetector = await FaceDetector.createFromOptions(vision, {
        baseOptions: {
            // Use the locally hosted model.
            // This keeps the model request same-origin and avoids
            // depending on the old Google Storage model URL.
            modelAssetPath:
                "/vendor/mediapipe/models/blaze_face_short_range.tflite",
            delegate: "GPU",
        },
        runningMode: "IMAGE",
        minDetectionConfidence: 0.5,
    });

    return faceDetector;
}

/**
 * Detect faces and blur them on a canvas.
 *
 * @param {HTMLCanvasElement} sourceCanvas
 * @returns {HTMLCanvasElement}
 */
export async function blurFacesOnCanvas(sourceCanvas) {
    const detector = await initializeFaceDetector();

    const detections = detector.detect(sourceCanvas);

    if (!detections.detections || detections.detections.length === 0) {
        return sourceCanvas;
    }

    const ctx = sourceCanvas.getContext("2d");

    /*
     * Privacy settings.
     *
     * The detected MediaPipe box is intentionally expanded because
     * the detector does not necessarily cover the entire visible
     * facial area, especially when the subject is farther from the camera.
     */
    const BLUR_MARGIN_X = 0.45;
    const BLUR_MARGIN_Y = 0.5;

    /*
     * Strong, consistent Gaussian blur.
     */
    const BLUR_STRENGTH = 32;

    /*
     * Additional pixels around the blur region.
     *
     * This prevents the browser's blur filter from being clipped
     * directly at the edge of the temporary canvas.
     */
    const BLUR_PADDING = 40;

    for (const detection of detections.detections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        /*
         * Expand the detected face region substantially.
         */
        const marginX = Math.max(20, Math.round(box.width * BLUR_MARGIN_X));

        const marginY = Math.max(20, Math.round(box.height * BLUR_MARGIN_Y));

        /*
         * Main privacy region.
         */
        const privacyLeft = Math.max(0, Math.floor(box.originX - marginX));

        const privacyTop = Math.max(0, Math.floor(box.originY - marginY));

        const privacyRight = Math.min(
            sourceCanvas.width,
            Math.ceil(box.originX + box.width + marginX),
        );

        const privacyBottom = Math.min(
            sourceCanvas.height,
            Math.ceil(box.originY + box.height + marginY),
        );

        const privacyWidth = privacyRight - privacyLeft;
        const privacyHeight = privacyBottom - privacyTop;

        if (privacyWidth <= 0 || privacyHeight <= 0) {
            continue;
        }

        /*
         * Expand the source area beyond the privacy region.
         *
         * The blur needs neighboring pixels to produce a smooth
         * transition. Without this, the blur can be clipped at
         * the temporary canvas boundary.
         */
        const sourceLeft = Math.max(0, privacyLeft - BLUR_PADDING);

        const sourceTop = Math.max(0, privacyTop - BLUR_PADDING);

        const sourceRight = Math.min(
            sourceCanvas.width,
            privacyRight + BLUR_PADDING,
        );

        const sourceBottom = Math.min(
            sourceCanvas.height,
            privacyBottom + BLUR_PADDING,
        );

        const sourceWidth = sourceRight - sourceLeft;
        const sourceHeight = sourceBottom - sourceTop;

        if (sourceWidth <= 0 || sourceHeight <= 0) {
            continue;
        }

        /*
         * Create an off-screen canvas containing extra pixels
         * around the face.
         */
        const tempCanvas = document.createElement("canvas");

        tempCanvas.width = sourceWidth;
        tempCanvas.height = sourceHeight;

        const tempCtx = tempCanvas.getContext("2d", {
            willReadFrequently: false,
        });

        if (!tempCtx) {
            continue;
        }

        /*
         * Copy the original image into the temporary canvas.
         */
        tempCtx.drawImage(
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

        /*
         * Apply strong Gaussian blur to the entire expanded region.
         *
         * Because the temporary canvas includes padding, the blur
         * does not abruptly stop at the face boundary.
         */
        tempCtx.save();

        tempCtx.filter = `blur(${BLUR_STRENGTH}px)`;

        /*
         * Draw the image slightly offset so the blur has room
         * to spread naturally around the edges.
         */
        tempCtx.drawImage(
            tempCanvas,
            -BLUR_PADDING / 2,
            -BLUR_PADDING / 2,
            sourceWidth + BLUR_PADDING,
            sourceHeight + BLUR_PADDING,
        );

        tempCtx.restore();

        /*
         * Calculate where the privacy region begins inside
         * the temporary canvas.
         */
        const localX = privacyLeft - sourceLeft;
        const localY = privacyTop - sourceTop;

        /*
         * Only replace the expanded privacy region on the
         * original image.
         *
         * This keeps the rest of the photograph sharp while
         * providing a larger, stronger privacy zone around
         * the detected face.
         */
        ctx.save();

        ctx.beginPath();

        ctx.rect(privacyLeft, privacyTop, privacyWidth, privacyHeight);

        ctx.clip();

        ctx.drawImage(
            tempCanvas,
            0,
            0,
            sourceWidth,
            sourceHeight,
            sourceLeft,
            sourceTop,
            sourceWidth,
            sourceHeight,
        );

        ctx.restore();

        /*
         * Release the temporary canvas.
         */
        tempCanvas.width = 1;
        tempCanvas.height = 1;
    }

    return sourceCanvas;
}
