import AddRounded from '@mui/icons-material/AddRounded';
import ArrowBackRounded from '@mui/icons-material/ArrowBackRounded';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import DinnerDiningRounded from '@mui/icons-material/DinnerDiningRounded';
import EditRounded from '@mui/icons-material/EditRounded';
import FreeBreakfastRounded from '@mui/icons-material/FreeBreakfastRounded';
import LunchDiningRounded from '@mui/icons-material/LunchDiningRounded';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import IconButton from '@mui/material/IconButton';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useTheme } from '@mui/material/styles';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { BrandMark } from '@/components/brand-mark';
import { ColorModeButton } from '@/components/color-mode-button';
import { LanguageSwitcher } from '@/components/language-switcher';
import { RouterLink } from '@/components/router-link';

const previewMacros = [
    { labelKey: 'common.protein', value: 65, target: 150, color: '#32B8D2' },
    { labelKey: 'common.carbs', value: 183, target: 220, color: '#FFAB00' },
    { labelKey: 'common.fat', value: 32, target: 65, color: '#8E33FF' },
    { labelKey: 'common.fibre', value: 16, target: 30, color: '#22C55E' },
] as const;

const previewMeals = [
    {
        labelKey: 'diary.breakfast',
        calories: 359,
        icon: FreeBreakfastRounded,
        entries: [
            {
                foodKey: 'layout.preview_breakfast_food',
                calories: 194,
                amount: '2 × 100 g',
                protein: 1.5,
                carbs: 45.4,
                fat: 0.6,
                fibre: 3.4,
            },
            {
                foodKey: 'layout.preview_breakfast_food_2',
                calories: 165,
                amount: '1 × 100 g',
                protein: 31,
                carbs: 0,
                fat: 3.6,
                fibre: 0,
            },
        ],
    },
    {
        labelKey: 'diary.lunch',
        calories: 386,
        icon: LunchDiningRounded,
        entries: [
            {
                foodKey: 'layout.preview_lunch_food',
                calories: 386,
                amount: '1 × 100 g',
                protein: 9.8,
                carbs: 69.5,
                fat: 9.8,
                fibre: 7.7,
            },
        ],
    },
    {
        labelKey: 'diary.dinner',
        calories: 522,
        icon: DinnerDiningRounded,
        entries: [],
    },
] as const;

