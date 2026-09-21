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

    for (const detection of detections.detections) {
        const box = detection.boundingBox;

        if (!box) {
            continue;
        }

        const x = Math.max(0, box.originX);
        const y = Math.max(0, box.originY);

        const width = Math.min(box.width, sourceCanvas.width - x);

        const height = Math.min(box.height, sourceCanvas.height - y);

        if (width <= 0 || height <= 0) {
            continue;
        }

        // Save the original face area.
        const faceImage = ctx.getImageData(x, y, width, height);

        // Create a temporary canvas.
        const tempCanvas = document.createElement("canvas");

        tempCanvas.width = width;
        tempCanvas.height = height;

        const tempCtx = tempCanvas.getContext("2d");

        tempCtx.putImageData(faceImage, 0, 0);

        // Apply blur.
        ctx.save();

        ctx.filter = "blur(18px)";

        ctx.drawImage(tempCanvas, x, y, width, height);

        ctx.restore();
    }

    return sourceCanvas;
}
