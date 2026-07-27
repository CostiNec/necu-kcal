import CloseRounded from '@mui/icons-material/CloseRounded';
import CameraswitchRounded from '@mui/icons-material/CameraswitchRounded';
import FlashlightOffRounded from '@mui/icons-material/FlashlightOffRounded';
import FlashlightOnRounded from '@mui/icons-material/FlashlightOnRounded';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
    Stack,
    Typography,
    useMediaQuery,
    useTheme,
} from '@mui/material';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { IScannerControls } from '@zxing/browser';

type ScannerError =
    | 'permission'
    | 'unavailable'
    | 'unsupported'
    | 'unknown';

type ExtendedTrackCapabilities = MediaTrackCapabilities & {
    focusMode?: string[];
    torch?: boolean;
};

type ExtendedTrackConstraintSet = MediaTrackConstraintSet & {
    focusMode?: string;
    torch?: boolean;
};

type NativeBarcodeDetector = {
    detect: (
        source: HTMLVideoElement,
    ) => Promise<Array<{ rawValue: string }>>;
};

type NativeBarcodeDetectorConstructor = {
    new (options?: { formats: string[] }): NativeBarcodeDetector;
    getSupportedFormats?: () => Promise<string[]>;
};

const foodBarcodePattern = /^\d{6,18}$/;
const nativeBarcodeFormats = [
    'ean_13',
    'ean_8',
    'upc_a',
    'upc_e',
    'itf',
    'code_128',
];