export function AuthLayout({
    children,
    title,
    description,
    back,
}: PropsWithChildren<{
    title: string;
    description: string;
    back?: {
        href: string;
        label: string;
    };
}>) {
    const { t } = useTranslation();

    return (
        <Box
            component="main"
            sx={{
                minHeight: '100vh',
                display: 'grid',
                gridTemplateColumns: { lg: 'minmax(0, 0.9fr) minmax(480px, 1.1fr)' },
            }}
        >
            <Stack sx={{ minHeight: '100vh', p: 2 }}>
                <Stack direction="row" alignItems="center" justifyContent="space-between">
                    <Box
                        sx={{
                            display: {
                                xs: back ? 'none' : 'block',
                                lg: 'block',
                            },
                        }}
                    >
                        <RouterLink
                            href="/"
                            style={{ color: 'inherit', textDecoration: 'none' }}
                        >
                            <BrandMark />
                        </RouterLink>
                    </Box>
                    {back && (
                        <Box sx={{ display: { xs: 'block', lg: 'none' } }}>
                            <RouterLink
                                href={back.href}
                                style={{ color: 'inherit' }}
                            >
                                <IconButton
                                    color="inherit"
                                    aria-label={back.label}
                                    sx={{ width: 44, height: 44 }}
                                >
                                    <ArrowBackRounded />
                                </IconButton>
                            </RouterLink>
                        </Box>
                    )}
                    <Stack direction="row" alignItems="center" spacing={0.5}>
                        <LanguageSwitcher compact />
                        <ColorModeButton />
                    </Stack>
                </Stack>

                <Container
                    component={motion.div}
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    maxWidth="sm"
                    sx={{
                        my: 'auto',
                        py: 2,
                        width: '100%',
                        maxWidth: '480px !important',
                    }}
                >
                    <Typography variant="h3">{title}</Typography>
                    <Typography color="text.secondary" sx={{ my: 2 }}>
                        {description}
                    </Typography>
                    <Paper
                        sx={(theme) => ({
                            p: 2,
                            boxShadow:
                                theme.palette.mode === 'dark'
                                    ? '0 0 2px rgba(0,0,0,0.32), 0 24px 48px -12px rgba(0,0,0,0.52)'
                                    : '0 0 2px rgba(145,158,171,0.2), 0 24px 48px -12px rgba(145,158,171,0.22)',
                        })}
                    >
                        {children}
                    </Paper>
                </Container>

                <Typography variant="caption" color="text.secondary">
                    {t('layout.private_data')}
                </Typography>
            </Stack>

            <Box
                component={motion.section}
                initial={{ opacity: 0, x: 24 }}
                animate={{ opacity: 1, x: 0 }}
                sx={(theme) => ({
                    m: 2,
                    p: { lg: 3, xl: 4 },
                    display: { xs: 'none', lg: 'flex' },
                    alignItems: 'center',
                    justifyContent: 'center',
                    overflow: 'hidden',
                    position: 'relative',
                    borderRadius: 3,
                    color:
                        theme.palette.mode === 'dark'
                            ? theme.palette.common.white
                            : theme.palette.text.primary,
                    bgcolor:
                        theme.palette.mode === 'dark'
                            ? 'primary.darker'
                            : 'primary.lighter',
                    backgroundImage:
                        theme.palette.mode === 'dark'
                            ? 'radial-gradient(circle at 80% 12%, rgba(91,228,155,0.34), transparent 32%), linear-gradient(145deg, #007867, #004B50)'
                            : 'radial-gradient(circle at 80% 12%, rgba(255,255,255,0.78), transparent 34%), linear-gradient(145deg, #E7F8F0, #B5E5D1)',
                })}
            >
                <Box
                    sx={(theme) => ({
                        position: 'absolute',
                        width: 440,
                        height: 440,
                        borderRadius: '50%',
                        border:
                            theme.palette.mode === 'dark'
                                ? '1px solid rgba(255,255,255,0.12)'
                                : '1px solid rgba(0,120,103,0.12)',
                        top: -130,
                        right: -120,
                    })}
                />
                <Stack
                    spacing={{ lg: 2, xl: 3 }}
                    sx={{
                        zIndex: 1,
                        width: '100%',
                        maxWidth: 760,
                    }}
                >
                    <Box sx={{ maxWidth: 640 }}>
                        <Typography
                            variant="overline"
                            sx={(theme) => ({
                                color:
                                    theme.palette.mode === 'dark'
                                        ? 'primary.light'
                                        : 'primary.dark',
                            })}
                        >
                            {t('layout.promo_badge')}
                        </Typography>
                        <Typography variant="h4" sx={{ mt: 0.5 }}>
                            {t('layout.promo_quote')}
                        </Typography>
                        <Typography
                            variant="body2"
                            sx={(theme) => ({
                                mt: 1,
                                color:
                                    theme.palette.mode === 'dark'
                                        ? 'rgba(255,255,255,0.72)'
                                        : theme.palette.text.secondary,
                            })}
                        >
                            {t('layout.promo_copy')}
                        </Typography>
                    </Box>

                    <ProductPreview />
                </Stack>
            </Box>
        </Box>
    );
}

