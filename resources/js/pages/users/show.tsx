import { Head, router } from '@inertiajs/react';
import CheckRounded from '@mui/icons-material/CheckRounded';
import FavoriteBorderRounded from '@mui/icons-material/FavoriteBorderRounded';
import FavoriteRounded from '@mui/icons-material/FavoriteRounded';
import HourglassTopRounded from '@mui/icons-material/HourglassTopRounded';
import PersonAddAltRounded from '@mui/icons-material/PersonAddAltRounded';
import PersonRemoveRounded from '@mui/icons-material/PersonRemoveRounded';
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
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';

type FriendshipState = 'self' | 'none' | 'outgoing' | 'incoming' | 'friends';

type FriendRecipe = {
    id: number;
    food_id: number | null;
    name: string;
    cooked_weight: number;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
    is_favourite: boolean;
    ingredients: {
        id: number;
        name: string;
        amount: number;
        unit: string;
    }[];
};

export default function UserShow({
    profileUser,
    friendship,
    canViewRecipes,
    recipes,
}: {
    profileUser: { id: number; name: string; username: string };
    friendship: {
        friendship_id: number | null;
        friendship_state: FriendshipState;
    };
    canViewRecipes: boolean;
    recipes: FriendRecipe[];
}) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={profileUser.name} />
            <Stack spacing={2}>
                <Paper variant="outlined" sx={{ p: 3 }}>
                    <Stack
                        direction={{ xs: 'column', sm: 'row' }}
                        alignItems={{ xs: 'flex-start', sm: 'center' }}
                        spacing={2}
                    >
                        <Box sx={{ flex: 1 }}>
                            <Typography variant="h5">
                                {profileUser.name}
                            </Typography>
                            <Typography color="text.secondary">
                                @{profileUser.username}
                            </Typography>
                        </Box>
                        {friendship.friendship_state !== 'self' && (
                            <ProfileAction
                                state={friendship.friendship_state}
                                friendshipId={friendship.friendship_id}
                                username={profileUser.username}
                            />
                        )}
                    </Stack>
                </Paper>

                {!canViewRecipes ? (
                    <Paper
                        variant="outlined"
                        sx={{ p: 4, textAlign: 'center', borderStyle: 'dashed' }}
                    >
                        <Typography variant="h6">
                            {t('social.recipes_private')}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {t('social.recipes_private_description')}
                        </Typography>
                    </Paper>
                ) : recipes.length === 0 ? (
                    <Paper
                        variant="outlined"
                        sx={{ p: 4, textAlign: 'center', borderStyle: 'dashed' }}
                    >
                        <Typography variant="h6">
                            {t('social.no_shared_recipes')}
                        </Typography>
                    </Paper>
                ) : (
                    <>
                        <Typography variant="h5">
                            {t('social.user_recipes', {
                                username: profileUser.username,
                            })}
                        </Typography>
                        <Grid container spacing={2}>
                            {recipes.map((recipe) => (
                                <Grid
                                    key={recipe.id}
                                    size={{ xs: 12, md: 6 }}
                                >
                                    <RecipeCard recipe={recipe} />
                                </Grid>
                            ))}
                        </Grid>
                    </>
                )}
            </Stack>
        </AppLayout>
    );
}

