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
     * Privacy configuration.
     *
     * We intentionally use an oval mask instead of a rectangle.
     * This keeps the person's hair and surrounding evidence visible
     * while protecting the facial features.
     */

    // Slightly enlarge the detected face.
    const FACE_HORIZONTAL_PADDING = 0.08;
    const FACE_VERTICAL_PADDING = 0.2;

    const BLUR_STRENGTH = 32;

    // Extra source pixels used so the blur itself is not clipped.
    const BLUR_PADDING = 45;

    for (const detection of detections.detections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        /*
         * Expand the MediaPipe face box slightly.
         *
         * Keep this relatively small because we do NOT want to
         * accidentally include the person's hair.
         */
        const faceWidth = box.width * (1 + FACE_HORIZONTAL_PADDING * 2);

        const faceHeight = box.height * (1 + FACE_VERTICAL_PADDING * 2);

        const faceCenterX = box.originX + box.width / 2;

        const faceCenterY = box.originY + box.height / 2;

        /*
         * Oval boundaries.
         */
        const ellipseLeft = Math.max(
            0,
            Math.round(faceCenterX - faceWidth / 2),
        );

        const ellipseTop = Math.max(
            0,
            Math.round(faceCenterY - faceHeight / 2),
        );

        const ellipseRight = Math.min(
            sourceCanvas.width,
            Math.round(faceCenterX + faceWidth / 2),
        );

        const ellipseBottom = Math.min(
            sourceCanvas.height,
            Math.round(faceCenterY + faceHeight / 2),
        );

        const ellipseWidth = ellipseRight - ellipseLeft;

        const ellipseHeight = ellipseBottom - ellipseTop;

        if (ellipseWidth <= 0 || ellipseHeight <= 0) {
            continue;
        }

        /*
         * Create a larger source region around the face.
         *
         * This gives the blur filter enough surrounding pixels
         * to produce a smooth result.
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
         * Temporary canvas.
         */
        const tempCanvas = document.createElement("canvas");

        tempCanvas.width = sourceWidth;
        tempCanvas.height = sourceHeight;

        const tempCtx = tempCanvas.getContext("2d");

        if (!tempCtx) {
            continue;
        }

        /*
         * Copy the original image region.
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
         * Apply the strong blur to the temporary region.
         */
        const blurredCanvas = document.createElement("canvas");

        blurredCanvas.width = sourceWidth;
        blurredCanvas.height = sourceHeight;

        const blurredCtx = blurredCanvas.getContext("2d");

        if (!blurredCtx) {
            continue;
        }

        blurredCtx.filter = `blur(${BLUR_STRENGTH}px)`;

        blurredCtx.drawImage(tempCanvas, 0, 0);

        blurredCtx.filter = "none";

        /*
         * Only the oval face area will be copied back.
         *
         * This is the important part:
         *
         * Hair outside the oval remains completely sharp.
         */
        ctx.save();

        ctx.beginPath();

        const localCenterX = faceCenterX - sourceLeft;

        const localCenterY = faceCenterY - sourceTop;

        ctx.ellipse(
            faceCenterX,
            faceCenterY,
            ellipseWidth / 2,
            ellipseHeight / 2,
            0,
            0,
            Math.PI * 2,
        );

        ctx.clip();

        /*
         * Put the blurred image back into the original
         * canvas at its original position.
         */
        ctx.drawImage(
            blurredCanvas,
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
         * Release temporary canvas memory.
         */
        tempCanvas.width = 1;
        tempCanvas.height = 1;

        blurredCanvas.width = 1;
        blurredCanvas.height = 1;
    }

    return sourceCanvas;
}