function ProductPreview() {
    const { t } = useTranslation();
    const theme = useTheme();
    const dark = theme.palette.mode === 'dark';

    return (
        <Paper
            role="img"
            aria-label={t('layout.preview_screen_label')}
            sx={(theme) => ({
                p: 0.75,
                color: 'text.primary',
                bgcolor: 'background.paper',
                border: `1px solid ${theme.palette.divider}`,
                boxShadow:
                    theme.palette.mode === 'dark'
                        ? '0 30px 60px rgba(0, 0, 0, 0.42)'
                        : '0 30px 60px rgba(41, 80, 68, 0.22)',
            })}
        >
            <Stack
                direction="row"
                alignItems="center"
                justifyContent="space-between"
                sx={{
                    height: 32,
                    px: 1,
                    color: 'text.secondary',
                }}
            >
                <Stack direction="row" alignItems="center" spacing={1}>
                    <Stack direction="row" spacing={0.5}>
                        {['#FF6B6B', '#FFB020', '#35C88A'].map((color) => (
                            <Box
                                key={color}
                                sx={{
                                    width: 7,
                                    height: 7,
                                    borderRadius: '50%',
                                    bgcolor: color,
                                }}
                            />
                        ))}
                    </Stack>
                    <Typography variant="caption">
                        NecuTrack
                    </Typography>
                </Stack>
                <Typography variant="caption">
                    {t('common.today')}
                </Typography>
            </Stack>

            <Box
                sx={{
                    p: { lg: 1, xl: 1.25 },
                    color: 'text.primary',
                    bgcolor: 'background.default',
                    borderRadius: 1.5,
                }}
            >
                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 0.82fr) minmax(0, 1.18fr)',
                        gap: 1,
                    }}
                >
                    <Paper
                        variant="outlined"
                        sx={{
                            p: 1.25,
                            borderRadius: 1.5,
                        }}
                    >
                        <Box
                            sx={{
                                py: 1,
                                mb: 1,
                                textAlign: 'center',
                                borderRadius: 1.25,
                                bgcolor: 'action.hover',
                            }}
                        >
                            <Typography
                                variant="caption"
                                color="text.secondary"
                                sx={{
                                    display: 'block',
                                    fontSize: 9,
                                    textTransform: 'uppercase',
                                    letterSpacing: 0.6,
                                }}
                            >
                                {t('diary.remaining')}
                            </Typography>
                            <Typography variant="subtitle1" fontWeight={800}>
                                1,033 kcal
                            </Typography>
                        </Box>

                        <Box
                            sx={{
                                display: 'grid',
                                placeItems: 'center',
                                py: 0.5,
                            }}
                        >
                            <Box
                                sx={{
                                    width: 112,
                                    height: 112,
                                    flexShrink: 0,
                                    display: 'grid',
                                    placeItems: 'center',
                                    borderRadius: '50%',
                                    background:
                                        'conic-gradient(#22A76F 0 55%, #BDF4D2 55% 100%)',
                                    filter: dark
                                        ? 'drop-shadow(0 7px 10px rgba(0, 167, 111, 0.18))'
                                        : 'drop-shadow(0 7px 10px rgba(0, 167, 111, 0.2))',
                                }}
                            >
                                <Stack
                                    alignItems="center"
                                    justifyContent="center"
                                    sx={{
                                        width: 88,
                                        height: 88,
                                        borderRadius: '50%',
                                        bgcolor: 'background.paper',
                                    }}
                                >
                                    <Typography
                                        variant="h6"
                                        fontWeight={800}
                                        lineHeight={1}
                                    >
                                        1,267
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                        sx={{ fontSize: 9 }}
                                    >
                                        {t('diary.of_calories', {
                                            target: '2,300',
                                        })}
                                    </Typography>
                                </Stack>
                            </Box>
                        </Box>

                        <Stack spacing={0.9} sx={{ mt: 1 }}>
                            {previewMacros.map((macro) => (
                                <Box key={macro.labelKey}>
                                    <Stack
                                        direction="row"
                                        justifyContent="space-between"
                                        sx={{ mb: 0.4 }}
                                    >
                                        <Typography
                                            variant="caption"
                                            fontWeight={700}
                                            sx={{ fontSize: 10 }}
                                        >
                                            {t(macro.labelKey)}
                                        </Typography>
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            sx={{ fontSize: 9 }}
                                        >
                                            {macro.value}/{macro.target} g
                                        </Typography>
                                    </Stack>
                                    <Box
                                        sx={{
                                            height: 5,
                                            overflow: 'hidden',
                                            borderRadius: 4,
                                            bgcolor: 'action.hover',
                                        }}
                                    >
                                        <Box
                                            sx={{
                                                width: `${(macro.value / macro.target) * 100}%`,
                                                height: '100%',
                                                borderRadius: 'inherit',
                                                bgcolor: macro.color,
                                            }}
                                        />
                                    </Box>
                                </Box>
                            ))}
                        </Stack>
                    </Paper>

                    <Stack spacing={1}>
                        {previewMeals.map((meal) => (
                            <PreviewMealCard
                                key={meal.labelKey}
                                meal={meal}
                            />
                        ))}
                    </Stack>
                </Box>
            </Box>
        </Paper>
    );
}