function ProfileAction({
    state,
    friendshipId,
    username,
}: {
    state: FriendshipState;
    friendshipId: number | null;
    username: string;
}) {
    const { t } = useTranslation();
    const [removeDialogOpen, setRemoveDialogOpen] = useState(false);
    const [removeProcessing, setRemoveProcessing] = useState(false);

    if (state === 'friends') {
        return (
            <>
                <Button
                    color="error"
                    variant="outlined"
                    startIcon={<PersonRemoveRounded />}
                    onClick={() => setRemoveDialogOpen(true)}
                >
                    {t('social.remove_friend')}
                </Button>
                <Dialog
                    fullWidth
                    maxWidth="xs"
                    open={removeDialogOpen}
                    onClose={() => {
                        if (!removeProcessing) setRemoveDialogOpen(false);
                    }}
                >
                    <DialogTitle>
                        {t('social.remove_friend_title', { username })}
                    </DialogTitle>
                    <DialogContent>
                        <Typography color="text.secondary">
                            {t('social.remove_friend_warning')}
                        </Typography>
                    </DialogContent>
                    <DialogActions>
                        <Button
                            color="inherit"
                            disabled={removeProcessing}
                            onClick={() => setRemoveDialogOpen(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            color="error"
                            variant="contained"
                            disabled={removeProcessing}
                            onClick={() =>
                                router.delete(
                                    `/friendships/${friendshipId}`,
                                    {
                                        preserveScroll: true,
                                        onStart: () =>
                                            setRemoveProcessing(true),
                                        onFinish: () => {
                                            setRemoveProcessing(false);
                                            setRemoveDialogOpen(false);
                                        },
                                    },
                                )
                            }
                        >
                            {t('social.remove_friend')}
                        </Button>
                    </DialogActions>
                </Dialog>
            </>
        );
    }

    if (state === 'incoming') {
        return (
            <Stack direction="row" spacing={1}>
                <Button
                    variant="contained"
                    startIcon={<CheckRounded />}
                    onClick={() =>
                        router.put(`/friendships/${friendshipId}/accept`)
                    }
                >
                    {t('social.accept_request')}
                </Button>
                <Button
                    color="inherit"
                    onClick={() =>
                        router.delete(`/friendships/${friendshipId}`)
                    }
                >
                    {t('social.decline')}
                </Button>
            </Stack>
        );
    }

    if (state === 'outgoing') {
        return (
            <Button
                color="inherit"
                variant="outlined"
                startIcon={<HourglassTopRounded />}
                onClick={() =>
                    router.delete(`/friendships/${friendshipId}`)
                }
            >
                {t('social.cancel_request')}
            </Button>
        );
    }

    return (
        <Button
            variant="contained"
            startIcon={<PersonAddAltRounded />}
            onClick={() =>
                router.post(`/users/${username}/friend-request`)
            }
        >
            {t('social.add_friend')}
        </Button>
    );
}

function RecipeCard({ recipe }: { recipe: FriendRecipe }) {
    const { t } = useTranslation();
    const [usingRecipe, setUsingRecipe] = useState(false);
    const ingredientsByAmount = [...recipe.ingredients].sort(
        (first, second) => second.amount - first.amount,
    );
    const openInFriendRecipes = () =>
        router.visit(`/recipes?tab=friends&recipe=${recipe.id}`);
    const useRecipe = () => {
        if (!recipe.food_id || recipe.is_favourite) {
            openInFriendRecipes();
            return;
        }

        router.post(
            `/foods/${recipe.food_id}/favourite`,
            {},
            {
                preserveScroll: true,
                onStart: () => setUsingRecipe(true),
                onSuccess: openInFriendRecipes,
                onFinish: () => setUsingRecipe(false),
            },
        );
    };

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
                        spacing={1}
                    >
                        <Box sx={{ minWidth: 0, flex: 1 }}>
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
                        {recipe.food_id && (
                            <IconButton
                                color={
                                    recipe.is_favourite
                                        ? 'primary'
                                        : 'default'
                                }
                                aria-label={
                                    recipe.is_favourite
                                        ? t('food.remove_favourite', {
                                              food: recipe.name,
                                          })
                                        : t('food.add_favourite', {
                                              food: recipe.name,
                                          })
                                }
                                onClick={(event) => {
                                    event.stopPropagation();
                                    router.post(
                                        `/foods/${recipe.food_id}/favourite`,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                                onKeyDown={(event) =>
                                    event.stopPropagation()
                                }
                            >
                                {recipe.is_favourite ? (
                                    <FavoriteRounded />
                                ) : (
                                    <FavoriteBorderRounded />
                                )}
                            </IconButton>
                        )}
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
                        flexWrap="wrap"
                        gap={0.75}
                        sx={{
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
                    <Button
                        variant="soft"
                        disabled={usingRecipe}
                        onClick={(event) => {
                            event.stopPropagation();
                            useRecipe();
                        }}
                        onKeyDown={(event) => event.stopPropagation()}
                    >
                        {t('social.find_in_foods')}
                    </Button>
                </Stack>
            </CardContent>
        </Card>
    );
}