export function BarcodeScannerDialog({
    open,
    onClose,
    onDetected,
}: {
    open: boolean;
    onClose: () => void;
    onDetected: (barcode: string) => void;
}) {
    const { t } = useTranslation();
    const theme = useTheme();
    const fullScreen = useMediaQuery(theme.breakpoints.down('sm'));
    const videoRef = useRef<HTMLVideoElement>(null);
    const videoTrackRef = useRef<MediaStreamTrack | null>(null);
    const handledRef = useRef(false);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<ScannerError | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [torchSupported, setTorchSupported] = useState(false);
    const [torchOn, setTorchOn] = useState(false);

    useEffect(() => {
        if (!open) return;

        let cancelled = false;
        let scannerControls: IScannerControls | null = null;
        let nativeScanTimer: number | null = null;
        let tryHarderTimer: number | null = null;
        handledRef.current = false;
        setError(null);
        setStarting(true);
        setTorchSupported(false);
        setTorchOn(false);

        const finishDetection = (
            value: string,
            stopScanner: () => void,
        ) => {
            const barcode = value.trim();

            if (
                !foodBarcodePattern.test(barcode) ||
                handledRef.current ||
                cancelled
            ) {
                return false;
            }

            handledRef.current = true;
            stopScanner();
            navigator.vibrate?.(80);
            onDetected(barcode);

            return true;
        };

        const configureCamera = async (stream: MediaStream) => {
            const track = stream.getVideoTracks()[0];
            if (!track) return;

            videoTrackRef.current = track;
            const capabilities =
                track.getCapabilities?.() as ExtendedTrackCapabilities;

            setTorchSupported(Boolean(capabilities?.torch));

            if (capabilities?.focusMode?.includes('continuous')) {
                try {
                    await track.applyConstraints({
                        advanced: [
                            {
                                focusMode: 'continuous',
                            } as ExtendedTrackConstraintSet,
                        ],
                    });
                } catch {
                    // Some browsers advertise focus controls but reject them.
                }
            }
        };

        const videoConstraints: MediaTrackConstraints = {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
            aspectRatio: { ideal: 16 / 9 },
        };

        const startNativeScanner = async (
            Detector: NativeBarcodeDetectorConstructor,
        ) => {
            const supportedFormats = Detector.getSupportedFormats
                ? await Detector.getSupportedFormats()
                : nativeBarcodeFormats;
            const formats = nativeBarcodeFormats.filter((format) =>
                supportedFormats.includes(format),
            );

            if (formats.length === 0) {
                throw new Error('No supported retail barcode formats.');
            }

            const detector = new Detector({ formats });
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: videoConstraints,
            });

            try {
                const video = videoRef.current;

                if (!video || cancelled) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                video.srcObject = stream;
                await video.play();
                await configureCamera(stream);

                const stop = () => {
                    if (nativeScanTimer !== null) {
                        window.clearTimeout(nativeScanTimer);
                        nativeScanTimer = null;
                    }
                    stream.getTracks().forEach((track) => track.stop());
                };
                scannerControls = { stop };
                setStarting(false);

                const scanFrame = async () => {
                    if (cancelled || handledRef.current) return;

                    try {
                        const barcodes = await detector.detect(video);
                        const detected = barcodes.find((barcode) =>
                            foodBarcodePattern.test(
                                barcode.rawValue.trim(),
                            ),
                        );

                        if (
                            detected &&
                            finishDetection(detected.rawValue, stop)
                        ) {
                            return;
                        }
                    } catch {
                        // A transient frame error should not stop the camera.
                    }

                    if (!cancelled && !handledRef.current) {
                        nativeScanTimer = window.setTimeout(
                            scanFrame,
                            40,
                        );
                    }
                };

                void scanFrame();
            } catch (nativeError) {
                stream.getTracks().forEach((track) => track.stop());
                throw nativeError;
            }
        };

        const startZxingScanner = async () => {
            const [{ BrowserMultiFormatOneDReader }, zxing] =
                await Promise.all([
                    import('@zxing/browser'),
                    import('@zxing/library'),
                ]);
            const hints = new Map();
            hints.set(zxing.DecodeHintType.POSSIBLE_FORMATS, [
                zxing.BarcodeFormat.EAN_13,
                zxing.BarcodeFormat.EAN_8,
                zxing.BarcodeFormat.UPC_A,
                zxing.BarcodeFormat.UPC_E,
                zxing.BarcodeFormat.ITF,
                zxing.BarcodeFormat.CODE_128,
            ]);
            const reader = new BrowserMultiFormatOneDReader(hints, {
                delayBetweenScanAttempts: 40,
                delayBetweenScanSuccess: 300,
            });
            tryHarderTimer = window.setTimeout(() => {
                hints.set(zxing.DecodeHintType.TRY_HARDER, true);
            }, 1500);

            if (!videoRef.current || cancelled) return;

            const controls = await reader.decodeFromConstraints(
                {
                    audio: false,
                    video: videoConstraints,
                },
                videoRef.current,
                (result, _scanError, activeControls) => {
                    if (!result) return;

                    finishDetection(result.getText(), () =>
                        activeControls.stop(),
                    );
                },
            );

            if (cancelled) {
                controls.stop();
                return;
            }

            scannerControls = controls;
            const stream = videoRef.current.srcObject;
            if (stream instanceof MediaStream) {
                await configureCamera(stream);
            }
            setStarting(false);
        };

        const startScanner = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setError('unsupported');
                setStarting(false);
                return;
            }

            try {
                const Detector = (
                    window as typeof window & {
                        BarcodeDetector?: NativeBarcodeDetectorConstructor;
                    }
                ).BarcodeDetector;

                if (Detector) {
                    try {
                        await startNativeScanner(Detector);
                        return;
                    } catch (nativeError) {
                        if (
                            nativeError instanceof DOMException &&
                            [
                                'NotAllowedError',
                                'NotFoundError',
                                'OverconstrainedError',
                            ].includes(nativeError.name)
                        ) {
                            throw nativeError;
                        }
                    }
                }

                await startZxingScanner();
            } catch (scannerError) {
                if (cancelled) return;

                const name =
                    scannerError instanceof DOMException
                        ? scannerError.name
                        : '';

                setError(
                    name === 'NotAllowedError'
                        ? 'permission'
                        : name === 'NotFoundError' ||
                            name === 'OverconstrainedError'
                          ? 'unavailable'
                          : 'unknown',
                );
                setStarting(false);
            }
        };

        void startScanner();

        return () => {
            cancelled = true;
            if (nativeScanTimer !== null) {
                window.clearTimeout(nativeScanTimer);
            }
            if (tryHarderTimer !== null) {
                window.clearTimeout(tryHarderTimer);
            }
            scannerControls?.stop();
            videoTrackRef.current = null;

            const stream = videoRef.current?.srcObject;
            if (stream instanceof MediaStream) {
                stream.getTracks().forEach((track) => track.stop());
            }
        };
    }, [attempt, onDetected, open]);

    const toggleTorch = async () => {
        const track = videoTrackRef.current;
        if (!track) return;

        const nextValue = !torchOn;

        try {
            await track.applyConstraints({
                advanced: [
                    {
                        torch: nextValue,
                    } as ExtendedTrackConstraintSet,
                ],
            });
            setTorchOn(nextValue);
        } catch {
            setTorchSupported(false);
        }
    };

    const errorMessage =
        error === 'permission'
            ? t('food.camera_permission_error')
            : error === 'unavailable'
              ? t('food.camera_unavailable')
              : error === 'unsupported'
                ? t('food.camera_unsupported')
                : t('food.camera_error');

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullScreen={fullScreen}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle
                component="div"
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <Typography variant="h6">{t('food.scan_barcode')}</Typography>
                <IconButton aria-label={t('common.close')} onClick={onClose}>
                    <CloseRounded />
                </IconButton>
            </DialogTitle>
            <DialogContent>
                <Stack spacing={2}>
                    <Box
                        sx={{
                            position: 'relative',
                            overflow: 'hidden',
                            aspectRatio: '4 / 3',
                            borderRadius: 2,
                            bgcolor: 'grey.900',
                        }}
                    >
                        <Box
                            component="video"
                            ref={videoRef}
                            muted
                            playsInline
                            aria-label={t('food.camera_preview')}
                            sx={{
                                width: '100%',
                                height: '100%',
                                display: 'block',
                                objectFit: 'cover',
                            }}
                        />

                        {!error && (
                            <Box
                                aria-hidden
                                sx={{
                                    position: 'absolute',
                                    inset: '30% 10%',
                                    border: '2px solid',
                                    borderColor: 'common.white',
                                    borderRadius: 1.5,
                                    boxShadow:
                                        '0 0 0 999px rgba(0, 0, 0, 0.32)',
                                }}
                            />
                        )}

                        {torchSupported && !starting && !error && (
                            <IconButton
                                aria-label={t(
                                    torchOn
                                        ? 'food.turn_off_flashlight'
                                        : 'food.turn_on_flashlight',
                                )}
                                onClick={() => void toggleTorch()}
                                sx={{
                                    position: 'absolute',
                                    right: 12,
                                    bottom: 12,
                                    color: 'common.white',
                                    bgcolor: 'rgba(0, 0, 0, 0.55)',
                                    '&:hover': {
                                        bgcolor: 'rgba(0, 0, 0, 0.72)',
                                    },
                                }}
                            >
                                {torchOn ? (
                                    <FlashlightOffRounded />
                                ) : (
                                    <FlashlightOnRounded />
                                )}
                            </IconButton>
                        )}

                        {starting && (
                            <Stack
                                alignItems="center"
                                justifyContent="center"
                                spacing={1.5}
                                sx={{
                                    position: 'absolute',
                                    inset: 0,
                                    color: 'common.white',
                                    bgcolor: 'rgba(0, 0, 0, 0.48)',
                                }}
                            >
                                <CircularProgress color="inherit" size={32} />
                                <Typography variant="body2">
                                    {t('food.starting_camera')}
                                </Typography>
                            </Stack>
                        )}
                    </Box>

                    {error ? (
                        <Alert
                            severity="error"
                            action={
                                <Button
                                    color="inherit"
                                    size="small"
                                    startIcon={<CameraswitchRounded />}
                                    onClick={() =>
                                        setAttempt((current) => current + 1)
                                    }
                                >
                                    {t('food.retry_camera')}
                                </Button>
                            }
                        >
                            {errorMessage}
                        </Alert>
                    ) : (
                        <Typography
                            variant="body2"
                            color="text.secondary"
                            textAlign="center"
                        >
                            {t('food.scan_barcode_help')}
                        </Typography>
                    )}
                </Stack>
            </DialogContent>
        </Dialog>
    );
}
