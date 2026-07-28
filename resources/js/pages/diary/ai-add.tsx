import { Head, router, useForm } from '@inertiajs/react';
import AddPhotoAlternateRounded from '@mui/icons-material/AddPhotoAlternateRounded';
import ArrowBackRounded from '@mui/icons-material/ArrowBackRounded';
import AutoAwesomeRounded from '@mui/icons-material/AutoAwesomeRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import PhotoCameraRounded from '@mui/icons-material/PhotoCameraRounded';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardHeader from '@mui/material/CardHeader';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type ChangeEvent,
    type ClipboardEvent,
    type FormEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/utils';

type MealKey = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type NumberValue = number | '';

type AiNutritionEstimate = {
    name: string;
    weight_grams: number;
    calories_per_100g: number;
    protein_per_100g: number;
    carbohydrates_per_100g: number;
    fat_per_100g: number;
    fibre_per_100g: number;
    confidence: 'low' | 'medium' | 'high';
    assumptions: string;
};

type AiEntryForm = {
    date: string;
    meal: MealKey;
    name: string;
    weight_grams: NumberValue;
    calories_per_100g: NumberValue;
    protein_per_100g: NumberValue;
    carbohydrates_per_100g: NumberValue;
    fat_per_100g: NumberValue;
    fibre_per_100g: NumberValue;
};

const mealLabelKeys: Record<MealKey, string> = {
    breakfast: 'diary.breakfast',
    lunch: 'diary.lunch',
    dinner: 'diary.dinner',
    snacks: 'diary.snacks',
};

const supportedImageTypes = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const targetImageBytes = Math.floor(1.8 * 1024 * 1024);
const maximumImageDimension = 2560;

const loadImage = (file: File): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();
        const url = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('The image could not be decoded.'));
        };
        image.src = url;
    });

const canvasBlob = (
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob> =>
    new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(new Error('The image could not be encoded.')),
            'image/jpeg',
            quality,
        );
    });

const optimizedImageName = (name: string): string => {
    const basename = name.replace(/\.[^.]+$/, '').trim();

    return `${basename || 'food-photo'}.jpg`;
};

const optimizeImageForUpload = async (file: File): Promise<File> => {
    if (!file.type.startsWith('image/')) {
        throw new Error('Unsupported image type.');
    }

    if (supportedImageTypes.has(file.type) && file.size <= targetImageBytes) {
        return file;
    }

    const image = await loadImage(file);
    const longestSide = Math.max(image.naturalWidth, image.naturalHeight);

    if (longestSide <= 0) {
        throw new Error('The image has invalid dimensions.');
    }

    let scale = Math.min(1, maximumImageDimension / longestSide);
    let quality = 0.88;

    for (let attempt = 0; attempt < 8; attempt += 1) {
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Image processing is unavailable.');
        }

        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const blob = await canvasBlob(canvas, quality);

        if (blob.size <= targetImageBytes) {
            return new File([blob], optimizedImageName(file.name), {
                type: 'image/jpeg',
                lastModified: file.lastModified,
            });
        }

        if (quality > 0.58) {
            quality -= 0.1;
        } else {
            scale *= 0.8;
            quality = 0.82;
        }
    }

    throw new Error('The optimized image is still too large.');
};

