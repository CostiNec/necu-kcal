import { Head, router } from '@inertiajs/react';
import AddRounded from '@mui/icons-material/AddRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import EditOutlined from '@mui/icons-material/EditOutlined';
import RemoveCircleOutlineRounded from '@mui/icons-material/RemoveCircleOutlineRounded';
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
    IconButton,
    InputAdornment,
    Paper,
    Stack,
    Tab,
    Tabs,
    TextField,
    Tooltip,
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
    owner: { name: string; username: string };
    is_owner: boolean;
    ingredients: {
        id: number;
        food_id: number | null;
        name: string;
        amount: number;
        unit: 'g' | 'ml';
    }[];
};
type RecipeTab = 'mine' | 'friends';

const normalizeSearch = (value: string) =>
    value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase()
        .trim();

export default function RecipesIndex({
    recipes,
    friendRecipes,
    filters,
}: {
    recipes: Recipe[];
    friendRecipes: Recipe[];
    filters: { tab: RecipeTab; recipe: number | null };
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<RecipeTab>(filters.tab);
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const [recipeToDelete, setRecipeToDelete] = useState<Recipe | null>(null);
    const [recipeToRemove, setRecipeToRemove] = useState<Recipe | null>(null);
    const [removingFriendRecipe, setRemovingFriendRecipe] = useState(false);
    const activeRecipes =
        activeTab === 'mine' ? recipes : friendRecipes;
    const filteredRecipes = useMemo(() => {
        const query = normalizeSearch(deferredSearch);

        if (!query) return activeRecipes;

        return activeRecipes.filter((recipe) =>
            [recipe.name, ...recipe.ingredients.map(({ name }) => name)]
                .map(normalizeSearch)
                .some((value) => value.includes(query)),
        );
    }, [activeRecipes, deferredSearch]);

    return (
        <AppLayout
            title={t('recipe.title')}
            subtitle={t('recipe.saved_description')}
        >
            <Head title={t('recipe.title')} />

            <Stack spacing={2}>
                <Tabs
                    value={activeTab}
                    onChange={(_, value: RecipeTab) => {
                        setActiveTab(value);
                        setSearch('');
                    }}
                    aria-label={t('recipe.recipe_lists')}
                >
                    <Tab
                        value="mine"
                        label={`${t('recipe.mine')} (${recipes.length})`}
                    />
                    <Tab
                        value="friends"
                        label={`${t('recipe.friends')} (${friendRecipes.length})`}
                    />
                </Tabs>

                {activeRecipes.length > 0 && (
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
                        {activeTab === 'mine' && (
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
                                    sx={{
                                        width: {
                                            xs: '100%',
                                            sm: 'auto',
                                        },
                                    }}
                                >
                                    {t('recipe.add_recipe')}
                                </Button>
                            </RouterLink>
                        )}
                    </Stack>
                )}

                {activeRecipes.length === 0 ? (
                    activeTab === 'mine' ? (
                        <EmptyRecipes />
                    ) : (
                        <EmptyFriendRecipes />
                    )
                ) : filteredRecipes.length === 0 ? (
                    <Paper
                        variant="outlined"
                        sx={{
                            p: 2,
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
                                    onDelete={
                                        recipe.is_owner
                                            ? () =>
                                                  setRecipeToDelete(
                                                      recipe,
                                                  )
                                            : undefined
                                    }
                                    onRemove={
                                        !recipe.is_owner && recipe.food_id
                                            ? () =>
                                                  setRecipeToRemove(
                                                      recipe,
                                                  )
                                            : undefined
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

            <Dialog
                open={Boolean(recipeToRemove)}
                onClose={() => {
                    if (!removingFriendRecipe) setRecipeToRemove(null);
                }}
                maxWidth="xs"
                fullWidth
            >
                <DialogTitle>{t('recipe.remove_saved_title')}</DialogTitle>
                <DialogContent>
                    <Typography color="text.secondary">
                        {t('recipe.remove_saved_description', {
                            recipe: recipeToRemove?.name,
                            username: recipeToRemove?.owner.username,
                        })}
                    </Typography>
                </DialogContent>
                <DialogActions>
                    <Button
                        color="inherit"
                        disabled={removingFriendRecipe}
                        onClick={() => setRecipeToRemove(null)}
                    >
                        {t('recipe.cancel')}
                    </Button>
                    <Button
                        color="error"
                        variant="contained"
                        disabled={removingFriendRecipe}
                        onClick={() => {
                            if (!recipeToRemove?.food_id) return;

                            router.delete(
                                `/foods/${recipeToRemove.food_id}/favourite`,
                                {
                                    preserveScroll: true,
                                    onStart: () =>
                                        setRemovingFriendRecipe(true),
                                    onFinish: () => {
                                        setRemovingFriendRecipe(false);
                                        setRecipeToRemove(null);
                                    },
                                },
                            );
                        }}
                    >
                        {t('recipe.remove_from_list')}
                    </Button>
                </DialogActions>
            </Dialog>
        </AppLayout>
    );
}

function EmptyFriendRecipes() {
    const { t } = useTranslation();

    return (
        <Paper
            variant="outlined"
            sx={{
                p: 2,
                textAlign: 'center',
                borderStyle: 'dashed',
            }}
        >
            <RestaurantMenuOutlined color="primary" sx={{ fontSize: 48 }} />
            <Typography variant="h5" sx={{ mt: 2 }}>
                {t('recipe.empty_friends_title')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                {t('recipe.empty_friends_description')}
            </Typography>
        </Paper>
    );
}

function EmptyRecipes() {
    const { t } = useTranslation();

    return (
        <Paper
            variant="outlined"
            sx={{
                p: 2,
                textAlign: 'center',
                borderStyle: 'dashed',
            }}
        >
            <RestaurantMenuOutlined color="primary" sx={{ fontSize: 48 }} />
            <Typography variant="h5" sx={{ mt: 2 }}>
                {t('recipe.empty_title')}
            </Typography>
            <Typography
                variant="body2"
                color="text.secondary"
                sx={{ my: 2 }}
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
    onRemove,
}: {
    recipe: Recipe;
    onDelete?: () => void;
    onRemove?: () => void;
}) {
    const { t } = useTranslation();
    const ingredientsByAmount = [...recipe.ingredients].sort(
        (first, second) => second.amount - first.amount,
    );

    return (
        <Card
            role="link"
            tabIndex={0}
            aria-label={recipe.name}
            onClick={() => router.visit(`/recipes/${recipe.id}`)}
            onKeyDown={(event) => {
                if (
                    event.currentTarget !== event.target ||
                    event.key !== 'Enter'
                ) {
                    return;
                }

                event.preventDefault();
                router.visit(`/recipes/${recipe.id}`);
            }}
            sx={{
                height: 1,
                cursor: 'pointer',
                touchAction: 'manipulation',
                WebkitTapHighlightColor: 'transparent',
                transition: (theme) =>
                    theme.transitions.create(
                        ['transform', 'box-shadow', 'background-color'],
                        { duration: theme.transitions.duration.shorter },
                    ),
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
            }}
        >
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
                                {!recipe.is_owner && (
                                    <>
                                        {t('recipe.by_author_label')}{' '}
                                        <RouterLink
                                            href={`/users/${recipe.owner.username}`}
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                            onKeyDown={(event) =>
                                                event.stopPropagation()
                                            }
                                            style={{
                                                color: 'inherit',
                                                fontWeight: 600,
                                                textDecoration: 'underline',
                                                textUnderlineOffset: 2,
                                            }}
                                        >
                                            @{recipe.owner.username}
                                        </RouterLink>
                                        {' · '}
                                    </>
                                )}
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

                    <Stack
                        direction="row"
                        alignItems="flex-start"
                        spacing={1}
                    >
                        <Stack
                            direction="row"
                            flexWrap="wrap"
                            gap={0.75}
                            sx={{
                                flex: 1,
                                minWidth: 0,
                                maxHeight: 54,
                                overflow: 'hidden',
                            }}
                        >
                            {ingredientsByAmount.map((ingredient) => (
                                <Chip
                                    key={ingredient.id}
                                    size="small"
                                    variant="outlined"
                                    label={`${ingredient.name} · ${formatNumber(
                                        ingredient.amount,
                                        0,
                                    )} ${ingredient.unit}`}
                                />
                            ))}
                        </Stack>

                        {!recipe.is_owner && recipe.food_id && onRemove && (
                            <Tooltip title={t('recipe.remove_from_list')}>
                                <IconButton
                                    size="small"
                                    aria-label={t(
                                        'recipe.remove_from_list_label',
                                        { recipe: recipe.name },
                                    )}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onRemove();
                                    }}
                                    onKeyDown={(event) =>
                                        event.stopPropagation()
                                    }
                                    sx={{
                                        flexShrink: 0,
                                        color: 'text.secondary',
                                        opacity: 0.72,
                                        '&:hover': {
                                            color: 'error.main',
                                            bgcolor: 'error.lighter',
                                            opacity: 1,
                                        },
                                    }}
                                >
                                    <RemoveCircleOutlineRounded fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        )}
                    </Stack>

                    {recipe.is_owner && onDelete && (
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
                                onClick={(event) =>
                                    event.stopPropagation()
                                }
                                onKeyDown={(event) =>
                                    event.stopPropagation()
                                }
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
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onDelete();
                                }}
                                onKeyDown={(event) =>
                                    event.stopPropagation()
                                }
                            >
                                {t('recipe.delete_action')}
                            </Button>
                        </Box>
                    )}
                </Stack>
            </CardContent>
        </Card>
    );
}
