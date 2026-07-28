import { Head, router } from '@inertiajs/react';
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
import MenuItem from '@mui/material/MenuItem';
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
} from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { optimizeImageForUpload } from '@/lib/ai-image-upload';
import { formatDate, formatNumber } from '@/lib/utils';

type MealKey = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type NumberValue = number | '';
type NutritionKey =
    | 'weight_grams'
    | 'calories_per_100g'
    | 'protein_per_100g'
    | 'carbohydrates_per_100g'
    | 'fat_per_100g'
    | 'fibre_per_100g';

type DayEntry = {
    meal: MealKey;
    name: string;
    weight_grams: NumberValue;
    calories_per_100g: NumberValue;
    protein_per_100g: NumberValue;
    carbohydrates_per_100g: NumberValue;
    fat_per_100g: NumberValue;
    fibre_per_100g: NumberValue;
    confidence: 'low' | 'medium' | 'high';
    assumptions: string;
};

type DayEstimate = {
    entries: DayEntry[];
};

const meals: MealKey[] = ['breakfast', 'lunch', 'dinner', 'snacks'];
const mealLabelKeys: Record<MealKey, string> = {
    breakfast: 'diary.breakfast',
    lunch: 'diary.lunch',
    dinner: 'diary.dinner',
    snacks: 'diary.snacks',
};
const maximumImages = 10;
const targetImageBytes = 600 * 1024;
const maximumImageDimension = 1600;

const asNumber = (value: NumberValue): number =>
    value === '' ? 0 : Number(value);

const numberValue = (value: string): NumberValue =>
    value === '' ? '' : Number(value);

