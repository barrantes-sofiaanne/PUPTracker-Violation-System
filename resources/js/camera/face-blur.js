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

    // Privacy settings.
    // 30% expands the detected face area on all sides.
    const BLUR_MARGIN_RATIO = 0.3;

    // Stronger blur than the previous 18px.
    const BLUR_STRENGTH = 24;

    for (const detection of detections.detections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        // Add extra coverage around the detected face.
        const marginX = Math.max(15, Math.round(box.width * BLUR_MARGIN_RATIO));

        const marginY = Math.max(
            15,
            Math.round(box.height * BLUR_MARGIN_RATIO),
        );

        const x = Math.max(0, Math.round(box.originX - marginX));

        const y = Math.max(0, Math.round(box.originY - marginY));

        const right = Math.min(
            sourceCanvas.width,
            Math.round(box.originX + box.width + marginX),
        );

        const bottom = Math.min(
            sourceCanvas.height,
            Math.round(box.originY + box.height + marginY),
        );

        const width = right - x;
        const height = bottom - y;

        if (width <= 0 || height <= 0) {
            continue;
        }

        // Copy the expanded face area.
        const faceImage = ctx.getImageData(x, y, width, height);

        // Temporary canvas for applying the blur.
        const tempCanvas = document.createElement("canvas");

        tempCanvas.width = width;
        tempCanvas.height = height;

        const tempCtx = tempCanvas.getContext("2d");

        tempCtx.putImageData(faceImage, 0, 0);

        // Apply stronger privacy blur.
        ctx.save();

        ctx.filter = `blur(${BLUR_STRENGTH}px)`;

        ctx.drawImage(tempCanvas, x, y, width, height);

        ctx.restore();
    }

    return sourceCanvas;
}