export default function AiAddDiaryEntry({
    date,
    meal,
}: {
    date: string;
    meal: MealKey;
}) {
    const { t } = useTranslation();
    const [description, setDescription] = useState('');
    const [images, setImages] = useState<File[]>([]);
    const [inputError, setInputError] = useState('');
    const [estimateError, setEstimateError] = useState('');
    const [estimating, setEstimating] = useState(false);
    const [processingImages, setProcessingImages] = useState(false);
    const [estimate, setEstimate] = useState<AiNutritionEstimate | null>(null);
    const nutritionDetailsRef = useRef<HTMLDivElement | null>(null);
    const form = useForm<AiEntryForm>({
        date,
        meal,
        name: '',
        weight_grams: '',
        calories_per_100g: '',
        protein_per_100g: '',
        carbohydrates_per_100g: '',
        fat_per_100g: '',
        fibre_per_100g: '',
    });
    const previews = useMemo(
        () =>
            images.map((file) => ({
                file,
                url: URL.createObjectURL(file),
            })),
        [images],
    );

    useEffect(
        () => () => {
            previews.forEach(({ url }) => URL.revokeObjectURL(url));
        },
        [previews],
    );

    useEffect(() => {
        if (!estimate) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            nutritionDetailsRef.current?.scrollIntoView({
                behavior: window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches
                    ? 'auto'
                    : 'smooth',
                block: 'start',
            });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [estimate]);

    const asNumber = (value: NumberValue): number =>
        value === '' ? 0 : Number(value);
    const numberValue = (value: string): NumberValue =>
        value === '' ? '' : Number(value);
    const factor = asNumber(form.data.weight_grams) / 100;
    const totals = {
        calories: asNumber(form.data.calories_per_100g) * factor,
        protein: asNumber(form.data.protein_per_100g) * factor,
        carbohydrates:
            asNumber(form.data.carbohydrates_per_100g) * factor,
        fat: asNumber(form.data.fat_per_100g) * factor,
        fibre: asNumber(form.data.fibre_per_100g) * factor,
    };
    const canEstimate = description.trim() !== '' || images.length > 0;

    const resetEstimate = () => {
        setEstimate(null);
        setEstimateError('');
        form.clearErrors();
        form.setData({
            date,
            meal,
            name: '',
            weight_grams: '',
            calories_per_100g: '',
            protein_per_100g: '',
            carbohydrates_per_100g: '',
            fat_per_100g: '',
            fibre_per_100g: '',
        });
    };

    const addImageFiles = async (selected: File[]) => {
        if (selected.length === 0 || processingImages) {
            return;
        }

        if (selected.some((file) => !file.type.startsWith('image/'))) {
            setInputError(t('diary.ai_unsupported_image'));
            return;
        }

        if (images.length + selected.length > 2) {
            setInputError(t('diary.ai_too_many_images'));
            return;
        }

        setProcessingImages(true);

        try {
            const prepared: File[] = [];

            for (const image of selected) {
                prepared.push(await optimizeImageForUpload(image));
            }

            setImages((current) => [...current, ...prepared]);
            setInputError('');
            resetEstimate();
        } catch {
            setInputError(t('diary.ai_image_processing_error'));
        } finally {
            setProcessingImages(false);
        }
    };

    const addImages = (event: ChangeEvent<HTMLInputElement>) => {
        const selected = Array.from(event.target.files ?? []);
        event.target.value = '';
        void addImageFiles(selected);
    };

    const pasteImages = (event: ClipboardEvent<HTMLDivElement>) => {
        const pastedImages = Array.from(event.clipboardData.items)
            .filter((item) => item.kind === 'file')
            .map((item) => item.getAsFile())
            .filter(
                (file): file is File =>
                    file !== null && file.type.startsWith('image/'),
            );

        if (pastedImages.length === 0) {
            return;
        }

        event.preventDefault();
        void addImageFiles(pastedImages);
    };

    const removeImage = (index: number) => {
        setImages((current) =>
            current.filter((_, imageIndex) => imageIndex !== index),
        );
        setInputError('');
        resetEstimate();
    };

    const estimateNutrition = async () => {
        if (!canEstimate) {
            setInputError(t('diary.ai_input_required'));
            return;
        }

        setInputError('');
        setEstimateError('');
        setEstimating(true);

        try {
            const csrfToken =
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content;
            const headers: Record<string, string> = {
                Accept: 'application/json',
            };

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const requestData = new FormData();
            requestData.append('description', description.trim());
            images.forEach((image) => requestData.append('images[]', image));

            const response = await fetch('/diary-entries/ai/estimate', {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: requestData,
            });
            const payload = (await response.json().catch(() => ({}))) as {
                estimate?: AiNutritionEstimate;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok || !payload.estimate) {
                const validationError = Object.values(
                    payload.errors ?? {},
                ).flat()[0];

                throw new Error(
                    validationError ??
                        payload.message ??
                        t('diary.ai_estimate_error'),
                );
            }

            setEstimate(payload.estimate);
            form.clearErrors();
            form.setData({
                date,
                meal,
                name: payload.estimate.name,
                weight_grams: payload.estimate.weight_grams,
                calories_per_100g: payload.estimate.calories_per_100g,
                protein_per_100g: payload.estimate.protein_per_100g,
                carbohydrates_per_100g:
                    payload.estimate.carbohydrates_per_100g,
                fat_per_100g: payload.estimate.fat_per_100g,
                fibre_per_100g: payload.estimate.fibre_per_100g,
            });
        } catch (error) {
            setEstimateError(
                error instanceof Error
                    ? error.message
                    : t('diary.ai_estimate_error'),
            );
        } finally {
            setEstimating(false);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/diary-entries/ai');
    };

    const mealLabel = t(mealLabelKeys[meal]);

    return (
        <AppLayout
            title={t('diary.ai_entry')}
            subtitle={`${mealLabel} · ${formatDate(date)}`}
            actions={
                <Button
                    size="small"
                    variant="outlined"
                    startIcon={<ArrowBackRounded />}
                    onClick={() => router.visit(`/diary/${date}`)}
                >
                    {t('diary.back_to_diary')}
                </Button>
            }
        >
            <Head title={t('diary.ai_entry')} />
            <Box
                component="form"
                onSubmit={submit}
                sx={{ maxWidth: 880, mx: 'auto' }}
            >
                <Stack spacing={2}>
                    <Card>
                        <CardContent>
                            <Stack spacing={2}>
                                <TextField
                                    multiline
                                    minRows={4}
                                    label={t('diary.ai_food_description')}
                                    placeholder={t(
                                        'diary.ai_food_placeholder',
                                    )}
                                    helperText={t(
                                        'diary.ai_paste_images',
                                    )}
                                    value={description}
                                    slotProps={{
                                        htmlInput: { maxLength: 1000 },
                                    }}
                                    onChange={(event) => {
                                        setDescription(event.target.value);
                                        setInputError('');
                                        resetEstimate();
                                    }}
                                    onPaste={pasteImages}
                                />
                                <Box>
                                    <Typography variant="subtitle2">
                                        {t('diary.ai_images')}
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        {t('diary.ai_images_help')}
                                    </Typography>
                                </Box>
                                {previews.length > 0 && (
                                    <Grid container spacing={1.5}>
                                        {previews.map(
                                            ({ file, url }, index) => (
                                                <Grid
                                                    key={`${file.name}-${file.lastModified}-${index}`}
                                                    size={{ xs: 6, sm: 4 }}
                                                >
                                                    <Box
                                                        sx={{
                                                            position:
                                                                'relative',
                                                            overflow:
                                                                'hidden',
                                                            borderRadius: 2,
                                                            border: 1,
                                                            borderColor:
                                                                'divider',
                                                            aspectRatio: '1',
                                                        }}
                                                    >
                                                        <Box
                                                            component="img"
                                                            src={url}
                                                            alt={file.name}
                                                            sx={{
                                                                width: 1,
                                                                height: 1,
                                                                display:
                                                                    'block',
                                                                objectFit:
                                                                    'cover',
                                                            }}
                                                        />
                                                        <IconButton
                                                            size="small"
                                                            color="error"
                                                            disabled={
                                                                processingImages
                                                            }
                                                            aria-label={t(
                                                                'diary.ai_remove_image',
                                                                {
                                                                    name: file.name,
                                                                },
                                                            )}
                                                            onClick={() =>
                                                                removeImage(
                                                                    index,
                                                                )
                                                            }
                                                            sx={{
                                                                position:
                                                                    'absolute',
                                                                top: 6,
                                                                right: 6,
                                                                bgcolor:
                                                                    'background.paper',
                                                                '&:hover': {
                                                                    bgcolor:
                                                                        'background.paper',
                                                                },
                                                            }}
                                                        >
                                                            <DeleteOutlineRounded fontSize="small" />
                                                        </IconButton>
                                                    </Box>
                                                </Grid>
                                            ),
                                        )}
                                    </Grid>
                                )}
                                {images.length < 2 && (
                                    <Stack
                                        direction={{
                                            xs: 'column',
                                            sm: 'row',
                                        }}
                                        spacing={1}
                                    >
                                        <Button
                                            fullWidth
                                            component="label"
                                            variant="outlined"
                                            disabled={processingImages}
                                            startIcon={
                                                <AddPhotoAlternateRounded />
                                            }
                                        >
                                            {t('diary.ai_add_images')}
                                            <input
                                                hidden
                                                multiple
                                                type="file"
                                                accept="image/*"
                                                onChange={addImages}
                                            />
                                        </Button>
                                        <Button
                                            fullWidth
                                            component="label"
                                            variant="outlined"
                                            disabled={processingImages}
                                            startIcon={
                                                <PhotoCameraRounded />
                                            }
                                        >
                                            {t('diary.ai_take_photo')}
                                            <input
                                                hidden
                                                type="file"
                                                accept="image/*"
                                                capture="environment"
                                                onChange={addImages}
                                            />
                                        </Button>
                                    </Stack>
                                )}
                                {processingImages && (
                                    <Alert
                                        severity="info"
                                        icon={
                                            <CircularProgress
                                                color="inherit"
                                                size={18}
                                            />
                                        }
                                    >
                                        {t('diary.ai_processing_images')}
                                    </Alert>
                                )}
                                {inputError && (
                                    <Alert severity="error">
                                        {inputError}
                                    </Alert>
                                )}
                                <Alert severity="info">
                                    {t('diary.ai_privacy')}
                                </Alert>
                                {estimateError && (
                                    <Alert severity="error">
                                        {estimateError}
                                    </Alert>
                                )}
                                {!estimate && (
                                    <Button
                                        type="button"
                                        variant="contained"
                                        startIcon={
                                            estimating ||
                                            processingImages ? (
                                                <CircularProgress
                                                    color="inherit"
                                                    size={18}
                                                />
                                            ) : (
                                                <AutoAwesomeRounded />
                                            )
                                        }
                                        disabled={
                                            estimating ||
                                            processingImages ||
                                            !canEstimate
                                        }
                                        onClick={() =>
                                            void estimateNutrition()
                                        }
                                    >
                                        {processingImages
                                            ? t(
                                                  'diary.ai_processing_images',
                                              )
                                            : estimating
                                            ? t(
                                                  'diary.estimating_nutrition',
                                              )
                                            : t(
                                                  'diary.estimate_nutrition',
                                              )}
                                    </Button>
                                )}
                            </Stack>
                        </CardContent>
                    </Card>

                    <Card
                        ref={nutritionDetailsRef}
                        sx={{ scrollMarginTop: 96 }}
                    >
                        <CardHeader
                            title={
                                estimate
                                    ? t('diary.ai_review_title')
                                    : t('diary.ai_food_details')
                            }
                            subheader={
                                estimate
                                    ? t('diary.ai_review_help')
                                    : t('diary.ai_food_details_waiting')
                            }
                        />
                        <CardContent>
                            <Stack spacing={2}>
                                {estimate && (
                                    <Alert severity="warning">
                                        <Typography variant="body2">
                                            {t('diary.ai_confidence', {
                                                confidence: t(
                                                    `diary.ai_confidence_${estimate.confidence}`,
                                                ),
                                            })}
                                        </Typography>
                                        {estimate.assumptions && (
                                            <Typography variant="body2">
                                                {t(
                                                    'diary.ai_assumptions',
                                                    {
                                                        assumptions:
                                                            estimate.assumptions,
                                                    },
                                                )}
                                            </Typography>
                                        )}
                                    </Alert>
                                )}
                                <Grid container spacing={2}>
                                    <Grid size={{ xs: 8, sm: 9 }}>
                                        <TextField
                                            required
                                            disabled={!estimate}
                                            fullWidth
                                            label={t('diary.ai_food_name')}
                                            value={form.data.name}
                                            error={Boolean(form.errors.name)}
                                            helperText={form.errors.name}
                                            slotProps={{
                                                htmlInput: {
                                                    maxLength: 255,
                                                },
                                            }}
                                            onChange={(event) =>
                                                form.setData(
                                                    'name',
                                                    event.target.value,
                                                )}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 4, sm: 3 }}>
                                        <TextField
                                            required
                                            disabled={!estimate}
                                            fullWidth
                                            type="number"
                                            label={t(
                                                'diary.ai_total_amount',
                                            )}
                                            value={form.data.weight_grams}
                                            error={Boolean(
                                                form.errors.weight_grams,
                                            )}
                                            helperText={
                                                form.errors.weight_grams
                                            }
                                            slotProps={{
                                                input: {
                                                    endAdornment: (
                                                        <InputAdornment position="end">
                                                            g
                                                        </InputAdornment>
                                                    ),
                                                },
                                                htmlInput: {
                                                    min: 0.01,
                                                    max: 1000000,
                                                    step: 0.01,
                                                },
                                            }}
                                            onChange={(event) =>
                                                form.setData(
                                                    'weight_grams',
                                                    numberValue(
                                                        event.target.value,
                                                    ),
                                                )}
                                        />
                                    </Grid>
                                </Grid>
                                <Divider>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                    >
                                        {t(
                                            'diary.ai_nutrition_per_100g',
                                        )}
                                    </Typography>
                                </Divider>
                                <Grid container spacing={2}>
                                    {(
                                        [
                                            [
                                                'calories_per_100g',
                                                t('common.calories'),
                                                'kcal',
                                                0.01,
                                                100000,
                                            ],
                                            [
                                                'protein_per_100g',
                                                t('common.protein'),
                                                'g',
                                                0,
                                                10000,
                                            ],
                                            [
                                                'carbohydrates_per_100g',
                                                t('common.carbohydrates'),
                                                'g',
                                                0,
                                                10000,
                                            ],
                                            [
                                                'fat_per_100g',
                                                t('common.fat'),
                                                'g',
                                                0,
                                                10000,
                                            ],
                                            [
                                                'fibre_per_100g',
                                                t('common.fibre'),
                                                'g',
                                                0,
                                                10000,
                                            ],
                                        ] as const
                                    ).map(
                                        ([
                                            key,
                                            label,
                                            unit,
                                            minimum,
                                            maximum,
                                        ]) => (
                                            <Grid
                                                key={key}
                                                size={{
                                                    xs: 6,
                                                    sm: 4,
                                                }}
                                            >
                                                <TextField
                                                    required
                                                    disabled={!estimate}
                                                    fullWidth
                                                    type="number"
                                                    label={label}
                                                    value={form.data[key]}
                                                    error={Boolean(
                                                        form.errors[key],
                                                    )}
                                                    helperText={
                                                        form.errors[key]
                                                    }
                                                    slotProps={{
                                                        input: {
                                                            endAdornment: (
                                                                <InputAdornment position="end">
                                                                    {unit}
                                                                </InputAdornment>
                                                            ),
                                                        },
                                                        htmlInput: {
                                                            min: minimum,
                                                            max: maximum,
                                                            step: 0.01,
                                                        },
                                                    }}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            key,
                                                            numberValue(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </Grid>
                                        ),
                                    )}
                                </Grid>
                                {estimate && (
                                    <Alert severity="success">
                                        <Typography variant="subtitle2">
                                            {t(
                                                'diary.ai_estimated_totals',
                                                {
                                                    weight: formatNumber(
                                                        asNumber(
                                                            form.data
                                                                .weight_grams,
                                                        ),
                                                        1,
                                                    ),
                                                },
                                            )}
                                        </Typography>
                                        <Typography variant="body2">
                                            {t(
                                                'diary.ai_total_summary',
                                                {
                                                    calories: formatNumber(
                                                        totals.calories,
                                                        1,
                                                    ),
                                                    protein: formatNumber(
                                                        totals.protein,
                                                        1,
                                                    ),
                                                    carbs: formatNumber(
                                                        totals.carbohydrates,
                                                        1,
                                                    ),
                                                    fat: formatNumber(
                                                        totals.fat,
                                                        1,
                                                    ),
                                                    fibre: formatNumber(
                                                        totals.fibre,
                                                        1,
                                                    ),
                                                },
                                            )}
                                        </Typography>
                                    </Alert>
                                )}
                                {estimate && (
                                    <Stack
                                        direction={{
                                            xs: 'column-reverse',
                                            sm: 'row',
                                        }}
                                        spacing={1}
                                        justifyContent="flex-end"
                                    >
                                        <Button
                                            type="button"
                                            variant="outlined"
                                            startIcon={
                                                estimating ? (
                                                    <CircularProgress
                                                        size={18}
                                                    />
                                                ) : (
                                                    <AutoAwesomeRounded />
                                                )
                                            }
                                            disabled={estimating}
                                            onClick={() =>
                                                void estimateNutrition()
                                            }
                                        >
                                            {estimating
                                                ? t(
                                                      'diary.estimating_nutrition',
                                                  )
                                                : t(
                                                      'diary.retry_estimate',
                                                  )}
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="contained"
                                            disabled={
                                                form.processing || estimating
                                            }
                                        >
                                            {t('diary.add_ai_entry')}
                                        </Button>
                                    </Stack>
                                )}
                            </Stack>
                        </CardContent>
                    </Card>
                </Stack>
            </Box>
        </AppLayout>
    );
}
