import { Head, useForm } from '@inertiajs/react';
import AddRounded from '@mui/icons-material/AddRounded';
import ArrowBackRounded from '@mui/icons-material/ArrowBackRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import RestaurantMenuOutlined from '@mui/icons-material/RestaurantMenuOutlined';
import ScaleOutlined from '@mui/icons-material/ScaleOutlined';
import {
    Alert,
    Autocomplete,
    Box,
    Button,
    Card,
    CardContent,
    CardHeader,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    Grid,
    IconButton,
    InputAdornment,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import {
    formatNumber,
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import { RouterLink } from '@/components/router-link';
import type { NutritionBasisUnit } from '@/lib/measurement-units';

export type FoodOption = {
    id: number;
    name: string;
    brand: string | null;
    calories: number;
    nutrition_basis_amount: number;
    nutrition_basis_unit: NutritionBasisUnit;
    protein: number | null;
    carbohydrates: number | null;
    fat: number | null;
    fibre: number | null;
};

type IngredientInput = {
    food_id: number | null;
    amount: NumberInputValue;
};

export type EditableRecipe = {
    id: number;
    name: string;
    cooked_weight: number;
    ingredients: {
        food_id: number | null;
        amount: number;
        food: FoodOption | null;
    }[];
};

const emptyIngredient = (): IngredientInput => ({
    food_id: null,
    amount: 100,
});

export default function RecipeFormPage({
    createdFood,
    recipe = null,
}: {
    createdFood: FoodOption | null;
    recipe?: EditableRecipe | null;
}) {
    const { t } = useTranslation();
    const editing = recipe !== null;
    const [foodDialogOpen, setFoodDialogOpen] = useState(false);
    const [foodOptions, setFoodOptions] = useState<FoodOption[]>(
        [
            ...(recipe?.ingredients
                .map((ingredient) => ingredient.food)
                .filter((food): food is FoodOption => food !== null) ?? []),
            ...(createdFood ? [createdFood] : []),
        ],
    );
    const [foodSearch, setFoodSearch] = useState('');
    const [foodSearching, setFoodSearching] = useState(false);
    const form = useForm<{
        name: string;
        cooked_weight: NumberInputValue;
        ingredients: IngredientInput[];
    }>({
        name: recipe?.name ?? '',
        cooked_weight: recipe?.cooked_weight ?? 0,
        ingredients:
            recipe?.ingredients.map((ingredient) => ({
                food_id: ingredient.food_id,
                amount: ingredient.amount,
            })) ?? [emptyIngredient()],
    });
    const title = editing
        ? t('recipe.edit_title')
        : t('recipe.create_title');
    const description = editing
        ? t('recipe.edit_description')
        : t('recipe.create_description');

    const foodById = useMemo(
        () => new Map(foodOptions.map((food) => [food.id, food])),
        [foodOptions],
    );
    const totals = useMemo(
        () =>
            form.data.ingredients.reduce(
                (result, ingredient) => {
                    const food = ingredient.food_id
                        ? foodById.get(ingredient.food_id)
                        : null;

                    if (!food) return result;

                    const factor =
                        Number(ingredient.amount) /
                        food.nutrition_basis_amount;
                    result.calories += food.calories * factor;
                    result.protein += (food.protein ?? 0) * factor;
                    result.carbohydrates +=
                        (food.carbohydrates ?? 0) * factor;
                    result.fat += (food.fat ?? 0) * factor;
                    result.fibre += (food.fibre ?? 0) * factor;

                    return result;
                },
                {
                    calories: 0,
                    protein: 0,
                    carbohydrates: 0,
                    fat: 0,
                    fibre: 0,
                },
            ),
        [foodById, form.data.ingredients],
    );
    const nutritionPerHundred = useMemo(() => {
        const factor =
            Number(form.data.cooked_weight) > 0
                ? 100 / Number(form.data.cooked_weight)
                : 0;

        return Object.fromEntries(
            Object.entries(totals).map(([key, value]) => [key, value * factor]),
        ) as typeof totals;
    }, [form.data.cooked_weight, totals]);
    const fieldErrors = form.errors as Record<string, string>;

    useEffect(() => {
        const search = foodSearch.trim();

        if (search.length < 2) {
            setFoodSearching(false);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setFoodSearching(true);

            try {
                const response = await fetch(
                    `/foods/search?search=${encodeURIComponent(
                        search,
                    )}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!response.ok) return;

                const payload = (await response.json()) as {
                    foods: FoodOption[];
                };

                setFoodOptions((current) => {
                    const options = new Map(
                        current.map((food) => [food.id, food]),
                    );

                    payload.foods.forEach((food) =>
                        options.set(food.id, food),
                    );

                    return Array.from(options.values());
                });
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }
            } finally {
                if (!controller.signal.aborted) {
                    setFoodSearching(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [foodSearch]);

    useEffect(() => {
        if (!createdFood) return;

        setFoodOptions((current) => {
            const options = new Map(
                current.map((food) => [food.id, food]),
            );
            options.set(createdFood.id, createdFood);

            return Array.from(options.values());
        });

        const emptyIndex = form.data.ingredients.findIndex(
            (ingredient) => ingredient.food_id === null,
        );
        const nextIngredients = [...form.data.ingredients];

        if (emptyIndex >= 0) {
            nextIngredients[emptyIndex] = {
                ...nextIngredients[emptyIndex],
                food_id: createdFood.id,
                amount: createdFood.nutrition_basis_amount,
            };
        } else {
            nextIngredients.push({
                food_id: createdFood.id,
                amount: createdFood.nutrition_basis_amount,
            });
        }

        form.setData('ingredients', nextIngredients);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [createdFood?.id]);

    const updateIngredient = (
        index: number,
        values: Partial<IngredientInput>,
    ) => {
        form.setData(
            'ingredients',
            form.data.ingredients.map((ingredient, ingredientIndex) =>
                ingredientIndex === index
                    ? { ...ingredient, ...values }
                    : ingredient,
            ),
        );
    };

    const removeIngredient = (index: number) => {
        form.setData(
            'ingredients',
            form.data.ingredients.filter(
                (_, ingredientIndex) => ingredientIndex !== index,
            ),
        );
    };

    return (
        <AppLayout
            title={title}
            subtitle={description}
            actions={
                <RouterLink
                    href="/recipes"
                    style={{ color: 'inherit', textDecoration: 'none' }}
                >
                    <Button
                        color="inherit"
                        variant="soft"
                        startIcon={<ArrowBackRounded />}
                    >
                        {t('recipe.back_to_recipes')}
                    </Button>
                </RouterLink>
            }
        >
            <Head title={title} />

            <Stack spacing={2}>
                <Card>
                    <CardHeader
                        avatar={<RestaurantMenuOutlined color="primary" />}
                        title={title}
                        subheader={description}
                    />
                    <CardContent>
                        <Stack
                            component="form"
                            spacing={2}
                            onSubmit={(event: FormEvent) => {
                                event.preventDefault();

                                if (recipe) {
                                    form.put(`/recipes/${recipe.id}`);
                                    return;
                                }

                                form.post('/recipes');
                            }}
                        >
                            <Grid container spacing={2}>
                                <Grid size={{ xs: 12, md: 7 }}>
                                    <TextField
                                        required
                                        label={t('recipe.name')}
                                        value={form.data.name}
                                        error={Boolean(form.errors.name)}
                                        helperText={form.errors.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Grid>
                                <Grid size={{ xs: 12, md: 5 }}>
                                    <TextField
                                        required
                                        type="number"
                                        label={t('recipe.cooked_weight')}
                                        value={form.data.cooked_weight}
                                        error={Boolean(
                                            form.errors.cooked_weight,
                                        )}
                                        helperText={
                                            form.errors.cooked_weight ??
                                            t('recipe.cooked_weight_help')
                                        }
                                        slotProps={{
                                            input: {
                                                startAdornment: (
                                                    <InputAdornment position="start">
                                                        <ScaleOutlined />
                                                    </InputAdornment>
                                                ),
                                                endAdornment: (
                                                    <InputAdornment position="end">
                                                        g
                                                    </InputAdornment>
                                                ),
                                            },
                                            htmlInput: {
                                                min: 0.01,
                                                step: 0.01,
                                            },
                                        }}
                                        onChange={(event) =>
                                            form.setData(
                                                'cooked_weight',
                                                parseNumberInput(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                    />
                                </Grid>
                            </Grid>

                            <Divider />

                            <Stack
                                direction={{ xs: 'column', sm: 'row' }}
                                alignItems={{ xs: 'stretch', sm: 'center' }}
                                justifyContent="space-between"
                                spacing={2}
                            >
                                <Box>
                                    <Typography variant="h6">
                                        {t('recipe.ingredients')}
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        color="text.secondary"
                                    >
                                        {t('recipe.ingredients_help')}
                                    </Typography>
                                </Box>
                                <Button
                                    variant="soft"
                                    startIcon={<AddRounded />}
                                    onClick={() => setFoodDialogOpen(true)}
                                >
                                    {t('recipe.create_food')}
                                </Button>
                            </Stack>

                            <Stack spacing={2}>
                                {form.data.ingredients.map(
                                    (ingredient, index) => {
                                        const selectedFood =
                                            ingredient.food_id !== null
                                                ? (foodById.get(
                                                      ingredient.food_id,
                                                  ) ?? null)
                                                : null;

                                        return (
                                            <Paper
                                                key={index}
                                                variant="outlined"
                                                sx={{ p: 1.5 }}
                                            >
                                                <Grid
                                                    container
                                                    spacing={2}
                                                    alignItems="flex-start"
                                                >
                                                    <Grid
                                                        size={{
                                                            xs: 12,
                                                            sm: 8,
                                                        }}
                                                    >
                                                        <Autocomplete
                                                            options={
                                                                foodOptions
                                                            }
                                                            value={selectedFood}
                                                            autoHighlight
                                                            loading={
                                                                foodSearching
                                                            }
                                                            filterSelectedOptions
                                                            getOptionLabel={(
                                                                food,
                                                            ) =>
                                                                food.brand
                                                                    ? `${food.name} · ${food.brand}`
                                                                    : food.name
                                                            }
                                                            isOptionEqualToValue={(
                                                                option,
                                                                value,
                                                            ) =>
                                                                option.id ===
                                                                value.id
                                                            }
                                                            onChange={(
                                                                _,
                                                                food,
                                                            ) => {
                                                                if (food) {
                                                                    setFoodOptions(
                                                                        (
                                                                            current,
                                                                        ) => {
                                                                            const options =
                                                                                new Map(
                                                                                    current.map(
                                                                                        (
                                                                                            item,
                                                                                        ) => [
                                                                                            item.id,
                                                                                            item,
                                                                                        ],
                                                                                    ),
                                                                                );
                                                                            options.set(
                                                                                food.id,
                                                                                food,
                                                                            );

                                                                            return Array.from(
                                                                                options.values(),
                                                                            );
                                                                        },
                                                                    );
                                                                }

                                                                updateIngredient(
                                                                    index,
                                                                    {
                                                                        food_id:
                                                                            food?.id ??
                                                                            null,
                                                                        amount:
                                                                            food?.nutrition_basis_amount ??
                                                                            ingredient.amount,
                                                                    },
                                                                );
                                                            }}
                                                            onInputChange={(
                                                                _,
                                                                value,
                                                                reason,
                                                            ) => {
                                                                if (
                                                                    reason ===
                                                                    'input'
                                                                ) {
                                                                    setFoodSearch(
                                                                        value,
                                                                    );
                                                                }
                                                            }}
                                                            noOptionsText={
                                                                foodSearch.trim()
                                                                    .length < 2
                                                                    ? t(
                                                                          'recipe.type_to_search',
                                                                      )
                                                                    : t(
                                                                          'recipe.no_foods_found',
                                                                      )
                                                            }
                                                            renderInput={(
                                                                params,
                                                            ) => (
                                                                <TextField
                                                                    {...params}
                                                                    label={t(
                                                                        'recipe.search_food',
                                                                    )}
                                                                    placeholder={t(
                                                                        'recipe.search_placeholder',
                                                                    )}
                                                                    error={Boolean(
                                                                        fieldErrors[
                                                                            `ingredients.${index}.food_id`
                                                                        ],
                                                                    )}
                                                                    helperText={
                                                                        fieldErrors[
                                                                            `ingredients.${index}.food_id`
                                                                        ]
                                                                    }
                                                                />
                                                            )}
                                                            renderOption={(
                                                                props,
                                                                food,
                                                            ) => (
                                                                <Box
                                                                    component="li"
                                                                    {...props}
                                                                >
                                                                    <Box
                                                                        sx={{
                                                                            minWidth: 0,
                                                                            flex: 1,
                                                                        }}
                                                                    >
                                                                        <Typography
                                                                            variant="subtitle2"
                                                                            noWrap
                                                                        >
                                                                            {
                                                                                food.name
                                                                            }
                                                                        </Typography>
                                                                        <Typography
                                                                            variant="caption"
                                                                            color="text.secondary"
                                                                        >
                                                                            {formatNumber(
                                                                                food.calories,
                                                                            )}{' '}
                                                                            kcal
                                                                            /
                                                                            {formatNumber(
                                                                                food.nutrition_basis_amount,
                                                                            )}{' '}
                                                                            {
                                                                                food.nutrition_basis_unit
                                                                            }
                                                                        </Typography>
                                                                    </Box>
                                                                </Box>
                                                            )}
                                                        />
                                                    </Grid>
                                                    <Grid
                                                        size={{
                                                            xs: 10,
                                                            sm: 3,
                                                        }}
                                                    >
                                                        <TextField
                                                            type="number"
                                                            label={t(
                                                                'recipe.ingredient_amount',
                                                            )}
                                                            value={
                                                                ingredient.amount
                                                            }
                                                            error={Boolean(
                                                                fieldErrors[
                                                                    `ingredients.${index}.amount`
                                                                ],
                                                            )}
                                                            helperText={
                                                                fieldErrors[
                                                                    `ingredients.${index}.amount`
                                                                ]
                                                            }
                                                            slotProps={{
                                                                input: {
                                                                    endAdornment:
                                                                        (
                                                                            <InputAdornment position="end">
                                                                                {foodById.get(
                                                                                    ingredient.food_id ??
                                                                                        0,
                                                                                )
                                                                                    ?.nutrition_basis_unit ??
                                                                                    'g'}
                                                                            </InputAdornment>
                                                                        ),
                                                                },
                                                                htmlInput: {
                                                                    min: 0.01,
                                                                    step: 0.01,
                                                                },
                                                            }}
                                                            onChange={(event) =>
                                                                updateIngredient(
                                                                    index,
                                                                    {
                                                                        amount: parseNumberInput(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </Grid>
                                                    <Grid
                                                        size={{
                                                            xs: 2,
                                                            sm: 1,
                                                        }}
                                                        sx={{
                                                            display: 'flex',
                                                            justifyContent:
                                                                'flex-end',
                                                        }}
                                                    >
                                                        <IconButton
                                                            color="error"
                                                            disabled={
                                                                form.data
                                                                    .ingredients
                                                                    .length === 1
                                                            }
                                                            aria-label={t(
                                                                'recipe.remove_ingredient',
                                                            )}
                                                            onClick={() =>
                                                                removeIngredient(
                                                                    index,
                                                                )
                                                            }
                                                            sx={{
                                                                width: 54,
                                                                height: 54,
                                                            }}
                                                        >
                                                            <DeleteOutlineRounded />
                                                        </IconButton>
                                                    </Grid>
                                                </Grid>
                                            </Paper>
                                        );
                                    },
                                )}
                            </Stack>

                            {form.errors.ingredients && (
                                <Alert severity="error">
                                    {form.errors.ingredients}
                                </Alert>
                            )}

                            <Button
                                variant="outlined"
                                startIcon={<AddRounded />}
                                onClick={() =>
                                    form.setData('ingredients', [
                                        ...form.data.ingredients,
                                        emptyIngredient(),
                                    ])
                                }
                                sx={{ alignSelf: 'flex-start' }}
                            >
                                {t('recipe.add_ingredient')}
                            </Button>

                            <NutritionPreview
                                totals={totals}
                                perHundred={nutritionPerHundred}
                            />

                            <Box
                                sx={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                }}
                            >
                                <Button
                                    type="submit"
                                    variant="contained"
                                    size="large"
                                    disabled={form.processing}
                                    startIcon={
                                        form.processing ? (
                                            <CircularProgress
                                                size={18}
                                                color="inherit"
                                            />
                                        ) : (
                                            <RestaurantMenuOutlined />
                                        )
                                    }
                                >
                                    {editing
                                        ? t('recipe.save_changes')
                                        : t('recipe.save')}
                                </Button>
                            </Box>
                        </Stack>
                    </CardContent>
                </Card>

            </Stack>

            <CreateFoodDialog
                open={foodDialogOpen}
                onClose={() => setFoodDialogOpen(false)}
            />
        </AppLayout>
    );
}

function NutritionPreview({
    totals,
    perHundred,
}: {
    totals: Record<string, number>;
    perHundred: Record<string, number>;
}) {
    const { t } = useTranslation();
    const nutrients = [
        ['calories', t('common.calories'), 'kcal'],
        ['protein', t('common.protein'), 'g'],
        ['carbohydrates', t('common.carbs'), 'g'],
        ['fat', t('common.fat'), 'g'],
        ['fibre', t('common.fibre'), 'g'],
    ] as const;

    return (
        <Paper variant="outlined" sx={{ overflow: 'hidden' }}>
            <Box sx={{ px: 2, py: 1.5, bgcolor: 'action.hover' }}>
                <Typography variant="subtitle2">
                    {t('recipe.nutrition_preview')}
                </Typography>
            </Box>
            <Grid container>
                {nutrients.map(([key, label, unit]) => (
                    <Grid key={key} size={{ xs: 6, sm: 2.4 }}>
                        <Box sx={{ p: 2 }}>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                            >
                                {label}
                            </Typography>
                            <Typography variant="h6">
                                {formatNumber(perHundred[key] ?? 0, 1)} {unit}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                            >
                                {t('recipe.total_value', {
                                    value: formatNumber(totals[key] ?? 0, 1),
                                    unit,
                                })}
                            </Typography>
                        </Box>
                    </Grid>
                ))}
            </Grid>
        </Paper>
    );
}

function CreateFoodDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
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
        barcode: '',
        calories: 0,
        nutrition_basis_amount: 100,
        nutrition_basis_unit: 'g',
        protein: 0,
        carbohydrates: 0,
        fat: 0,
        fibre: 0,
    });
    const nutrients = [
        ['calories', t('common.calories')],
        ['protein', t('common.protein')],
        ['carbohydrates', t('common.carbs')],
        ['fat', t('common.fat')],
        ['fibre', t('common.fibre')],
    ] as const;

    const handleClose = () => {
        if (form.processing) return;
        form.clearErrors();
        onClose();
    };

    return (
        <Dialog
            open={open}
            onClose={handleClose}
            maxWidth="sm"
            fullWidth
        >
            <Box
                component="form"
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    form.post('/foods', {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            onClose();
                        },
                    });
                }}
            >
                <DialogTitle>{t('recipe.create_food_title')}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2}>
                        <Grid container spacing={2}>
                            <Grid size={{ xs: 12, sm: 7 }}>
                                <TextField
                                    required
                                    label={t('food.food_name')}
                                    value={form.data.name}
                                    error={Boolean(form.errors.name)}
                                    helperText={form.errors.name}
                                    onChange={(event) =>
                                        form.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 5 }}>
                                <TextField
                                    label={t('common.brand')}
                                    value={form.data.brand}
                                    error={Boolean(form.errors.brand)}
                                    helperText={form.errors.brand}
                                    onChange={(event) =>
                                        form.setData(
                                            'brand',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Grid>
                        </Grid>

                        <Grid container spacing={2}>
                            <Grid size={{ xs: 8, sm: 4 }}>
                                <TextField
                                    required
                                    type="number"
                                    label={t(
                                        'food.nutrition_basis_amount',
                                    )}
                                    value={
                                        form.data.nutrition_basis_amount
                                    }
                                    error={Boolean(
                                        form.errors
                                            .nutrition_basis_amount,
                                    )}
                                    helperText={
                                        form.errors
                                            .nutrition_basis_amount
                                    }
                                    slotProps={{
                                        htmlInput: {
                                            min: 0.01,
                                            step: 0.01,
                                        },
                                    }}
                                    onChange={(event) =>
                                        form.setData(
                                            'nutrition_basis_amount',
                                            parseNumberInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                />
                            </Grid>
                            <Grid size={{ xs: 4, sm: 2 }}>
                                <TextField
                                    select
                                    label={t('common.unit')}
                                    value={
                                        form.data.nutrition_basis_unit
                                    }
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
                                    Number(
                                        form.data.nutrition_basis_amount,
                                    ) || 0,
                                unit: form.data.nutrition_basis_unit,
                            })}
                        </Typography>
                        <Grid container spacing={2}>
                            {nutrients.map(([key, label]) => (
                                <Grid
                                    key={key}
                                    size={{ xs: 6, sm: 4 }}
                                >
                                    <TextField
                                        type="number"
                                        label={label}
                                        value={form.data[key]}
                                        error={Boolean(form.errors[key])}
                                        helperText={form.errors[key]}
                                        slotProps={{
                                            htmlInput: {
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
                        <Alert severity="info">
                            {t('recipe.create_food_help')}
                        </Alert>
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button color="inherit" onClick={handleClose}>
                        {t('recipe.cancel')}
                    </Button>
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
                        {t('recipe.create_and_select')}
                    </Button>
                </DialogActions>
            </Box>
        </Dialog>
    );
}
