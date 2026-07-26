import { Head, router } from '@inertiajs/react';
import AddRounded from '@mui/icons-material/AddRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import EditOutlined from '@mui/icons-material/EditOutlined';
import RestaurantMenuOutlined from '@mui/icons-material/RestaurantMenuOutlined';
import SearchRounded from '@mui/icons-material/SearchRounded';
import {
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Grid,
    InputAdornment,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useDeferredValue, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RouterLink } from '@/components/router-link';
import { AppLayout } from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';

type Recipe = {
    id: number;
    food_id: number | null;
    name: string;
    cooked_weight: number;
    total_calories: number;
    total_protein: number;
    total_carbohydrates: number;
    total_fat: number;
    total_fibre: number;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
    ingredients: {
        id: number;
        food_id: number | null;
        name: string;
        amount: number;
    }[];
};

const normalizeSearch = (value: string) =>
    value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase()
        .trim();

export default function RecipesIndex({
    recipes,
}: {
    recipes: Recipe[];
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const [recipeToDelete, setRecipeToDelete] = useState<Recipe | null>(null);
    const filteredRecipes = useMemo(() => {
        const query = normalizeSearch(deferredSearch);

        if (!query) return recipes;

        return recipes.filter((recipe) =>
            [recipe.name, ...recipe.ingredients.map(({ name }) => name)]
                .map(normalizeSearch)
                .some((value) => value.includes(query)),
        );
    }, [deferredSearch, recipes]);

    return (
        <AppLayout
            title={t('recipe.title')}
            subtitle={t('recipe.saved_description')}
        >
            <Head title={t('recipe.title')} />

            <Stack spacing={3}>
                {recipes.length > 0 && (
                    <Stack
                        direction={{ xs: 'column', sm: 'row' }}
                        alignItems={{ sm: 'center' }}
                        justifyContent="space-between"
                        spacing={2}
                    >
                        <TextField
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            placeholder={t(
                                'recipe.search_placeholder_recipes',
                            )}
                            aria-label={t('recipe.search_recipes')}
                            slotProps={{
                                input: {
                                    startAdornment: (
                                        <InputAdornment position="start">
                                            <SearchRounded />
                                        </InputAdornment>
                                    ),
                                },
                            }}
                            sx={{ width: 1, maxWidth: 560 }}
                        />
                        <RouterLink
                            href="/recipes/create"
                            style={{
                                display: 'block',
                                flexShrink: 0,
                                textDecoration: 'none',
                            }}
                        >
                            <Button
                                variant="contained"
                                startIcon={<AddRounded />}
                                sx={{ width: { xs: '100%', sm: 'auto' } }}
                            >
                                {t('recipe.add_recipe')}
                            </Button>
                        </RouterLink>
                    </Stack>
                )}

                {recipes.length === 0 ? (
                    <EmptyRecipes />
                ) : filteredRecipes.length === 0 ? (
                    <Paper
                        variant="outlined"
                        sx={{
                            p: 4,
                            textAlign: 'center',
                            borderStyle: 'dashed',
                        }}
                    >
                        <SearchRounded
                            color="disabled"
                            sx={{ fontSize: 40 }}
                        />
                        <Typography variant="h6" sx={{ mt: 1 }}>
                            {t('recipe.no_results')}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {t('recipe.no_results_description')}
                        </Typography>
                    </Paper>
                ) : (
                    <Grid container spacing={2}>
                        {filteredRecipes.map((recipe) => (
                            <Grid key={recipe.id} size={{ xs: 12, md: 6 }}>
                                <RecipeCard
                                    recipe={recipe}
                                    onDelete={() =>
                                        setRecipeToDelete(recipe)
                                    }
                                />
                            </Grid>
                        ))}
                    </Grid>
                )}
            </Stack>

            <Dialog
                open={Boolean(recipeToDelete)}
                onClose={() => setRecipeToDelete(null)}
                maxWidth="xs"
                fullWidth
            >
                <DialogTitle>{t('recipe.delete_title')}</DialogTitle>
                <DialogContent>
                    <Typography color="text.secondary">
                        {t('recipe.delete_description', {
                            recipe: recipeToDelete?.name,
                        })}
                    </Typography>
                </DialogContent>
                <DialogActions>
                    <Button
                        color="inherit"
                        onClick={() => setRecipeToDelete(null)}
                    >
                        {t('recipe.cancel')}
                    </Button>
                    <Button
                        color="error"
                        variant="contained"
                        onClick={() => {
                            if (!recipeToDelete) return;

                            router.delete(`/recipes/${recipeToDelete.id}`, {
                                preserveScroll: true,
                                onFinish: () => setRecipeToDelete(null),
                            });
                        }}
                    >
                        {t('recipe.delete')}
                    </Button>
                </DialogActions>
            </Dialog>
        </AppLayout>
    );
}

function EmptyRecipes() {
    const { t } = useTranslation();

    return (
        <Paper
            variant="outlined"
            sx={{
                p: { xs: 4, sm: 6 },
                textAlign: 'center',
                borderStyle: 'dashed',
            }}
        >
            <RestaurantMenuOutlined color="primary" sx={{ fontSize: 48 }} />
            <Typography variant="h5" sx={{ mt: 1.5 }}>
                {t('recipe.empty_title')}
            </Typography>
            <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mt: 0.75, mb: 2.5 }}
            >
                {t('recipe.empty_description')}
            </Typography>
            <RouterLink
                href="/recipes/create"
                style={{ textDecoration: 'none' }}
            >
                <Button variant="contained" startIcon={<AddRounded />}>
                    {t('recipe.add_recipe')}
                </Button>
            </RouterLink>
        </Paper>
    );
}

