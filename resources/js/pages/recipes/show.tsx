import { Head, useForm } from '@inertiajs/react';
import ChatBubbleOutlineRounded from '@mui/icons-material/ChatBubbleOutlineRounded';
import CheckRounded from '@mui/icons-material/CheckRounded';
import CloseRounded from '@mui/icons-material/CloseRounded';
import EditOutlined from '@mui/icons-material/EditOutlined';
import RestaurantMenuOutlined from '@mui/icons-material/RestaurantMenuOutlined';
import SendRounded from '@mui/icons-material/SendRounded';
import {
    Avatar,
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Divider,
    Grid,
    IconButton,
    Paper,
    Stack,
    TextField,
    Tooltip,
    Typography,
} from '@mui/material';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { RouterLink } from '@/components/router-link';
import {
    RecipeReactions,
    type RecipeReactionSummary,
} from '@/components/recipe-reactions';
import { AppLayout } from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';

type RecipeComment = {
    id: number;
    body: string;
    created_at: string;
    can_edit: boolean;
    user: {
        id: number;
        name: string;
        username: string;
    };
};

type Recipe = RecipeReactionSummary & {
    id: number;
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
    owner: {
        id: number;
        name: string;
        username: string;
    };
    is_owner: boolean;
    ingredients: {
        id: number;
        name: string;
        amount: number;
        unit: 'g' | 'ml';
    }[];
    comments: RecipeComment[];
};

const nutrients = [
    {
        key: 'calories',
        totalKey: 'total_calories',
        unit: 'kcal',
    },
    { key: 'protein', totalKey: 'total_protein', unit: 'g' },
    {
        key: 'carbohydrates',
        totalKey: 'total_carbohydrates',
        unit: 'g',
    },
    { key: 'fat', totalKey: 'total_fat', unit: 'g' },
    { key: 'fibre', totalKey: 'total_fibre', unit: 'g' },
] as const;

