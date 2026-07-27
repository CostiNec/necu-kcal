import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { BrandMark } from '@/components/brand-mark';
import { ColorModeButton } from '@/components/color-mode-button';
import { LanguageSwitcher } from '@/components/language-switcher';
import { RouterLink } from '@/components/router-link';

export function AuthLayout({
    children,
    title,
    description,
}: PropsWithChildren<{ title: string; description: string }>) {
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
                    <RouterLink
                        href="/"
                        style={{ color: 'inherit', textDecoration: 'none' }}
                    >
                        <BrandMark />
                    </RouterLink>
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
                sx={{
                    m: 2,
                    p: 2,
                    display: { xs: 'none', lg: 'flex' },
                    alignItems: 'flex-end',
                    overflow: 'hidden',
                    position: 'relative',
                    borderRadius: 3,
                    color: 'common.white',
                    bgcolor: 'primary.darker',
                    backgroundImage:
                        'radial-gradient(circle at 80% 12%, rgba(91,228,155,0.34), transparent 32%), linear-gradient(145deg, #007867, #004B50)',
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        width: 440,
                        height: 440,
                        borderRadius: '50%',
                        border: '1px solid rgba(255,255,255,0.12)',
                        top: -130,
                        right: -120,
                    }}
                />
                <Paper
                    sx={{
                        p: 2,
                        maxWidth: 560,
                        color: 'inherit',
                        bgcolor: 'rgba(255,255,255,0.08)',
                        border: '1px solid rgba(255,255,255,0.14)',
                        backdropFilter: 'blur(12px)',
                    }}
                >
                    <Typography
                        variant="overline"
                        sx={{ color: 'primary.light' }}
                    >
                        {t('layout.promo_badge')}
                    </Typography>
                    <Typography variant="h3" sx={{ mt: 2 }}>
                        {t('layout.promo_quote')}
                    </Typography>
                    <Typography sx={{ mt: 2, color: 'rgba(255,255,255,0.72)' }}>
                        {t('layout.promo_copy')}
                    </Typography>
                </Paper>
            </Box>
        </Box>
    );
}
