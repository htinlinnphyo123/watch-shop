// Own each camera session so closing during a permission prompt cannot leak a stream.
export function createCameraScanner({ getUserMedia, createReader, onDetected, onError, onStarted }) {
    let generation = 0;
    let stream = null;
    let controls = null;

    const stop = () => {
        generation++;
        controls?.stop();
        controls = null;
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
    };

    const start = async (video, deviceId = '') => {
        stop();
        const session = generation;
        try {
            const reader = await createReader();
            if (session !== generation) return;
            const camera = await getUserMedia({
                audio: false,
                video: {
                    ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } }),
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
            });
            if (session !== generation) {
                camera.getTracks().forEach(track => track.stop());
                return;
            }
            stream = camera;
            const scanner = await reader.decodeFromStream(camera, video, (result, error, activeControls) => {
                if (session !== generation) return;
                if (result) {
                    const code = result.getText().trim();
                    if (!code) return;
                    activeControls?.stop();
                    stop();
                    onDetected(code);
                } else if (error && !['NotFoundException', 'ChecksumException', 'FormatException'].includes(error.getKind?.() || error.name)) {
                    activeControls?.stop();
                    stop();
                    onError(error);
                }
            });
            if (session !== generation) scanner.stop();
            else {
                controls = scanner;
                onStarted?.(camera);
            }
        } catch (error) {
            if (session !== generation) return;
            stop();
            onError(error);
        }
    };

    return { start, stop };
}
