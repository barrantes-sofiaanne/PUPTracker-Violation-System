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
     * Face privacy configuration.
     *
     * The horizontal padding stays relatively small so that
     * important hair details remain visible.
     *
     * The vertical padding is larger so the forehead and chin
     * remain protected.
     */
    const FACE_HORIZONTAL_PADDING = 0.08;
    const FACE_VERTICAL_PADDING = 0.28;

    /*
     * Strong blur for the central facial area.
     */
    const BLUR_STRENGTH = 32;

    /*
     * Additional source area around the face.
     * This prevents the blur from being clipped.
     */
    const BLUR_PADDING = 45;

    /*
     * Feathering controls.
     *
     * The first value keeps the center fully blurred.
     * The second value determines where the blur begins
     * fading toward the original image.
     */
    const FEATHER_START = 0.68;
    const FEATHER_END = 1.0;

    for (const detection of detections.detections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        /*
         * Expand the detected face slightly.
         */
        const faceWidth = box.width * (1 + FACE_HORIZONTAL_PADDING * 2);

        const faceHeight = box.height * (1 + FACE_VERTICAL_PADDING * 2);

        const faceCenterX = box.originX + box.width / 2;

        const faceCenterY = box.originY + box.height / 2;

        /*
         * Ellipse boundaries.
         */
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

        /*
         * Expand the source region so the blur has surrounding
         * pixels to work with.
         */
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

        /*
         * ---------------------------------------------
         * 1. Copy the original region
         * ---------------------------------------------
         */
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

        /*
         * ---------------------------------------------
         * 2. Create the blurred version
         * ---------------------------------------------
         */
        const blurredCanvas = document.createElement("canvas");

        blurredCanvas.width = sourceWidth;
        blurredCanvas.height = sourceHeight;

        const blurredCtx = blurredCanvas.getContext("2d");

        if (!blurredCtx) {
            continue;
        }

        blurredCtx.save();

        blurredCtx.filter = `blur(${BLUR_STRENGTH}px)`;

        /*
         * Slight scaling prevents the blur from producing
         * a visible unblurred border.
         */
        blurredCtx.drawImage(
            originalCanvas,
            -BLUR_PADDING / 2,
            -BLUR_PADDING / 2,
            sourceWidth + BLUR_PADDING,
            sourceHeight + BLUR_PADDING,
        );

        blurredCtx.restore();

        /*
         * ---------------------------------------------
         * 3. Create the feathered oval mask
         * ---------------------------------------------
         */
        const maskCanvas = document.createElement("canvas");

        maskCanvas.width = sourceWidth;
        maskCanvas.height = sourceHeight;

        const maskCtx = maskCanvas.getContext("2d");

        if (!maskCtx) {
            continue;
        }

        /*
         * Face center relative to temporary canvas.
         */
        const localCenterX = faceCenterX - sourceLeft;

        const localCenterY = faceCenterY - sourceTop;

        /*
         * The ellipse is slightly smaller than the entire
         * source region because the surrounding area needs
         * to remain sharp.
         */
        const radiusX = ellipseWidth / 2;

        const radiusY = ellipseHeight / 2;

        /*
         * Draw a radial gradient and stretch it vertically/
         * horizontally into an ellipse.
         */
        maskCtx.save();

        maskCtx.translate(localCenterX, localCenterY);

        maskCtx.scale(radiusX, radiusY);

        const gradient = maskCtx.createRadialGradient(0, 0, 0, 0, 0, 1);

        /*
         * Center:
         * completely blurred.
         */
        gradient.addColorStop(0, "rgba(255,255,255,1)");

        /*
         * Keep almost the entire face strongly blurred.
         */
        gradient.addColorStop(FEATHER_START, "rgba(255,255,255,1)");

        /*
         * Begin gradually reducing the blur.
         */
        gradient.addColorStop(0.86, "rgba(255,255,255,0.75)");

        gradient.addColorStop(0.94, "rgba(255,255,255,0.35)");

        /*
         * Outside the ellipse:
         * original image remains visible.
         */
        gradient.addColorStop(FEATHER_END, "rgba(255,255,255,0)");

        maskCtx.fillStyle = gradient;

        maskCtx.beginPath();
        maskCtx.arc(0, 0, 1, 0, Math.PI * 2);
        maskCtx.fill();

        maskCtx.restore();

        /*
         * ---------------------------------------------
         * 4. Apply the mask to the blurred image
         * ---------------------------------------------
         */
        const maskedBlurCanvas = document.createElement("canvas");

        maskedBlurCanvas.width = sourceWidth;
        maskedBlurCanvas.height = sourceHeight;

        const maskedBlurCtx = maskedBlurCanvas.getContext("2d");

        if (!maskedBlurCtx) {
            continue;
        }

        /*
         * Draw blurred image first.
         */
        maskedBlurCtx.drawImage(blurredCanvas, 0, 0);

        /*
         * Keep only the portion covered by the
         * feathered mask.
         */
        maskedBlurCtx.globalCompositeOperation = "destination-in";

        maskedBlurCtx.drawImage(maskCanvas, 0, 0);

        maskedBlurCtx.globalCompositeOperation = "source-over";

        /*
         * ---------------------------------------------
         * 5. Put the feathered blur over the original
         * ---------------------------------------------
         *
         * The original image underneath remains sharp.
         * Only the masked facial region is replaced.
         */
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

        /*
         * Release temporary canvas memory.
         */
        originalCanvas.width = 1;
        originalCanvas.height = 1;

        blurredCanvas.width = 1;
        blurredCanvas.height = 1;

        maskCanvas.width = 1;
        maskCanvas.height = 1;

        maskedBlurCanvas.width = 1;
        maskedBlurCanvas.height = 1;
    }

    return sourceCanvas;
}
