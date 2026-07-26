import { Head, usePage } from '@inertiajs/react';
import ArrowForwardRounded from '@mui/icons-material/ArrowForwardRounded';
import CheckRounded from '@mui/icons-material/CheckRounded';
import InsightsRounded from '@mui/icons-material/InsightsRounded';
import SearchRounded from '@mui/icons-material/SearchRounded';
import TrackChangesRounded from '@mui/icons-material/TrackChangesRounded';
import AutoAwesomeRounded from '@mui/icons-material/AutoAwesomeRounded';
import {
    AppBar,
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    CircularProgress,
    Container,
    Grid,
    LinearProgress,
    Stack,
    Toolbar,
    Typography,
} from '@mui/material';
import { alpha } from '@mui/material/styles';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { ColorModeButton } from '@/components/color-mode-button';
import { LanguageSwitcher } from '@/components/language-switcher';
import { RouterLink } from '@/components/router-link';
import type { SharedProps } from '@/types';

const featureIcons = [SearchRounded, TrackChangesRounded, InsightsRounded];

export default function Welcome() {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();

    return (
        <Box sx={{ minHeight: '100vh', overflow: 'hidden' }}>
            <Head title={t('landing.head_title')} />

            <AppBar
                position="sticky"
                color="transparent"
                elevation={0}
                sx={(theme) => ({
                    borderBottom: 1,
                    borderColor: 'divider',
                    bgcolor: alpha(theme.palette.background.paper, 0.82),
                    backdropFilter: 'blur(16px)',
                })}
            >
                <Toolbar
                    sx={{
                        width: 1,
                        maxWidth: 1280,
                        minHeight: { xs: 72, sm: 80 },
                        mx: 'auto',
                        px: { xs: 2.5, sm: 4 },
                    }}
                >
                    <BrandMark />
                    <Stack
                        direction="row"
                        alignItems="center"
                        spacing={1}
                        sx={{ ml: 'auto' }}
                    >
                        <LanguageSwitcher compact />
                        <ColorModeButton />
                        {auth.user ? (
                            <RouterLink href="/today">
                                <Button
                                    variant="contained"
                                    endIcon={<ArrowForwardRounded />}
                                >
                                    {t('landing.open_diary')}
                                </Button>
                            </RouterLink>
                        ) : (
                            <>
                                <RouterLink href="/login">
                                    <Button
                                        color="inherit"
                                        sx={{
                                            display: { xs: 'none', sm: 'inline-flex' },
                                        }}
                                    >
                                        {t('landing.sign_in')}
                                    </Button>
                                </RouterLink>
                                <RouterLink href="/register">
                                    <Button variant="contained">
                                        {t('landing.start_tracking')}
                                    </Button>
                                </RouterLink>
                            </>
                        )}
                    </Stack>
                </Toolbar>
            </AppBar>

            <Box
                component="main"
                sx={{
                    position: 'relative',
                    '&::before': {
                        content: '""',
                        position: 'absolute',
                        zIndex: -1,
                        top: -260,
                        left: -260,
                        width: 620,
                        height: 620,
                        borderRadius: '50%',
                        bgcolor: 'primary.lighter',
                        filter: 'blur(90px)',
                        opacity: 0.55,
                    },
                    '&::after': {
                        content: '""',
                        position: 'absolute',
                        zIndex: -1,
                        top: 80,
                        right: -280,
                        width: 560,
                        height: 560,
                        borderRadius: '50%',
                        bgcolor: 'secondary.light',
                        filter: 'blur(120px)',
                        opacity: 0.18,
                    },
                }}
            >
                <Container maxWidth="lg" sx={{ py: { xs: 7, md: 13 } }}>
                    <Grid container spacing={{ xs: 7, lg: 10 }} alignItems="center">
                        <Grid size={{ xs: 12, lg: 7 }}>
                            <motion.div
                                initial={{ opacity: 0, y: 18 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.55, ease: 'easeOut' }}
                            >
                                <Chip
                                    icon={<AutoAwesomeRounded />}
                                    label={t('landing.badge')}
                                    color="primary"
                                    sx={{ mb: 3 }}
                                />
                                <Typography
                                    component="h1"
                                    variant="h1"
                                    sx={{
                                        maxWidth: 700,
                                        fontSize: {
                                            xs: '2.75rem',
                                            sm: '3.75rem',
                                            md: '4.5rem',
                                        },
                                        lineHeight: 1.04,
                                    }}
                                >
                                    {t('landing.headline')}
                                </Typography>
                                <Typography
                                    variant="h6"
                                    color="text.secondary"
                                    sx={{
                                        mt: 3,
                                        maxWidth: 620,
                                        fontWeight: 400,
                                        lineHeight: 1.65,
                                    }}
                                >
                                    {t('landing.description')}
                                </Typography>

                                <Stack
                                    direction={{ xs: 'column', sm: 'row' }}
                                    spacing={1.5}
                                    sx={{ mt: 4 }}
                                >
                                    <RouterLink
                                        href={auth.user ? '/today' : '/register'}
                                    >
                                        <Button
                                            size="large"
                                            variant="contained"
                                            endIcon={<ArrowForwardRounded />}
                                        >
                                            {auth.user
                                                ? t('landing.open_your_diary')
                                                : t(
                                                      'landing.create_free_account',
                                                  )}
                                        </Button>
                                    </RouterLink>
                                    {!auth.user && (
                                        <RouterLink href="/login">
                                            <Button size="large" variant="outlined">
                                                {t(
                                                    'landing.already_have_account',
                                                )}
                                            </Button>
                                        </RouterLink>
                                    )}
                                </Stack>

                                <Stack
                                    direction="row"
                                    flexWrap="wrap"
                                    gap={1.25}
                                    sx={{ mt: 4 }}
                                >
                                    {[
                                        'landing.private_default',
                                        'landing.no_subscription',
                                        'landing.mobile_first',
                                    ].map((key) => (
                                        <Chip
                                            key={key}
                                            variant="outlined"
                                            icon={<CheckRounded color="primary" />}
                                            label={t(key)}
                                            sx={{ bgcolor: 'background.paper' }}
                                        />
                                    ))}
                                </Stack>
                            </motion.div>
                        </Grid>

                        <Grid size={{ xs: 12, lg: 5 }}>
                            <motion.div
                                initial={{ opacity: 0, scale: 0.96, y: 24 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                transition={{
                                    duration: 0.65,
                                    delay: 0.12,
                                    ease: 'easeOut',
                                }}
                            >
                                <Card
                                    sx={{
                                        maxWidth: 460,
                                        mx: 'auto',
                                        boxShadow:
                                            '0 32px 80px rgba(0, 167, 111, 0.18)',
                                    }}
                                >
                                    <CardContent>
                                        <Stack
                                            direction="row"
                                            justifyContent="space-between"
                                            alignItems="flex-start"
                                        >
                                            <Box>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    {t('common.today')}
                                                </Typography>
                                                <Typography variant="h5" sx={{ mt: 0.5 }}>
                                                    {t(
                                                        'landing.good_afternoon',
                                                    )}
                                                </Typography>
                                            </Box>
                                            <Chip
                                                size="small"
                                                color="success"
                                                label={t('landing.on_track')}
                                            />
                                        </Stack>

                                        <Box
                                            sx={{
                                                position: 'relative',
                                                display: 'grid',
                                                placeItems: 'center',
                                                width: 196,
                                                height: 196,
                                                mx: 'auto',
                                                my: 4,
                                            }}
                                        >
                                            <CircularProgress
                                                variant="determinate"
                                                value={100}
                                                size={196}
                                                thickness={4}
                                                sx={{
                                                    position: 'absolute',
                                                    color: 'primary.lighter',
                                                }}
                                            />
                                            <CircularProgress
                                                variant="determinate"
                                                value={68}
                                                size={196}
                                                thickness={4}
                                                sx={{
                                                    position: 'absolute',
                                                    color: 'primary.main',
                                                    '& .MuiCircularProgress-circle':
                                                        { strokeLinecap: 'round' },
                                                }}
                                            />
                                            <Box sx={{ textAlign: 'center' }}>
                                                <Typography variant="h3">
                                                    1,368
                                                </Typography>
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                >
                                                    {t('landing.of_target', {
                                                        target: '2,000',
                                                    })}
                                                </Typography>
                                            </Box>
                                        </Box>

                                        <Grid container spacing={1.5}>
                                            {[
                                                [
                                                    'common.protein',
                                                    '84 / 120g',
                                                    70,
                                                    '#8E33FF',
                                                ],
                                                [
                                                    'common.carbs',
                                                    '146 / 220g',
                                                    66,
                                                    '#FFAB00',
                                                ],
                                                [
                                                    'common.fat',
                                                    '42 / 65g',
                                                    65,
                                                    '#FF5630',
                                                ],
                                            ].map(([label, value, progress, color]) => (
                                                <Grid
                                                    key={String(label)}
                                                    size={{ xs: 4 }}
                                                >
                                                    <Box
                                                        sx={{
                                                            p: 1.5,
                                                            borderRadius: 2,
                                                            bgcolor:
                                                                'background.default',
                                                        }}
                                                    >
                                                        <Typography
                                                            variant="caption"
                                                            color="text.secondary"
                                                        >
                                                            {t(String(label))}
                                                        </Typography>
                                                        <Typography
                                                            variant="subtitle2"
                                                            noWrap
                                                            sx={{ mt: 0.25 }}
                                                        >
                                                            {String(value)}
                                                        </Typography>
                                                        <LinearProgress
                                                            variant="determinate"
                                                            value={Number(progress)}
                                                            sx={{
                                                                mt: 1,
                                                                height: 5,
                                                                bgcolor:
                                                                    'action.hover',
                                                                '& .MuiLinearProgress-bar':
                                                                    {
                                                                        bgcolor:
                                                                            String(
                                                                                color,
                                                                            ),
                                                                    },
                                                            }}
                                                        />
                                                    </Box>
                                                </Grid>
                                            ))}
                                        </Grid>
                                        <Button
                                            fullWidth
                                            variant="contained"
                                            startIcon={<SearchRounded />}
                                            sx={{ mt: 2.5 }}
                                        >
                                            {t('landing.add_food')}
                                        </Button>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </Grid>
                    </Grid>
                </Container>

                <Box
                    sx={(theme) => ({
                        borderTop: 1,
                        borderBottom: 1,
                        borderColor: 'divider',
                        bgcolor: alpha(theme.palette.background.paper, 0.7),
                        backdropFilter: 'blur(16px)',
                    })}
                >
                    <Container maxWidth="lg" sx={{ py: 8 }}>
                        <Grid container spacing={3}>
                            {[
                                [
                                    'landing.feature_log_title',
                                    'landing.feature_log_copy',
                                ],
                                [
                                    'landing.feature_targets_title',
                                    'landing.feature_targets_copy',
                                ],
                                [
                                    'landing.feature_reports_title',
                                    'landing.feature_reports_copy',
                                ],
                            ].map(([title, copy], index) => {
                                const Icon = featureIcons[index];

                                return (
                                    <Grid key={title} size={{ xs: 12, md: 4 }}>
                                        <Card
                                            variant="outlined"
                                            sx={{
                                                height: 1,
                                                boxShadow: 'none',
                                                transition: (theme) =>
                                                    theme.transitions.create([
                                                        'transform',
                                                        'box-shadow',
                                                    ]),
                                                '&:hover': {
                                                    transform:
                                                        'translateY(-4px)',
                                                    boxShadow: (theme) =>
                                                        theme.shadows[8],
                                                },
                                            }}
                                        >
                                            <CardContent>
                                                <Box
                                                    sx={{
                                                        display: 'grid',
                                                        placeItems: 'center',
                                                        width: 48,
                                                        height: 48,
                                                        mb: 2,
                                                        borderRadius: 2,
                                                        color: 'primary.main',
                                                        bgcolor:
                                                            'primary.lighter',
                                                    }}
                                                >
                                                    <Icon />
                                                </Box>
                                                <Typography variant="h6">
                                                    {t(title)}
                                                </Typography>
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                    sx={{ mt: 1, lineHeight: 1.7 }}
                                                >
                                                    {t(copy)}
                                                </Typography>
                                            </CardContent>
                                        </Card>
                                    </Grid>
                                );
                            })}
                        </Grid>
                    </Container>
                </Box>
            </Box>
        </Box>
    );
}