export default function AiDayDiary({ date }: { date: string }) {
    const { t } = useTranslation();
    const [description, setDescription] = useState('');
    const [images, setImages] = useState<File[]>([]);
    const [entries, setEntries] = useState<DayEntry[] | null>(null);
    const [inputError, setInputError] = useState('');
    const [estimateError, setEstimateError] = useState('');
    const [saveError, setSaveError] = useState('');
    const [estimating, setEstimating] = useState(false);
    const [processingImages, setProcessingImages] = useState(false);
    const [saving, setSaving] = useState(false);
    const reviewRef = useRef<HTMLDivElement | null>(null);
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
        if (!entries) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            reviewRef.current?.scrollIntoView({
                behavior: window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches
                    ? 'auto'
                    : 'smooth',
                block: 'start',
            });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [entries]);

    const canEstimate = description.trim() !== '' || images.length > 0;
    const resetEstimate = () => {
        setEntries(null);
        setEstimateError('');
        setSaveError('');
    };

    const addImageFiles = async (selected: File[]) => {
        if (selected.length === 0 || processingImages) {
            return;
        }

        if (selected.some((file) => !file.type.startsWith('image/'))) {
            setInputError(t('diary.ai_unsupported_image'));
            return;
        }

        if (images.length + selected.length > maximumImages) {
            setInputError(t('diary.ai_day_too_many_images'));
            return;
        }

        setProcessingImages(true);

        try {
            const prepared: File[] = [];

            for (const image of selected) {
                prepared.push(
                    await optimizeImageForUpload(image, {
                        targetBytes: targetImageBytes,
                        maximumDimension: maximumImageDimension,
                    }),
                );
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

    const estimateDay = async () => {
        if (!canEstimate) {
            setInputError(t('diary.ai_input_required'));
            return;
        }

        setInputError('');
        setEstimateError('');
        setSaveError('');
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

            const response = await fetch(
                '/diary-entries/ai/day/estimate',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: requestData,
                },
            );
            const payload = (await response.json().catch(() => ({}))) as {
                estimate?: DayEstimate;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (
                !response.ok ||
                !payload.estimate ||
                payload.estimate.entries.length === 0
            ) {
                const validationError = Object.values(
                    payload.errors ?? {},
                ).flat()[0];

                throw new Error(
                    validationError ??
                        payload.message ??
                        t('diary.ai_day_estimate_error'),
                );
            }

            setEntries(payload.estimate.entries);
        } catch (error) {
            setEstimateError(
                error instanceof Error
                    ? error.message
                    : t('diary.ai_day_estimate_error'),
            );
        } finally {
            setEstimating(false);
        }
    };

    const updateEntry = <Key extends keyof DayEntry>(
        index: number,
        key: Key,
        value: DayEntry[Key],
    ) => {
        setEntries((current) =>
            current
                ? current.map((entry, entryIndex) =>
                      entryIndex === index
                          ? { ...entry, [key]: value }
                          : entry,
                  )
                : current,
        );
        setSaveError('');
    };

    const removeEntry = (index: number) => {
        setEntries((current) =>
            current
                ? current.filter(
                      (_, entryIndex) => entryIndex !== index,
                  )
                : current,
        );
        setSaveError('');
    };

    const saveEntries = () => {
        if (!entries || entries.length === 0) {
            setSaveError(t('diary.ai_day_no_entries'));
            return;
        }

        setSaveError('');
        router.post(
            '/diary-entries/ai/day',
            { date, entries },
            {
                onStart: () => setSaving(true),
                onError: (errors) => {
                    setSaveError(
                        String(
                            Object.values(errors)[0] ??
                                t('diary.ai_day_save_error'),
                        ),
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const totals = (entries ?? []).reduce(
        (sum, entry) => {
            const factor = asNumber(entry.weight_grams) / 100;

            return {
                calories:
                    sum.calories +
                    asNumber(entry.calories_per_100g) * factor,
                protein:
                    sum.protein +
                    asNumber(entry.protein_per_100g) * factor,
                carbohydrates:
                    sum.carbohydrates +
                    asNumber(entry.carbohydrates_per_100g) * factor,
                fat:
                    sum.fat + asNumber(entry.fat_per_100g) * factor,
                fibre:
                    sum.fibre +
                    asNumber(entry.fibre_per_100g) * factor,
            };
        },
        {
            calories: 0,
            protein: 0,
            carbohydrates: 0,
            fat: 0,
            fibre: 0,
        },
    );

    return (
        <AppLayout
            title={t('diary.ai_day_title')}
            subtitle={formatDate(date, { weekday: 'long' })}
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
            <Head title={t('diary.ai_day_title')} />
            <Box sx={{ maxWidth: 980, mx: 'auto' }}>
                <Stack spacing={2}>
                    <Card>
                        <CardHeader
                            title={t('diary.ai_day_input_title')}
                            subheader={t('diary.ai_day_input_help')}
                        />
                        <CardContent>
                            <Stack spacing={2}>
                                <TextField
                                    multiline
                                    minRows={4}
                                    label={t(
                                        'diary.ai_day_description',
                                    )}
                                    placeholder={t(
                                        'diary.ai_day_description_placeholder',
                                    )}
                                    helperText={t(
                                        'diary.ai_day_paste_images',
                                    )}
                                    value={description}
                                    slotProps={{
                                        htmlInput: { maxLength: 2000 },
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
                                        {t('diary.ai_day_images', {
                                            count: images.length,
                                        })}
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        {t(
                                            'diary.ai_day_images_help',
                                        )}
                                    </Typography>
                                </Box>

                                {previews.length > 0 && (
                                    <Grid container spacing={1.5}>
                                        {previews.map(
                                            ({ file, url }, index) => (
                                                <Grid
                                                    key={`${file.name}-${file.lastModified}-${index}`}
                                                    size={{
                                                        xs: 4,
                                                        sm: 3,
                                                        md: 2,
                                                    }}
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
                                                                top: 4,
                                                                right: 4,
                                                                bgcolor:
                                                                    'background.paper',
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

                                {images.length < maximumImages && (
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
                                        {t(
                                            'diary.ai_processing_images',
                                        )}
                                    </Alert>
                                )}
                                {inputError && (
                                    <Alert severity="error">
                                        {inputError}
                                    </Alert>
                                )}
                                <Alert severity="info">
                                    {t('diary.ai_day_privacy')}
                                </Alert>
                                {estimateError && (
                                    <Alert severity="error">
                                        {estimateError}
                                    </Alert>
                                )}
                                {!entries && (
                                    <Button
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
                                            void estimateDay()
                                        }
                                    >
                                        {estimating
                                            ? t(
                                                  'diary.ai_day_estimating',
                                              )
                                            : t(
                                                  'diary.ai_day_estimate',
                                              )}
                                    </Button>
                                )}
                            </Stack>
                        </CardContent>
                    </Card>

                    <Card ref={reviewRef} sx={{ scrollMarginTop: 96 }}>
                        <CardHeader
                            title={t('diary.ai_day_review_title')}
                            subheader={
                                entries
                                    ? t('diary.ai_day_review_help')
                                    : t(
                                          'diary.ai_day_review_waiting',
                                      )
                            }
                        />
                        <CardContent>
                            {!entries ? (
                                <Alert severity="info">
                                    {t('diary.ai_day_review_waiting')}
                                </Alert>
                            ) : (
                                <Stack spacing={2}>
                                    {entries.length === 0 && (
                                        <Alert severity="warning">
                                            {t(
                                                'diary.ai_day_no_entries',
                                            )}
                                        </Alert>
                                    )}

                                    {entries.map((entry, index) => (
                                        <DayEntryEditor
                                            key={index}
                                            index={index}
                                            entry={entry}
                                            onChange={updateEntry}
                                            onRemove={removeEntry}
                                        />
                                    ))}

                                    {entries.length > 0 && (
                                        <Alert severity="success">
                                            <Typography variant="subtitle2">
                                                {t(
                                                    'diary.ai_day_total_title',
                                                    {
                                                        count: entries.length,
                                                    },
                                                )}
                                            </Typography>
                                            <Typography variant="body2">
                                                {t(
                                                    'diary.ai_total_summary',
                                                    {
                                                        calories:
                                                            formatNumber(
                                                                totals.calories,
                                                                1,
                                                            ),
                                                        protein:
                                                            formatNumber(
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

                                    {saveError && (
                                        <Alert severity="error">
                                            {saveError}
                                        </Alert>
                                    )}

                                    <Stack
                                        direction={{
                                            xs: 'column-reverse',
                                            sm: 'row',
                                        }}
                                        spacing={1}
                                        justifyContent="flex-end"
                                    >
                                        <Button
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
                                            disabled={
                                                estimating ||
                                                processingImages ||
                                                !canEstimate
                                            }
                                            onClick={() =>
                                                void estimateDay()
                                            }
                                        >
                                            {estimating
                                                ? t(
                                                      'diary.ai_day_estimating',
                                                  )
                                                : t(
                                                      'diary.retry_estimate',
                                                  )}
                                        </Button>
                                        <Button
                                            variant="contained"
                                            disabled={
                                                saving ||
                                                estimating ||
                                                entries.length === 0
                                            }
                                            onClick={saveEntries}
                                        >
                                            {saving
                                                ? t(
                                                      'diary.ai_day_saving',
                                                  )
                                                : t(
                                                      'diary.ai_day_add_entries',
                                                      {
                                                          count: entries.length,
                                                      },
                                                  )}
                                        </Button>
                                    </Stack>
                                </Stack>
                            )}
                        </CardContent>
                    </Card>
                </Stack>
            </Box>
        </AppLayout>
    );
}

function DayEntryEditor({
    index,
    entry,
    onChange,
    onRemove,
}: {
    index: number;
    entry: DayEntry;
    onChange: <Key extends keyof DayEntry>(
        index: number,
        key: Key,
        value: DayEntry[Key],
    ) => void;
    onRemove: (index: number) => void;
}) {
    const { t } = useTranslation();
    const nutrients: Array<{
        key: Exclude<NutritionKey, 'weight_grams'>;
        label: string;
        unit: string;
        minimum: number;
        maximum: number;
    }> = [
        {
            key: 'calories_per_100g',
            label: t('common.calories'),
            unit: 'kcal',
            minimum: 0.01,
            maximum: 100000,
        },
        {
            key: 'protein_per_100g',
            label: t('common.protein'),
            unit: 'g',
            minimum: 0,
            maximum: 10000,
        },
        {
            key: 'carbohydrates_per_100g',
            label: t('common.carbohydrates'),
            unit: 'g',
            minimum: 0,
            maximum: 10000,
        },
        {
            key: 'fat_per_100g',
            label: t('common.fat'),
            unit: 'g',
            minimum: 0,
            maximum: 10000,
        },
        {
            key: 'fibre_per_100g',
            label: t('common.fibre'),
            unit: 'g',
            minimum: 0,
            maximum: 10000,
        },
    ];

    return (
        <Card variant="outlined">
            <CardHeader
                title={t('diary.ai_day_entry_number', {
                    number: index + 1,
                })}
                action={
                    <IconButton
                        color="error"
                        aria-label={t('diary.ai_day_remove_entry')}
                        onClick={() => onRemove(index)}
                    >
                        <DeleteOutlineRounded />
                    </IconButton>
                }
            />
            <CardContent>
                <Stack spacing={2}>
                    <TextField
                        select
                        required
                        label={t('diary.ai_day_meal')}
                        value={entry.meal}
                        onChange={(event) =>
                            onChange(
                                index,
                                'meal',
                                event.target.value as MealKey,
                            )
                        }
                    >
                        {meals.map((meal) => (
                            <MenuItem key={meal} value={meal}>
                                {t(mealLabelKeys[meal])}
                            </MenuItem>
                        ))}
                    </TextField>

                    <Grid container spacing={2}>
                        <Grid size={{ xs: 8, sm: 9 }}>
                            <TextField
                                required
                                fullWidth
                                label={t('diary.ai_food_name')}
                                value={entry.name}
                                slotProps={{
                                    htmlInput: { maxLength: 255 },
                                }}
                                onChange={(event) =>
                                    onChange(
                                        index,
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Grid>
                        <Grid size={{ xs: 4, sm: 3 }}>
                            <TextField
                                required
                                fullWidth
                                type="number"
                                label={t('diary.ai_total_amount')}
                                value={entry.weight_grams}
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
                                    onChange(
                                        index,
                                        'weight_grams',
                                        numberValue(
                                            event.target.value,
                                        ),
                                    )
                                }
                            />
                        </Grid>
                    </Grid>

                    <Divider>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                        >
                            {t('diary.ai_nutrition_per_100g')}
                        </Typography>
                    </Divider>

                    <Grid container spacing={2}>
                        {nutrients.map(
                            ({
                                key,
                                label,
                                unit,
                                minimum,
                                maximum,
                            }) => (
                                <Grid
                                    key={key}
                                    size={{ xs: 6, sm: 4 }}
                                >
                                    <TextField
                                        required
                                        fullWidth
                                        type="number"
                                        label={label}
                                        value={entry[key]}
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
                                            onChange(
                                                index,
                                                key,
                                                numberValue(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                    />
                                </Grid>
                            ),
                        )}
                    </Grid>

                    <Alert severity="warning">
                        <Typography variant="body2">
                            {t('diary.ai_confidence', {
                                confidence: t(
                                    `diary.ai_confidence_${entry.confidence}`,
                                ),
                            })}
                        </Typography>
                        {entry.assumptions && (
                            <Typography variant="body2">
                                {t('diary.ai_assumptions', {
                                    assumptions: entry.assumptions,
                                })}
                            </Typography>
                        )}
                    </Alert>
                </Stack>
            </CardContent>
        </Card>
    );
}
