import {
    initializeFaceDetector,
    resetFaceDetector,
    blurFacesOnCanvas,
    logCameraDebug,
    logCameraEnvironment,
} from "./face-blur.js";

window.PUPTrackerFaceBlur = {
    initializeFaceDetector,
    resetFaceDetector,
    blurFacesOnCanvas,
    logCameraDebug,
    logCameraEnvironment,
};

// Emit browser/OS capability diagnostics once per page load.
logCameraEnvironment();
