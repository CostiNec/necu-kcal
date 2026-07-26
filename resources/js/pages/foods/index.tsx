import { Head, router, useForm } from '@inertiajs/react';
import ArrowBackRounded from '@mui/icons-material/ArrowBackRounded';
import ExpandLessRounded from '@mui/icons-material/ExpandLessRounded';
import ExpandMoreRounded from '@mui/icons-material/ExpandMoreRounded';
import FavoriteBorderRounded from '@mui/icons-material/FavoriteBorderRounded';
import FavoriteRounded from '@mui/icons-material/FavoriteRounded';
import AddRounded from '@mui/icons-material/AddRounded';
import CloseRounded from '@mui/icons-material/CloseRounded';
import SearchRounded from '@mui/icons-material/SearchRounded';
import {
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    CircularProgress,
    Collapse,
    Grid,
    IconButton,
    InputAdornment,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type FormEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import {
    formatNumber,
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import {
    massUnits,
    toGrams,
    type MassUnit,
} from '@/lib/mass-units';
import type { Food } from '@/types';

const mealLabels = {
    breakfast: 'diary.breakfast',
    lunch: 'diary.lunch',
    dinner: 'diary.dinner',
    snacks: 'diary.snacks',
};

type Meal = keyof typeof mealLabels;

export default function FoodsIndex({
    foods,
    filters,
    pagination,
    context,
}: {
    foods: Food[];
    filters: { search: string };
    pagination: { next_cursor: string | null };
    context: { date: string | null; meal: Meal };
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [results, setResults] = useState(foods);
    const [nextCursor, setNextCursor] = useState(pagination.next_cursor);
    const [searching, setSearching] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const initialSearch = useRef(true);
    const logging = Boolean(context.date);

    const loadFoods = useCallback(
        async ({
            value,
            cursor,
            append,
            signal,
        }: {
            value: string;
            cursor?: string | null;
            append: boolean;
            signal?: AbortSignal;
        }) => {
            const params = new URLSearchParams();
            const trimmedSearch = value.trim();

            if (trimmedSearch) {
                params.set('search', trimmedSearch);
            } else {
                params.set('favourites_only', '1');
            }

            if (cursor) {
                params.set('cursor', cursor);
            }

            const response = await fetch(`/foods/search?${params.toString()}`, {
                signal,
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) return;

            const payload = (await response.json()) as {
                foods: Food[];
                next_cursor: string | null;
            };

            setResults((current) =>
                append ? [...current, ...payload.foods] : payload.foods,
            );
            setNextCursor(payload.next_cursor);
        },
        [],
    );

    useEffect(() => {
        if (initialSearch.current) {
            initialSearch.current = false;
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setSearching(true);

            try {
                await loadFoods({
                    value: search,
                    append: false,
                    signal: controller.signal,
                });
            } catch (error) {
                if (
                    !(error instanceof DOMException) ||
                    error.name !== 'AbortError'
                ) {
                    setResults([]);
                    setNextCursor(null);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setSearching(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [loadFoods, search]);

    const loadNextPage = async () => {
        if (!nextCursor || loadingMore) return;

        setLoadingMore(true);
        try {
            await loadFoods({
                value: search,
                cursor: nextCursor,
                append: true,
            });
        } finally {
            setLoadingMore(false);
        }
    };

    const toggleFavourite = (food: Food) => {
        router.post(
            `/foods/${food.id}/favourite`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setResults((current) =>
                        search.trim() === ''
                            ? current.filter((item) => item.id !== food.id)
                            : current.map((item) =>
                                  item.id === food.id
                                      ? {
                                            ...item,
                                            is_favourite:
                                                !item.is_favourite,
                                        }
                                      : item,
                              ),
                    );
                },
            },
        );
    };

    const refreshResults = () =>
        loadFoods({ value: search, append: false });

    return (
        <AppLayout
            title={
                logging
                    ? t('food.add_to_meal', {
                          meal: t(mealLabels[context.meal]),
                      })
                    : t('food.library')
            }
            subtitle={
                logging
                    ? t('food.logging_description')
                    : t('food.library_description')
            }
            actions={
                logging ? (
                    <Button
                        size="small"
                        variant="outlined"
                        startIcon={<ArrowBackRounded />}
                        onClick={() => router.visit(`/diary/${context.date}`)}
                    >
                        {t('food.diary')}
                    </Button>
                ) : undefined
            }
        >
            <Head title={logging ? t('food.add_food_head') : t('common.foods')} />

            <Stack spacing={2.5}>
                <TextField
                    fullWidth
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t('food.search_placeholder')}
                    slotProps={{
                        input: {
                            startAdornment: (
                                <InputAdornment position="start">
                                    <SearchRounded color="action" />
                                </InputAdornment>
                            ),
                            endAdornment: (
                                <InputAdornment position="end">
                                    {searching ? (
                                        <CircularProgress size={20} />
                                    ) : search ? (
                                        <IconButton
                                            size="small"
                                            aria-label={t(
                                                'food.clear_search',
                                            )}
                                            onClick={() => setSearch('')}
                                        >
                                            <CloseRounded fontSize="small" />
                                        </IconButton>
                                    ) : null}
                                </InputAdornment>
                            ),
                        },
                    }}
                />

                <Button
                    variant="soft"
                    color="primary"
                    startIcon={<AddRounded />}
                    endIcon={
                        showCreate ? <ExpandLessRounded /> : <ExpandMoreRounded />
                    }
                    onClick={() => setShowCreate((value) => !value)}
                    sx={{ alignSelf: 'flex-start' }}
                >
                    {t('food.create_custom')}
                </Button>

                <Collapse in={showCreate} timeout={350} unmountOnExit>
                    <CreateFoodForm onCreated={refreshResults} />
                </Collapse>

                <Stack spacing={1.5}>
                    {results.length === 0 && !searching ? (
                        <Card variant="outlined" sx={{ borderStyle: 'dashed' }}>
                            <CardContent sx={{ py: { xs: 4, sm: 5 } }}>
                                <Stack
                                    direction={{ xs: 'column', sm: 'row' }}
                                    alignItems={{ xs: 'flex-start', sm: 'center' }}
                                    spacing={2.5}
                                >
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            placeItems: 'center',
                                            width: 48,
                                            height: 48,
                                            borderRadius: 2,
                                            color: 'primary.main',
                                            bgcolor: 'primary.lighter',
                                        }}
                                    >
                                        <SearchRounded />
                                    </Box>
                                    <Box sx={{ flex: 1 }}>
                                        <Typography variant="h6">
                                            {search.trim()
                                                ? t('food.no_results_for', {
                                                      search: search.trim(),
                                                  })
                                                : t(
                                                      'food.empty_favourites',
                                                  )}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                            sx={{ mt: 0.5, maxWidth: 620 }}
                                        >
                                            {search.trim()
                                                ? t('food.no_results_copy')
                                                : t(
                                                      'food.empty_favourites_copy',
                                                  )}
                                        </Typography>
                                    </Box>
                                </Stack>
                            </CardContent>
                        </Card>
                    ) : (
                        results.map((food) => (
                            <FoodRow
                                key={food.id}
                                food={food}
                                date={context.date}
                                meal={context.meal}
                                onToggleFavourite={() =>
                                    toggleFavourite(food)
                                }
                            />
                        ))
                    )}

                    {nextCursor && (
                        <Button
                            variant="outlined"
                            disabled={loadingMore}
                            onClick={loadNextPage}
                            sx={{ alignSelf: 'center', minWidth: 160 }}
                        >
                            {loadingMore
                                ? t('food.loading_more')
                                : t('food.load_more')}
                        </Button>
                    )}
                </Stack>
            </Stack>
        </AppLayout>
    );
}

function FoodRow({
    food,
    date,
    meal,
    onToggleFavourite,
}: {
    food: Food;
    date: string | null;
    meal: Meal;
    onToggleFavourite: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{
        food_id: number;
        date: string;
        meal: Meal;
        unit: MassUnit;
        amount: NumberInputValue;
        quantity: NumberInputValue;
    }>({
        food_id: food.id,
        date: date ?? '',
        meal,
        unit: 'g',
        amount: 100,
        quantity: 1,
    });
    const calculatedCalories =
        (food.calories *
            toGrams(
                Number(form.data.amount),
                form.data.unit,
                Number(form.data.quantity),
            )) /
        100;

    return (
        <Card
            sx={{
                transition: (theme) =>
                    theme.transitions.create(['transform', 'box-shadow']),
                '&:hover': {
                    transform: 'translateY(-2px)',
                    boxShadow: (theme) => theme.shadows[8],
                },
            }}
        >
            <CardContent>
                <Stack direction="row" alignItems="flex-start" spacing={1.5}>
                    <IconButton
                        color={food.is_favourite ? 'primary' : 'default'}
                        aria-label={
                            food.is_favourite
                                ? t('food.remove_favourite', { food: food.name })
                                : t('food.add_favourite', { food: food.name })
                        }
                        onClick={onToggleFavourite}
                    >
                        {food.is_favourite ? (
                            <FavoriteRounded />
                        ) : (
                            <FavoriteBorderRounded />
                        )}
                    </IconButton>
                    <Box sx={{ minWidth: 0, flex: 1 }}>
                        <Stack
                            direction="row"
                            alignItems="flex-start"
                            justifyContent="space-between"
                            spacing={2}
                        >
                            <Box sx={{ minWidth: 0 }}>
                                <Typography variant="subtitle1" noWrap>
                                    {food.name}
                                </Typography>
                                <Typography variant="caption" color="text.secondary">
                                    {food.brand ||
                                        (food.is_custom
                                            ? t('food.custom')
                                            : t('food.common'))}{' '}
                                    · {t('food.per_units', { unit: 'g' })}
                                </Typography>
                            </Box>
                            <Chip
                                size="small"
                                color="primary"
                                variant="filled"
                                label={`${formatNumber(food.calories)} kcal`}
                            />
                        </Stack>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            sx={{ display: 'block', mt: 1 }}
                        >
                            {t('food.macro_summary', {
                                protein: formatNumber(food.protein ?? 0, 1),
                                carbs: formatNumber(food.carbohydrates ?? 0, 1),
                                fat: formatNumber(food.fat ?? 0, 1),
                                fibre: formatNumber(food.fibre ?? 0, 1),
                            })}
                        </Typography>
                    </Box>
                </Stack>
            </CardContent>

            {date && (
                <Box
                    component="form"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        form.post('/diary-entries');
                    }}
                    sx={{
                        p: 2,
                        pt: 0,
                        display: 'grid',
                        gridTemplateColumns: {
                            xs: '1fr 1fr',
                            sm: '110px minmax(120px, 1fr) minmax(100px, 1fr) auto',
                        },
                        gap: 1.5,
                        alignItems: 'start',
                    }}
                >
                    <TextField
                        select
                        label={t('common.unit')}
                        value={form.data.unit}
                        onChange={(event) =>
                            form.setData('unit', event.target.value as MassUnit)
                        }
                    >
                        {massUnits.map((unit) => (
                            <MenuItem key={unit} value={unit}>
                                {unit}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        label={t('common.amount')}
                        type="number"
                        value={form.data.amount}
                        slotProps={{ htmlInput: { min: 0.01, step: 0.01 } }}
                        onChange={(event) =>
                            form.setData(
                                'amount',
                                parseNumberInput(event.target.value),
                            )
                        }
                    />
                    <TextField
                        label={t('common.quantity')}
                        type="number"
                        value={form.data.quantity}
                        slotProps={{ htmlInput: { min: 0.25, step: 0.25 } }}
                        onChange={(event) =>
                            form.setData(
                                'quantity',
                                parseNumberInput(event.target.value),
                            )
                        }
                    />
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                        startIcon={
                            form.processing ? (
                                <CircularProgress size={18} color="inherit" />
                            ) : (
                                <AddRounded />
                            )
                        }
                        sx={{ minHeight: 54, gridColumn: { xs: '1 / -1', sm: 'auto' } }}
                    >
                        {formatNumber(calculatedCalories)} kcal
                    </Button>
                </Box>
            )}
        </Card>
    );
}

function CreateFoodForm({ onCreated }: { onCreated: () => void }) {
    const { t } = useTranslation();
    const form = useForm<{
        name: string;
        brand: string;
        barcode: string;
        calories: NumberInputValue;
        protein: NumberInputValue;
        carbohydrates: NumberInputValue;
        fat: NumberInputValue;
        fibre: NumberInputValue;
    }>({
        name: '',
        brand: '',
        barcode: '',
        calories: 0,
        protein: 0,
        carbohydrates: 0,
        fat: 0,
        fibre: 0,
    });
    const nutrientFields = [
        ['calories', t('common.calories')],
        ['protein', t('common.protein')],
        ['carbohydrates', t('common.carbs')],
        ['fat', t('common.fat')],
        ['fibre', t('common.fibre')],
    ] as const;

    return (
        <Card id="create-food-form" variant="outlined" sx={{ scrollMarginTop: 96 }}>
            <CardContent>
                <Stack
                    component="form"
                    spacing={2.5}
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        form.post('/foods', {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                void onCreated();
                            },
                        });
                    }}
                >
                    <Grid container spacing={2}>
                        <Grid size={{ xs: 12, sm: 6 }}>
                            <TextField
                                fullWidth
                                required
                                label={t('food.food_name')}
                                value={form.data.name}
                                error={Boolean(form.errors.name)}
                                helperText={form.errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Grid>
                        <Grid size={{ xs: 12, sm: 6 }}>
                            <TextField
                                fullWidth
                                label={t('common.brand')}
                                value={form.data.brand}
                                error={Boolean(form.errors.brand)}
                                helperText={form.errors.brand}
                                onChange={(event) =>
                                    form.setData('brand', event.target.value)
                                }
                            />
                        </Grid>
                    </Grid>

                    <Typography variant="subtitle2">
                        {t('food.nutrition_per_100')}
                    </Typography>
                    <Grid container spacing={2}>
                        {nutrientFields.map(([key, label]) => (
                            <Grid key={key} size={{ xs: 6, sm: 4, md: 2.4 }}>
                                <TextField
                                    fullWidth
                                    label={label}
                                    type="number"
                                    value={form.data[key]}
                                    error={Boolean(form.errors[key])}
                                    helperText={form.errors[key]}
                                    slotProps={{
                                        htmlInput: { min: 0, step: 0.01 },
                                    }}
                                    onChange={(event) =>
                                        form.setData(
                                            key,
                                            parseNumberInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                />
                            </Grid>
                        ))}
                    </Grid>

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                            startIcon={
                                form.processing ? (
                                    <CircularProgress size={18} color="inherit" />
                                ) : (
                                    <AddRounded />
                                )
                            }
                        >
                            {t('food.save_custom')}
                        </Button>
                    </Box>
                </Stack>
            </CardContent>
        </Card>
    );
}
