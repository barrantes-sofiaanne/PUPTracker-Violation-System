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
        minDetectionConfidence: 0.35,
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

    const ctx = sourceCanvas.getContext("2d");

    if (!ctx) {
        return sourceCanvas;
    }

    /*
     * ---------------------------------------------------------
     * FACE DETECTION
     * ---------------------------------------------------------
     *
     * We perform:
     *
     * 1. A 2x full-image detection.
     * 2. Multiple overlapping 2x regional detections.
     *
     * The regional passes help detect:
     * - small/far-away faces
     * - faces near the left/right edge
     * - partially visible faces
     */

    const DETECTION_SCALE = 2;

    const detections = [];

    /**
     * Run MediaPipe detection on a region of the original image.
     *
     * @param {number} regionX
     * @param {number} regionY
     * @param {number} regionWidth
     * @param {number} regionHeight
     */
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

        /*
         * Copy the selected region into the enlarged
         * detection canvas.
         */
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

            /*
             * Convert detection coordinates:
             *
             * enlarged region
             *        ↓
             * original image coordinates
             */
            const originalX = regionX + box.originX / DETECTION_SCALE;

            const originalY = regionY + box.originY / DETECTION_SCALE;

            const originalWidth = box.width / DETECTION_SCALE;

            const originalHeight = box.height / DETECTION_SCALE;

            /*
             * Ignore invalid detections.
             */
            if (originalWidth <= 0 || originalHeight <= 0) {
                continue;
            }

            /*
             * Keep detections that overlap the actual
             * image boundaries.
             */
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

        /*
         * Release detection canvas.
         */
        detectionCanvas.width = 1;
        detectionCanvas.height = 1;
    };

    /*
     * ---------------------------------------------------------
     * PASS 1 — FULL IMAGE
     * ---------------------------------------------------------
     */
    detectRegion(0, 0, sourceCanvas.width, sourceCanvas.height);

    /*
     * ---------------------------------------------------------
     * PASS 2 — OVERLAPPING REGIONS
     * ---------------------------------------------------------
     *
     * We divide the image into overlapping regions.
     *
     * The overlap is intentional. A face near a boundary
     * will still appear substantially inside another region.
     */

    const horizontalTiles = 3;
    const verticalTiles = 2;

    const tileWidth = sourceCanvas.width / 2;

    const tileHeight = sourceCanvas.height / 1.5;

    const horizontalStep =
        (sourceCanvas.width - tileWidth) / (horizontalTiles - 1);

    const verticalStep =
        (sourceCanvas.height - tileHeight) / (verticalTiles - 1);

    for (let row = 0; row < verticalTiles; row++) {
        for (let column = 0; column < horizontalTiles; column++) {
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

    /*
     * ---------------------------------------------------------
     * REMOVE DUPLICATE DETECTIONS
     * ---------------------------------------------------------
     *
     * Because the regions overlap, the same face may have
     * been detected several times.
     */

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

    /*
     * No detected faces.
     */
    if (uniqueDetections.length === 0) {
        return sourceCanvas;
    }

    /*
     * ---------------------------------------------------------
     * FACE PRIVACY CONFIGURATION
     * ---------------------------------------------------------
     */

    const FACE_HORIZONTAL_PADDING = 0.08;

    const FACE_VERTICAL_PADDING = 0.2;

    const BLUR_STRENGTH = 32;

    const BLUR_PADDING = 45;

    const FEATHER_START = 0.68;

    const FEATHER_END = 1.0;

    /*
     * Face privacy configuration.
     *
     * The horizontal padding stays relatively small so that
     * important hair details remain visible.
     *
     * The vertical padding is larger so the forehead and chin
     * remain protected.
     */

    for (const detection of uniqueDetections) {
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