function RecipeCard({
    recipe,
    onDelete,
}: {
    recipe: Recipe;
    onDelete: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Card sx={{ height: 1 }}>
            <CardContent>
                <Stack spacing={2}>
                    <Stack
                        direction="row"
                        alignItems="flex-start"
                        justifyContent="space-between"
                        spacing={2}
                    >
                        <Box sx={{ minWidth: 0 }}>
                            <Typography variant="h6" noWrap>
                                {recipe.name}
                            </Typography>
                            <Typography
                                variant="body2"
                                color="text.secondary"
                            >
                                {t('recipe.yield_summary', {
                                    weight: formatNumber(
                                        recipe.cooked_weight,
                                        0,
                                    ),
                                    count: recipe.ingredients.length,
                                })}
                            </Typography>
                        </Box>
                        <Chip
                            color="primary"
                            label={`${formatNumber(recipe.calories)} kcal`}
                        />
                    </Stack>

                    <Typography variant="body2" color="text.secondary">
                        {t('recipe.macro_summary', {
                            protein: formatNumber(recipe.protein, 1),
                            carbs: formatNumber(recipe.carbohydrates, 1),
                            fat: formatNumber(recipe.fat, 1),
                            fibre: formatNumber(recipe.fibre, 1),
                        })}
                    </Typography>

                    <Stack direction="row" flexWrap="wrap" gap={0.75}>
                        {recipe.ingredients.map((ingredient) => (
                            <Chip
                                key={ingredient.id}
                                size="small"
                                variant="outlined"
                                label={`${ingredient.name} · ${formatNumber(
                                    ingredient.amount,
                                    0,
                                )} g`}
                            />
                        ))}
                    </Stack>

                    <Box
                        sx={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            alignItems: 'center',
                            gap: 1,
                        }}
                    >
                        <RouterLink
                            href={`/recipes/${recipe.id}/edit`}
                            style={{ textDecoration: 'none' }}
                        >
                            <Button
                                variant="soft"
                                startIcon={<EditOutlined />}
                            >
                                {t('recipe.edit')}
                            </Button>
                        </RouterLink>
                        <Button
                            variant="soft"
                            color="error"
                            startIcon={<DeleteOutlineRounded />}
                            onClick={onDelete}
                        >
                            {t('recipe.delete_action')}
                        </Button>
                    </Box>
                </Stack>
            </CardContent>
        </Card>
    );
}
