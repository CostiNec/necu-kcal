import CloseRounded from '@mui/icons-material/CloseRounded';
import CameraswitchRounded from '@mui/icons-material/CameraswitchRounded';
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
    const handledRef = useRef(false);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<ScannerError | null>(null);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (!open) return;

        let cancelled = false;
        let scannerControls: IScannerControls | null = null;
        handledRef.current = false;
        setError(null);
        setStarting(true);

        const startScanner = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setError('unsupported');
                setStarting(false);
                return;
            }

            try {
                const { BrowserMultiFormatOneDReader } = await import(
                    '@zxing/browser'
                );
                const reader = new BrowserMultiFormatOneDReader(undefined, {
                    delayBetweenScanAttempts: 120,
                    delayBetweenScanSuccess: 500,
                });

                if (!videoRef.current || cancelled) return;

                const controls = await reader.decodeFromConstraints(
                    {
                        audio: false,
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                    },
                    videoRef.current,
                    (result, _scanError, activeControls) => {
                        if (!result || handledRef.current || cancelled) return;

                        handledRef.current = true;
                        activeControls.stop();
                        navigator.vibrate?.(80);
                        onDetected(result.getText());
                    },
                );

                if (cancelled) {
                    controls.stop();
                    return;
                }

                scannerControls = controls;
                setStarting(false);
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
            scannerControls?.stop();

            const stream = videoRef.current?.srcObject;
            if (stream instanceof MediaStream) {
                stream.getTracks().forEach((track) => track.stop());
            }
        };
    }, [attempt, onDetected, open]);

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