function PreviewMealCard({ meal }: { meal: (typeof previewMeals)[number] }) {
    const { t } = useTranslation();
    const Icon = meal.icon;

    return (
        <Paper variant="outlined" sx={{ overflow: 'hidden', borderRadius: 1.5 }}>
            <Stack
                direction="row"
                alignItems="center"
                justifyContent="space-between"
                sx={{ px: 1, py: 0.75 }}
            >
                <Stack direction="row" alignItems="center" spacing={0.9}>
                    <Box
                        sx={{
                            width: 28,
                            height: 28,
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: 1,
                            color: 'primary.main',
                            bgcolor: 'primary.lighter',
                        }}
                    >
                        <Icon sx={{ fontSize: 16 }} />
                    </Box>
                    <Box>
                        <Typography
                            variant="caption"
                            fontWeight={800}
                            display="block"
                        >
                            {t(meal.labelKey)}
                        </Typography>
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            sx={{ display: 'block', fontSize: 9 }}
                        >
                            {meal.calories} kcal
                        </Typography>
                    </Box>
                </Stack>
                <Stack
                    direction="row"
                    alignItems="center"
                    spacing={0.35}
                    sx={{
                        px: 0.65,
                        py: 0.4,
                        borderRadius: 0.75,
                        color: 'primary.main',
                        border: '1px solid',
                        borderColor: 'primary.main',
                    }}
                >
                    <AddRounded sx={{ fontSize: 13 }} />
                    <Typography variant="caption" fontWeight={700} fontSize={9}>
                        {t('common.add')}
                    </Typography>
                </Stack>
            </Stack>

            {meal.entries.map((entry, index) => (
                <Box
                    key={entry.foodKey}
                    sx={{
                        px: 1,
                        py: 0.7,
                        borderTop: '1px solid',
                        borderColor: 'divider',
                    }}
                >
                    <Typography
                        variant="caption"
                        fontWeight={700}
                        noWrap
                        sx={{ display: 'block', fontSize: 9.5 }}
                    >
                        {t(entry.foodKey)} – {entry.calories} kcal{' '}
                        <Typography
                            component="span"
                            variant="caption"
                            color="text.secondary"
                            sx={{ fontSize: 8.5 }}
                        >
                            (
                            {t('diary.macro_short', {
                                protein: entry.protein,
                                carbs: entry.carbs,
                                fat: entry.fat,
                                fibre: entry.fibre,
                            })}
                            )
                        </Typography>
                    </Typography>
                    <Typography
                        variant="caption"
                        color="text.secondary"
                        sx={{ display: 'block', mt: 0.25, fontSize: 8.5 }}
                    >
                        {entry.amount}
                    </Typography>
                    {index === 0 ? (
                        <Stack direction="row" spacing={1.1} sx={{ mt: 0.45 }}>
                            <Stack
                                direction="row"
                                alignItems="center"
                                spacing={0.3}
                                sx={{ color: 'primary.main' }}
                            >
                                <EditRounded sx={{ fontSize: 11 }} />
                                <Typography
                                    variant="caption"
                                    fontWeight={700}
                                    fontSize={8.5}
                                >
                                    {t('diary.edit_amount')}
                                </Typography>
                            </Stack>
                            <Stack
                                direction="row"
                                alignItems="center"
                                spacing={0.3}
                                sx={{ color: 'error.main' }}
                            >
                                <DeleteOutlineRounded sx={{ fontSize: 11 }} />
                                <Typography
                                    variant="caption"
                                    fontWeight={700}
                                    fontSize={8.5}
                                >
                                    {t('diary.delete_entry')}
                                </Typography>
                            </Stack>
                        </Stack>
                    ) : null}
                </Box>
            ))}
        </Paper>
    );
}
