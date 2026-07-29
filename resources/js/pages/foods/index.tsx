import { Head, router, useForm } from '@inertiajs/react';
import ExpandLessRounded from '@mui/icons-material/ExpandLessRounded';
import ExpandMoreRounded from '@mui/icons-material/ExpandMoreRounded';
import FavoriteBorderRounded from '@mui/icons-material/FavoriteBorderRounded';
import FavoriteRounded from '@mui/icons-material/FavoriteRounded';
import AddRounded from '@mui/icons-material/AddRounded';
import CloseRounded from '@mui/icons-material/CloseRounded';
import QrCodeScannerRounded from '@mui/icons-material/QrCodeScannerRounded';
import {
    Avatar,
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
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { BarcodeScannerDialog } from '@/components/barcode-scanner-dialog';
import {
    formatNumber,
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import {
    toBaseAmount,
    unitsForBasis,
    type MeasurementUnit,
    type NutritionBasisUnit,
} from '@/lib/measurement-units';
import type { Food } from '@/types';

const mealLabels = {
    breakfast: 'diary.breakfast',
    lunch: 'diary.lunch',
    dinner: 'diary.dinner',
    snacks: 'diary.snacks',
};

type Meal = keyof typeof mealLabels;
type FoodTab = 'recent' | 'favourites' | 'recipes';

const isBarcode = (value: string) => /^\d{6,18}$/.test(value.trim());
const matchesLocalSearch = (food: Food, search: string) => {
    const query = search.trim().toLocaleLowerCase();

    if (!query) return true;

    return [food.name, food.brand, food.barcode].some((value) =>
        value?.toLocaleLowerCase().includes(query),
    );
};

export default function FoodsIndex({
    foods,
    lists,
    filters,
    pagination,
    context,
}: {
    foods: Food[];
    lists: Record<FoodTab, Food[]>;
    filters: { search: string };
    pagination: { next_cursor: string | null };
    context: { date: string | null; meal: Meal; today: string };
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(
        isBarcode(filters.search) ? '' : filters.search,
    );
    const [barcodeSearch, setBarcodeSearch] = useState<string | null>(
        isBarcode(filters.search) ? filters.search.trim() : null,
    );
    const [activeTab, setActiveTab] = useState<FoodTab>('recent');
    const [tabLists, setTabLists] = useState(lists);
    const [databaseSearch, setDatabaseSearch] = useState(
        filters.search.trim() !== '',
    );
    const [resolvedSearch, setResolvedSearch] = useState(filters.search.trim());
    const [results, setResults] = useState(foods);
    const [nextCursor, setNextCursor] = useState(pagination.next_cursor);
    const [searching, setSearching] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const [createBarcode, setCreateBarcode] = useState<string | null>(null);
    const [scannerOpen, setScannerOpen] = useState(false);
    const requestSequence = useRef(0);
    const logging = Boolean(context.date);
    const activeSearch = barcodeSearch ?? search;
    const localResults = useMemo(
        () =>
            tabLists[activeTab].filter((food) =>
                matchesLocalSearch(food, search),
            ),
        [activeTab, search, tabLists],
    );
    const visibleResults = databaseSearch ? results : localResults;

    const loadFoods = async ({
        value,
        cursor,
        append,
    }: {
        value: string;
        cursor?: string | null;
        append: boolean;
    }) => {
        const trimmedSearch = value.trim();

        if (!trimmedSearch) {
            requestSequence.current += 1;
            setDatabaseSearch(false);
            setSearching(false);
            setResults([]);
            setNextCursor(null);
            setResolvedSearch('');
            return;
        }

        const requestId = append
            ? requestSequence.current
            : ++requestSequence.current;
        const params = new URLSearchParams({ search: trimmedSearch });

        if (cursor) {
            params.set('cursor', cursor);
        }

        setDatabaseSearch(true);
        setSearching(true);
        setResolvedSearch(trimmedSearch);

        try {
            const response = await fetch(
                `/foods/search?${params.toString()}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('Unable to load foods.');
            }

            const payload = (await response.json()) as {
                foods: Food[];
                next_cursor: string | null;
            };

            if (requestId !== requestSequence.current) return;

            setResults((current) =>
                append ? [...current, ...payload.foods] : payload.foods,
            );
            setNextCursor(payload.next_cursor);
        } catch {
            if (requestId !== requestSequence.current) return;

            setResults([]);
            setNextCursor(null);
        } finally {
            if (requestId === requestSequence.current) {
                setSearching(false);
            }
        }
    };

    const runDatabaseSearch = (value = activeSearch) => {
        setCreateBarcode(null);
        setShowCreate(false);
        void loadFoods({ value, append: false });
    };

    const loadNextPage = async () => {
        if (!nextCursor || loadingMore) return;

        setLoadingMore(true);
        try {
            await loadFoods({
                value: resolvedSearch,
                cursor: nextCursor,
                append: true,
            });
        } finally {
            setLoadingMore(false);
        }
    };

    const toggleFavourite = (food: Food) => {
        const willBeFavourite = !food.is_favourite;
        const updatedFood = {
            ...food,
            is_favourite: willBeFavourite,
        };

        router.post(
            `/foods/${food.id}/favourite`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setResults((current) =>
                        current.map((item) =>
                            item.id === food.id ? updatedFood : item,
                        ),
                    );
                    setTabLists((current) => {
                        const updateFlags = (items: Food[]) =>
                            items.map((item) =>
                                item.id === food.id ? updatedFood : item,
                            );
                        const favourites = willBeFavourite
                            ? current.favourites.some(
                                  (item) => item.id === food.id,
                              )
                                ? updateFlags(current.favourites)
                                : [updatedFood, ...current.favourites]
                            : current.favourites.filter(
                                  (item) => item.id !== food.id,
                              );
                        const belongsInRecipes =
                            food.is_recipe &&
                            (food.is_owned || willBeFavourite);
                        const recipes = belongsInRecipes
                            ? current.recipes.some(
                                  (item) => item.id === food.id,
                              )
                                ? updateFlags(current.recipes)
                                : [updatedFood, ...current.recipes]
                            : current.recipes.filter(
                                  (item) => item.id !== food.id,
                              );

                        return {
                            recent: updateFlags(current.recent),
                            favourites,
                            recipes,
                        };
                    });
                },
            },
        );
    };

    const refreshResults = () => {
        if (databaseSearch && activeSearch.trim()) {
            runDatabaseSearch();
        }
    };
    const handleBarcodeDetected = (barcode: string) => {
        const value = barcode.trim();

        setSearch('');
        setBarcodeSearch(value);
        setCreateBarcode(null);
        setShowCreate(false);
        setScannerOpen(false);
        runDatabaseSearch(value);
    };
    const clearSearch = () => {
        requestSequence.current += 1;
        setSearch('');
        setBarcodeSearch(null);
        setCreateBarcode(null);
        setShowCreate(false);
        setDatabaseSearch(false);
        setSearching(false);
        setResults([]);
        setNextCursor(null);
        setResolvedSearch('');
    };
    const toggleCreate = () => {
        setShowCreate((value) => {
            if (!value) {
                setCreateBarcode(null);
            }

            return !value;
        });
    };
    const createScannedProduct = () => {
        if (!barcodeSearch) return;

        setCreateBarcode(barcodeSearch);
        setShowCreate(true);
    };

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
            back={
                logging
                    ? {
                          href: `/diary/${context.date}?focus_meal=${context.meal}`,
                          label: t('food.diary'),
                      }
                    : undefined
            }
        >
            <Head title={logging ? t('food.add_food_head') : t('common.foods')} />

            <Stack spacing={2}>
                {logging && (
                    <TextField
                        select
                        fullWidth
                        label={t('food.meal')}
                        value={context.meal}
                        onChange={(event) => {
                            const meal = event.target.value as Meal;

                            router.visit(
                                `/foods?date=${context.date}&meal=${meal}`,
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                },
                            );
                        }}
                    >
                        {Object.entries(mealLabels).map(
                            ([meal, labelKey]) => (
                                <MenuItem key={meal} value={meal}>
                                    {t(labelKey)}
                                </MenuItem>
                            ),
                        )}
                    </TextField>
                )}

                <Box
                    component="form"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        runDatabaseSearch();
                    }}
                >
                    <TextField
                        fullWidth
                        value={search}
                        onChange={(event) => {
                            requestSequence.current += 1;
                            setBarcodeSearch(null);
                            setSearch(event.target.value);
                            setDatabaseSearch(false);
                            setSearching(false);
                        }}
                        onKeyDown={(event) => {
                            if (event.key !== 'Enter') return;

                            event.preventDefault();
                            runDatabaseSearch();
                        }}
                        placeholder={t('food.search_placeholder')}
                        slotProps={{
                            input: {
                                endAdornment: (
                                    <InputAdornment position="end">
                                        {activeSearch && (
                                            <IconButton
                                                type="button"
                                                size="small"
                                                aria-label={t(
                                                    'food.clear_search',
                                                )}
                                                onClick={clearSearch}
                                            >
                                                <CloseRounded fontSize="small" />
                                            </IconButton>
                                        )}
                                        <IconButton
                                            type="button"
                                            size="small"
                                            color="primary"
                                            aria-label={t(
                                                'food.scan_barcode',
                                            )}
                                            onClick={() =>
                                                setScannerOpen(true)
                                            }
                                        >
                                            <QrCodeScannerRounded fontSize="small" />
                                        </IconButton>
                                        {searching ? (
                                            <CircularProgress size={20} />
                                        ) : (
                                            <Button
                                                type="submit"
                                                size="small"
                                                variant="contained"
                                                sx={{ ml: 0.5 }}
                                            >
                                                {t('common.search')}
                                            </Button>
                                        )}
                                    </InputAdornment>
                                ),
                            },
                        }}
                    />
                </Box>

                <BarcodeScannerDialog
                    open={scannerOpen}
                    onClose={() => setScannerOpen(false)}
                    onDetected={handleBarcodeDetected}
                />

                {!barcodeSearch && (
                    <>
                        <Button
                            variant="soft"
                            color="primary"
                            startIcon={<AddRounded />}
                            endIcon={
                                showCreate ? (
                                    <ExpandLessRounded />
                                ) : (
                                    <ExpandMoreRounded />
                                )
                            }
                            onClick={toggleCreate}
                            sx={{ alignSelf: 'flex-start' }}
                        >
                            {t('food.create_custom')}
                        </Button>

                        <Collapse
                            in={showCreate}
                            timeout={350}
                            unmountOnExit
                        >
                            <CreateFoodForm
                                key="custom"
                                initialBarcode=""
                                onCreated={refreshResults}
                            />
                        </Collapse>
                    </>
                )}

                {!databaseSearch && (
                    <Tabs
                        value={activeTab}
                        onChange={(_, value: FoodTab) => setActiveTab(value)}
                        variant="scrollable"
                        scrollButtons="auto"
                        aria-label={t('food.food_lists')}
                    >
                        <Tab
                            value="recent"
                            label={`${t('food.recent')} (${tabLists.recent.length})`}
                        />
                        <Tab
                            value="favourites"
                            label={`${t('food.favourites')} (${tabLists.favourites.length})`}
                        />
                        <Tab
                            value="recipes"
                            label={`${t('common.recipes')} (${tabLists.recipes.length})`}
                        />
                    </Tabs>
                )}

                {databaseSearch && !searching && (
                    <Typography variant="subtitle2" color="text.secondary">
                        {t('food.database_results_for', {
                            search: resolvedSearch,
                        })}
                    </Typography>
                )}

                <Stack spacing={2}>
                    {searching ? (
                        <Box sx={{ display: 'grid', placeItems: 'center', py: 6 }}>
                            <CircularProgress />
                        </Box>
                    ) : visibleResults.length === 0 ? (
                        barcodeSearch &&
                        showCreate &&
                        createBarcode ? (
                            <CreateFoodForm
                                key={createBarcode}
                                initialBarcode={createBarcode}
                                onCreated={refreshResults}
                            />
                        ) : (
                            <Card
                                variant="outlined"
                                sx={{
                                    borderStyle: barcodeSearch
                                        ? 'solid'
                                        : 'dashed',
                                }}
                            >
                                <CardContent>
                                    <Box sx={{ flex: 1 }}>
                                        <Typography variant="h6">
                                            {barcodeSearch
                                                ? t(
                                                      'food.barcode_not_found',
                                                  )
                                                : search.trim()
                                                ? t('food.no_results_for', {
                                                      search: search.trim(),
                                                  })
                                                : t(`food.empty_${activeTab}`)}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                            sx={{ mt: 0.5, maxWidth: 620 }}
                                        >
                                            {barcodeSearch
                                                ? t(
                                                      'food.barcode_not_found_copy',
                                                  )
                                                : search.trim()
                                                ? databaseSearch
                                                    ? t('food.no_results_copy')
                                                    : t(
                                                          'food.local_no_results_copy',
                                                      )
                                                : t(
                                                      `food.empty_${activeTab}_copy`,
                                                  )}
                                        </Typography>
                                        {barcodeSearch && (
                                            <Button
                                                variant="contained"
                                                startIcon={<AddRounded />}
                                                onClick={createScannedProduct}
                                                sx={{
                                                    mt: 2,
                                                    width: {
                                                        xs: '100%',
                                                        sm: 'auto',
                                                    },
                                                }}
                                            >
                                                {t(
                                                    'food.add_scanned_product',
                                                )}
                                            </Button>
                                        )}
                                    </Box>
                                </CardContent>
                            </Card>
                        )
                    ) : (
                        visibleResults.map((food) => (
                            <FoodRow
                                key={`${food.id}-${context.date ?? 'library'}-${context.meal}`}
                                food={food}
                                date={context.date}
                                meal={context.meal}
                                targetDate={context.today}
                                onToggleFavourite={() =>
                                    toggleFavourite(food)
                                }
                            />
                        ))
                    )}

                    {databaseSearch && !searching && nextCursor && (
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
    targetDate,
    onToggleFavourite,
}: {
    food: Food;
    date: string | null;
    meal: Meal;
    targetDate: string;
    onToggleFavourite: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{
        food_id: number;
        date: string;
        meal: Meal;
        unit: MeasurementUnit;
        amount: NumberInputValue;
        quantity: NumberInputValue;
    }>({
        food_id: food.id,
        date: date ?? '',
        meal,
        unit: food.nutrition_basis_unit,
        amount: food.nutrition_basis_amount,
        quantity: 1,
    });
    const calculatedCalories =
        (food.calories *
            toBaseAmount(
                Number(form.data.amount),
                form.data.unit,
                Number(form.data.quantity),
            )) /
        food.nutrition_basis_amount;
    const libraryDestination =
        food.is_recipe && food.recipe_id
            ? `/recipes/${food.recipe_id}`
            : `/foods?date=${targetDate}&meal=${meal}`;
    const recipeCardIsClickable =
        !date && food.is_recipe && food.recipe_id !== null;

    return (
        <Card
            role={recipeCardIsClickable ? 'link' : undefined}
            tabIndex={recipeCardIsClickable ? 0 : undefined}
            aria-label={recipeCardIsClickable ? food.name : undefined}
            onClick={
                recipeCardIsClickable
                    ? () => router.visit(libraryDestination)
                    : undefined
            }
            onKeyDown={
                recipeCardIsClickable
                    ? (event) => {
                          if (
                              event.currentTarget !== event.target ||
                              event.key !== 'Enter'
                          ) {
                              return;
                          }

                          event.preventDefault();
                          router.visit(libraryDestination);
                      }
                    : undefined
            }
            sx={{
                transition: (theme) =>
                    theme.transitions.create(
                        ['transform', 'box-shadow', 'background-color'],
                        { duration: theme.transitions.duration.shorter },
                    ),
                ...(recipeCardIsClickable && {
                    cursor: 'pointer',
                    touchAction: 'manipulation',
                    WebkitTapHighlightColor: 'transparent',
                    '@media (hover: hover)': {
                        '&:hover': {
                            transform: 'translateY(-2px)',
                            boxShadow: (theme) => theme.shadows[8],
                        },
                    },
                    '&:active': {
                        transform: 'scale(0.985)',
                        bgcolor: 'action.hover',
                    },
                    '&:focus-visible': {
                        outline: '2px solid',
                        outlineColor: 'primary.main',
                        outlineOffset: 2,
                    },
                }),
            }}
        >
            <CardContent>
                <Stack direction="row" alignItems="flex-start" spacing={2}>
                    <IconButton
                        color={food.is_favourite ? 'primary' : 'default'}
                        aria-label={
                            food.is_favourite
                                ? t('food.remove_favourite', { food: food.name })
                                : t('food.add_favourite', { food: food.name })
                        }
                        onClick={(event) => {
                            event.stopPropagation();
                            onToggleFavourite();
                        }}
                        onKeyDown={(event) => event.stopPropagation()}
                    >
                        {food.is_favourite ? (
                            <FavoriteRounded />
                        ) : (
                            <FavoriteBorderRounded />
                        )}
                    </IconButton>
                    <Box
                        role={
                            date || recipeCardIsClickable
                                ? undefined
                                : 'button'
                        }
                        tabIndex={
                            date || recipeCardIsClickable ? undefined : 0
                        }
                        onClick={
                            date || recipeCardIsClickable
                                ? undefined
                                : () =>
                                      router.visit(
                                          libraryDestination,
                                          {
                                              preserveScroll: true,
                                              preserveState: true,
                                          },
                                      )
                        }
                        onKeyDown={
                            date || recipeCardIsClickable
                                ? undefined
                                : (event) => {
                                      if (
                                          event.key !== 'Enter' &&
                                          event.key !== ' '
                                      ) {
                                          return;
                                      }

                                      event.preventDefault();
                                      router.visit(
                                          libraryDestination,
                                          {
                                              preserveScroll: true,
                                              preserveState: true,
                                          },
                                      );
                                  }
                        }
                        sx={{
                            minWidth: 0,
                            flex: 1,
                            cursor:
                                date || recipeCardIsClickable
                                    ? 'inherit'
                                    : 'pointer',
                            borderRadius: 1,
                            '&:focus-visible': {
                                outline: '2px solid',
                                outlineColor: 'primary.main',
                                outlineOffset: 4,
                            },
                        }}
                    >
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
                                    ·{' '}
                                    {t('food.per_units', {
                                        amount: formatNumber(
                                            food.nutrition_basis_amount,
                                        ),
                                        unit: food.nutrition_basis_unit,
                                    })}
                                </Typography>
                            </Box>
                            <Stack
                                direction="row"
                                spacing={0.75}
                                alignItems="center"
                                sx={{ flexShrink: 0 }}
                            >
                                {food.is_recipe &&
                                    !food.is_owned &&
                                    food.recipe_owner && (
                                        <Chip
                                            clickable
                                            size="small"
                                            variant="outlined"
                                            avatar={
                                                <Avatar>
                                                    {food.recipe_owner.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </Avatar>
                                            }
                                            label={`@${food.recipe_owner.username}`}
                                            aria-label={t(
                                                'food.view_recipe_owner',
                                                {
                                                    name: food.recipe_owner
                                                        .name,
                                                },
                                            )}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                router.visit(
                                                    `/users/${food.recipe_owner?.username}`,
                                                );
                                            }}
                                            onKeyDown={(event) =>
                                                event.stopPropagation()
                                            }
                                            sx={{
                                                maxWidth: {
                                                    xs: 120,
                                                    sm: 150,
                                                },
                                                '& .MuiChip-label': {
                                                    display: 'block',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                },
                                                '& .MuiChip-avatar': {
                                                    mr: -0.5,
                                                },
                                            }}
                                        />
                                    )}
                                <Chip
                                    size="small"
                                    color="primary"
                                    variant="filled"
                                    label={`${formatNumber(food.calories)} kcal`}
                                />
                            </Stack>
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
                        gap: 2,
                        alignItems: 'start',
                    }}
                >
                    <TextField
                        select
                        label={t('common.unit')}
                        value={form.data.unit}
                        onChange={(event) =>
                            form.setData(
                                'unit',
                                event.target.value as MeasurementUnit,
                            )
                        }
                    >
                        {unitsForBasis(food.nutrition_basis_unit).map((unit) => (
                            <MenuItem key={unit} value={unit}>
                                {unit}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        label={t('common.amount')}
                        type="text"
                        value={form.data.amount}
                        slotProps={{
                            htmlInput: {
                                inputMode: 'decimal',
                                min: 0.01,
                                step: 0.01,
                            },
                        }}
                        onChange={(event) =>
                            form.setData(
                                'amount',
                                parseNumberInput(event.target.value),
                            )
                        }
                    />
                    <TextField
                        label={t('common.quantity')}
                        type="text"
                        value={form.data.quantity}
                        slotProps={{
                            htmlInput: {
                                inputMode: 'decimal',
                                min: 0.25,
                                step: 0.25,
                            },
                        }}
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
                        sx={{ gridColumn: { xs: '1 / -1', sm: 'auto' } }}
                    >
                        {formatNumber(calculatedCalories)} kcal
                    </Button>
                </Box>
            )}
        </Card>
    );
}

function CreateFoodForm({
    initialBarcode,
    onCreated,
}: {
    initialBarcode: string;
    onCreated: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm<{
        name: string;
        brand: string;
        barcode: string;
        calories: NumberInputValue;
        nutrition_basis_amount: NumberInputValue;
        nutrition_basis_unit: NutritionBasisUnit;
        protein: NumberInputValue;
        carbohydrates: NumberInputValue;
        fat: NumberInputValue;
        fibre: NumberInputValue;
    }>({
        name: '',
        brand: '',
        barcode: initialBarcode,
        calories: 0,
        nutrition_basis_amount: 100,
        nutrition_basis_unit: 'g',
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
                    spacing={2}
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
                    {initialBarcode && (
                        <Box>
                            <Typography variant="h6">
                                {t('food.add_product_details')}
                            </Typography>
                            <Typography
                                variant="body2"
                                color="text.secondary"
                                sx={{ mt: 0.5 }}
                            >
                                {t('food.add_product_details_copy')}
                            </Typography>
                        </Box>
                    )}

                    <Grid container spacing={2}>
                        <Grid size={{ xs: 12, sm: 6 }}>
                            <TextField
                                fullWidth
                                required
                                autoFocus={Boolean(initialBarcode)}
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

                    {form.errors.barcode && (
                        <Typography variant="body2" color="error">
                            {form.errors.barcode}
                        </Typography>
                    )}

                    <Grid container spacing={2}>
                        <Grid size={{ xs: 8, sm: 4 }}>
                            <TextField
                                fullWidth
                                required
                                type="text"
                                label={t('food.nutrition_basis_amount')}
                                value={form.data.nutrition_basis_amount}
                                error={Boolean(
                                    form.errors.nutrition_basis_amount,
                                )}
                                helperText={
                                    form.errors.nutrition_basis_amount
                                }
                                slotProps={{
                                    htmlInput: {
                                        inputMode: 'decimal',
                                        min: 0.01,
                                        step: 0.01,
                                    },
                                }}
                                onChange={(event) =>
                                    form.setData(
                                        'nutrition_basis_amount',
                                        parseNumberInput(event.target.value),
                                    )
                                }
                            />
                        </Grid>
                        <Grid size={{ xs: 4, sm: 2 }}>
                            <TextField
                                fullWidth
                                select
                                label={t('common.unit')}
                                value={form.data.nutrition_basis_unit}
                                onChange={(event) =>
                                    form.setData(
                                        'nutrition_basis_unit',
                                        event.target
                                            .value as NutritionBasisUnit,
                                    )
                                }
                            >
                                <MenuItem value="g">g</MenuItem>
                                <MenuItem value="ml">ml</MenuItem>
                            </TextField>
                        </Grid>
                    </Grid>
                    <Typography variant="subtitle2">
                        {t('food.nutrition_basis', {
                            amount:
                                Number(form.data.nutrition_basis_amount) || 0,
                            unit: form.data.nutrition_basis_unit,
                        })}
                    </Typography>
                    <Grid container spacing={2}>
                        {nutrientFields.map(([key, label]) => (
                            <Grid key={key} size={{ xs: 6, sm: 4, md: 2.4 }}>
                                <TextField
                                    fullWidth
                                    label={label}
                                    type="text"
                                    value={form.data[key]}
                                    error={Boolean(form.errors[key])}
                                    helperText={form.errors[key]}
                                    slotProps={{
                                        htmlInput: {
                                            inputMode: 'decimal',
                                            min: 0,
                                            step: 0.01,
                                        },
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
                            {initialBarcode
                                ? t('food.save_product')
                                : t('food.save_custom')}
                        </Button>
                    </Box>
                </Stack>
            </CardContent>
        </Card>
    );
}