export default function RecipeShow({ recipe }: { recipe: Recipe }) {
    const { t, i18n } = useTranslation();
    const form = useForm({ body: '' });

    const submitComment = (event: FormEvent) => {
        event.preventDefault();

        form.post(`/recipes/${recipe.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout
            back={{
                href: recipe.is_owner
                    ? '/recipes'
                    : `/users/${recipe.owner.username}`,
                label: recipe.is_owner
                    ? t('recipe.back_to_recipes')
                    : `@${recipe.owner.username}`,
                useHistory: true,
            }}
            actions={
                recipe.is_owner ? (
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
                ) : undefined
            }
        >
            <Head title={recipe.name} />

            <Stack spacing={2.5}>
                <Paper variant="outlined" sx={{ p: { xs: 2, sm: 3 } }}>
                    <Stack
                        direction={{ xs: 'column', sm: 'row' }}
                        alignItems={{ xs: 'flex-start', sm: 'center' }}
                        justifyContent="space-between"
                        spacing={2}
                    >
                        <Stack direction="row" alignItems="center" spacing={2}>
                            <Box
                                sx={{
                                    display: 'grid',
                                    placeItems: 'center',
                                    width: 52,
                                    height: 52,
                                    borderRadius: 2,
                                    color: 'primary.main',
                                    bgcolor: 'primary.lighter',
                                }}
                            >
                                <RestaurantMenuOutlined />
                            </Box>
                            <Box>
                                <Typography variant="h5">
                                    {recipe.name}
                                </Typography>
                                <Typography color="text.secondary">
                                    {t('recipe.by_author_label')}{' '}
                                    <RouterLink
                                        href={`/users/${recipe.owner.username}`}
                                        style={{
                                            color: 'inherit',
                                            fontWeight: 600,
                                            textDecoration: 'underline',
                                            textUnderlineOffset: 2,
                                        }}
                                    >
                                        @{recipe.owner.username}
                                    </RouterLink>
                                </Typography>
                            </Box>
                        </Stack>
                        <Stack
                            direction="row"
                            alignItems="center"
                            flexWrap="wrap"
                            gap={1}
                        >
                            <RecipeReactions
                                recipeId={recipe.id}
                                reaction={recipe}
                            />
                            <Chip
                                color="primary"
                                label={`${formatNumber(
                                    recipe.cooked_weight,
                                    0,
                                )} g`}
                            />
                            <Chip
                                variant="outlined"
                                label={t('recipe.ingredient_count', {
                                    count: recipe.ingredients.length,
                                })}
                            />
                        </Stack>
                    </Stack>
                </Paper>

                <Grid container spacing={2}>
                    <Grid size={{ xs: 12, lg: 5 }}>
                        <Card sx={{ height: 1 }}>
                            <CardContent>
                                <Stack spacing={2}>
                                    <Box>
                                        <Typography variant="h6">
                                            {t('recipe.ingredients')}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {t('recipe.ingredient_count', {
                                                count: recipe.ingredients
                                                    .length,
                                            })}
                                        </Typography>
                                    </Box>
                                    <Divider />
                                    <Stack spacing={1}>
                                        {recipe.ingredients.map(
                                            (ingredient, index) => (
                                                <Stack
                                                    key={ingredient.id}
                                                    direction="row"
                                                    alignItems="center"
                                                    justifyContent="space-between"
                                                    spacing={2}
                                                    sx={{ py: 0.75 }}
                                                >
                                                    <Stack
                                                        direction="row"
                                                        alignItems="center"
                                                        spacing={1.5}
                                                        sx={{ minWidth: 0 }}
                                                    >
                                                        <Avatar
                                                            sx={{
                                                                width: 30,
                                                                height: 30,
                                                                fontSize: 13,
                                                                bgcolor:
                                                                    'primary.lighter',
                                                                color: 'primary.dark',
                                                            }}
                                                        >
                                                            {index + 1}
                                                        </Avatar>
                                                        <Typography
                                                            variant="body2"
                                                            fontWeight={600}
                                                        >
                                                            {ingredient.name}
                                                        </Typography>
                                                    </Stack>
                                                    <Chip
                                                        size="small"
                                                        variant="outlined"
                                                        label={`${formatNumber(
                                                            ingredient.amount,
                                                            1,
                                                        )} ${ingredient.unit}`}
                                                    />
                                                </Stack>
                                            ),
                                        )}
                                    </Stack>
                                </Stack>
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid size={{ xs: 12, lg: 7 }}>
                        <Card sx={{ height: 1 }}>
                            <CardContent>
                                <Stack spacing={2}>
                                    <Box>
                                        <Typography variant="h6">
                                            {t('recipe.nutrition')}
                                        </Typography>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {t('recipe.nutrition_per_100')}
                                        </Typography>
                                    </Box>
                                    <Grid container spacing={1.5}>
                                        {nutrients.map(
                                            ({
                                                key,
                                                totalKey,
                                                unit,
                                            }) => (
                                                <Grid
                                                    key={key}
                                                    size={{
                                                        xs: 6,
                                                        sm:
                                                            key === 'calories'
                                                                ? 4
                                                                : 6,
                                                        md:
                                                            key === 'calories'
                                                                ? 4
                                                                : 4,
                                                    }}
                                                >
                                                    <Paper
                                                        variant="outlined"
                                                        sx={{
                                                            p: 1.75,
                                                            height: 1,
                                                        }}
                                                    >
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                        >
                                                            {t(
                                                                `recipe.${key}`,
                                                            )}
                                                        </Typography>
                                                        <Typography
                                                            variant="h5"
                                                            sx={{ my: 0.25 }}
                                                        >
                                                            {formatNumber(
                                                                recipe[key],
                                                                1,
                                                            )}{' '}
                                                            <Typography
                                                                component="span"
                                                                variant="body2"
                                                                color="text.secondary"
                                                            >
                                                                {unit}
                                                            </Typography>
                                                        </Typography>
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                        >
                                                            {t(
                                                                'recipe.full_recipe',
                                                                {
                                                                    value: formatNumber(
                                                                        recipe[
                                                                            totalKey
                                                                        ],
                                                                        1,
                                                                    ),
                                                                    unit,
                                                                },
                                                            )}
                                                        </Typography>
                                                    </Paper>
                                                </Grid>
                                            ),
                                        )}
                                    </Grid>
                                </Stack>
                            </CardContent>
                        </Card>
                    </Grid>
                </Grid>

                <Card>
                    <CardContent>
                        <Stack spacing={2}>
                            <Stack
                                direction="row"
                                alignItems="center"
                                spacing={1}
                            >
                                <ChatBubbleOutlineRounded color="primary" />
                                <Typography variant="h6">
                                    {t('recipe.comments', {
                                        count: recipe.comments.length,
                                    })}
                                </Typography>
                            </Stack>

                            <Box component="form" onSubmit={submitComment}>
                                <Stack
                                    direction={{
                                        xs: 'column',
                                        sm: 'row',
                                    }}
                                    alignItems={{ sm: 'flex-start' }}
                                    spacing={1.5}
                                >
                                    <TextField
                                        fullWidth
                                        multiline
                                        minRows={2}
                                        maxRows={6}
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'recipe.comment_placeholder',
                                        )}
                                        error={Boolean(form.errors.body)}
                                        helperText={
                                            form.errors.body ??
                                            t('recipe.comment_help')
                                        }
                                        slotProps={{
                                            htmlInput: { maxLength: 2000 },
                                        }}
                                    />
                                    <Button
                                        type="submit"
                                        variant="contained"
                                        startIcon={<SendRounded />}
                                        disabled={
                                            form.processing ||
                                            form.data.body.trim() === ''
                                        }
                                        sx={{
                                            minWidth: 150,
                                            width: {
                                                xs: '100%',
                                                sm: 'auto',
                                            },
                                        }}
                                    >
                                        {t('recipe.post_comment')}
                                    </Button>
                                </Stack>
                            </Box>

                            <Divider />

                            {recipe.comments.length === 0 ? (
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ py: 1 }}
                                >
                                    {t('recipe.no_comments')}
                                </Typography>
                            ) : (
                                <Stack spacing={2}>
                                    {recipe.comments.map((comment) => (
                                        <RecipeCommentItem
                                            key={comment.id}
                                            recipeId={recipe.id}
                                            comment={comment}
                                            locale={i18n.language}
                                        />
                                    ))}
                                </Stack>
                            )}
                        </Stack>
                    </CardContent>
                </Card>
            </Stack>
        </AppLayout>
    );
}

function RecipeCommentItem({
    recipeId,
    comment,
    locale,
}: {
    recipeId: number;
    comment: RecipeComment;
    locale: string;
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);
    const form = useForm({ body: comment.body });

    const cancelEditing = () => {
        form.setData('body', comment.body);
        form.clearErrors();
        setEditing(false);
    };

    const submitEdit = (event: FormEvent) => {
        event.preventDefault();

        form.put(`/recipes/${recipeId}/comments/${comment.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <Stack direction="row" alignItems="flex-start" spacing={1.5}>
            <Avatar sx={{ width: 36, height: 36, fontSize: 14 }}>
                {comment.user.name.slice(0, 1).toUpperCase()}
            </Avatar>
            <Box sx={{ minWidth: 0, flex: 1 }}>
                <Stack
                    direction="row"
                    alignItems="flex-start"
                    justifyContent="space-between"
                    spacing={1}
                >
                    <Box sx={{ minWidth: 0 }}>
                        <Stack
                            direction={{ xs: 'column', sm: 'row' }}
                            alignItems={{ sm: 'baseline' }}
                            spacing={{ xs: 0, sm: 1 }}
                        >
                            <Typography variant="subtitle2">
                                {comment.user.name}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                            >
                                @{comment.user.username} ·{' '}
                                {new Intl.DateTimeFormat(locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                }).format(new Date(comment.created_at))}
                            </Typography>
                        </Stack>
                    </Box>
                    {comment.can_edit && !editing && (
                        <Tooltip title={t('recipe.edit_comment')}>
                            <IconButton
                                size="small"
                                aria-label={t('recipe.edit_comment')}
                                onClick={() => setEditing(true)}
                            >
                                <EditOutlined fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    )}
                </Stack>

                {editing ? (
                    <Box
                        component="form"
                        onSubmit={submitEdit}
                        sx={{ mt: 1 }}
                    >
                        <TextField
                            autoFocus
                            fullWidth
                            multiline
                            minRows={2}
                            maxRows={6}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            error={Boolean(form.errors.body)}
                            helperText={
                                form.errors.body ??
                                t('recipe.empty_comment_deletes')
                            }
                            slotProps={{
                                htmlInput: { maxLength: 2000 },
                            }}
                        />
                        <Stack
                            direction="row"
                            justifyContent="flex-end"
                            spacing={0.5}
                            sx={{ mt: 0.5 }}
                        >
                            <Tooltip
                                title={t('recipe.cancel_comment_edit')}
                            >
                                <IconButton
                                    size="small"
                                    aria-label={t(
                                        'recipe.cancel_comment_edit',
                                    )}
                                    onClick={cancelEditing}
                                >
                                    <CloseRounded fontSize="small" />
                                </IconButton>
                            </Tooltip>
                            <Tooltip title={t('recipe.save_comment')}>
                                <span>
                                    <IconButton
                                        type="submit"
                                        size="small"
                                        color="primary"
                                        aria-label={t(
                                            'recipe.save_comment',
                                        )}
                                        disabled={form.processing}
                                    >
                                        <CheckRounded fontSize="small" />
                                    </IconButton>
                                </span>
                            </Tooltip>
                        </Stack>
                    </Box>
                ) : (
                    <Typography
                        variant="body2"
                        sx={{
                            mt: 0.5,
                            whiteSpace: 'pre-wrap',
                            overflowWrap: 'anywhere',
                        }}
                    >
                        {comment.body}
                    </Typography>
                )}
            </Box>
        </Stack>
    );
}
