import { router } from '@inertiajs/react';
import ThumbDownAltOutlined from '@mui/icons-material/ThumbDownAltOutlined';
import ThumbDownAltRounded from '@mui/icons-material/ThumbDownAltRounded';
import ThumbUpAltOutlined from '@mui/icons-material/ThumbUpAltOutlined';
import ThumbUpAltRounded from '@mui/icons-material/ThumbUpAltRounded';
import { Button, Stack } from '@mui/material';
import { useState, type KeyboardEvent, type MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';

export type RecipeReaction = 'like' | 'dislike' | null;

export type RecipeReactionSummary = {
    can_react: boolean;
    viewer_reaction: RecipeReaction;
    likes_count: number;
    dislikes_count: number;
};

export function RecipeReactions({
    recipeId,
    reaction,
}: {
    recipeId: number;
    reaction: RecipeReactionSummary;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const stopKeyPropagation = (event: KeyboardEvent) => {
        event.stopPropagation();
    };

    const submitReaction = (
        event: MouseEvent,
        selectedReaction: Exclude<RecipeReaction, null>,
    ) => {
        event.stopPropagation();

        if (!reaction.can_react || processing) {
            return;
        }

        router.post(
            `/recipes/${recipeId}/reaction`,
            { reaction: selectedReaction },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Stack direction="row" spacing={1}>
            <Button
                size="small"
                variant={
                    reaction.viewer_reaction === 'like'
                        ? 'contained'
                        : 'outlined'
                }
                startIcon={
                    reaction.viewer_reaction === 'like' ? (
                        <ThumbUpAltRounded />
                    ) : (
                        <ThumbUpAltOutlined />
                    )
                }
                aria-label={t('recipe.like')}
                aria-pressed={reaction.viewer_reaction === 'like'}
                disabled={!reaction.can_react || processing}
                onClick={(event) => submitReaction(event, 'like')}
                onKeyDown={stopKeyPropagation}
            >
                {reaction.likes_count}
            </Button>
            <Button
                size="small"
                color="error"
                variant={
                    reaction.viewer_reaction === 'dislike'
                        ? 'contained'
                        : 'outlined'
                }
                startIcon={
                    reaction.viewer_reaction === 'dislike' ? (
                        <ThumbDownAltRounded />
                    ) : (
                        <ThumbDownAltOutlined />
                    )
                }
                aria-label={t('recipe.dislike')}
                aria-pressed={reaction.viewer_reaction === 'dislike'}
                disabled={!reaction.can_react || processing}
                onClick={(event) => submitReaction(event, 'dislike')}
                onKeyDown={stopKeyPropagation}
            >
                {reaction.dislikes_count}
            </Button>
        </Stack>
    );
}
