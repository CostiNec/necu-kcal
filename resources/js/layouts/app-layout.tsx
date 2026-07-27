import { router, usePage } from '@inertiajs/react';
import BarChartOutlined from '@mui/icons-material/BarChartOutlined';
import CloseRounded from '@mui/icons-material/CloseRounded';
import RestaurantMenuOutlined from '@mui/icons-material/RestaurantMenuOutlined';
import ReceiptLongOutlined from '@mui/icons-material/ReceiptLongOutlined';
import LogoutRounded from '@mui/icons-material/LogoutRounded';
import MenuRounded from '@mui/icons-material/MenuRounded';
import MenuBookOutlined from '@mui/icons-material/MenuBookOutlined';
import MonitorWeightOutlined from '@mui/icons-material/MonitorWeightOutlined';
import SettingsOutlined from '@mui/icons-material/SettingsOutlined';
import AppBar from '@mui/material/AppBar';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import SwipeableDrawer from '@mui/material/SwipeableDrawer';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { alpha } from '@mui/material/styles';
import { useEffect, useState, type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { ColorModeButton } from '@/components/color-mode-button';
import { LanguageSwitcher } from '@/components/language-switcher';
import { InstallAppButton } from '@/components/install-app-button';
import { MobileBottomNavigation } from '@/components/mobile-bottom-navigation';
import { PageTransition } from '@/components/page-transition';
import { RouterLink } from '@/components/router-link';
import { toast } from '@/components/snackbar';
import type { SharedProps } from '@/types';

const drawerWidth = 280;

const desktopNavigation = [
    {
        labelKey: 'common.today',
        href: '/today',
        icon: RestaurantMenuOutlined,
        match: ['/today', '/diary'],
    },
    {
        labelKey: 'common.foods',
        href: '/foods',
        icon: MenuBookOutlined,
        match: ['/foods'],
    },
    {
        labelKey: 'common.recipes',
        href: '/recipes',
        icon: ReceiptLongOutlined,
        match: ['/recipes'],
    },
    {
        labelKey: 'common.reports',
        href: '/reports',
        icon: BarChartOutlined,
        match: ['/reports'],
    },
    {
        labelKey: 'common.weight',
        href: '/weight',
        icon: MonitorWeightOutlined,
        match: ['/weight'],
    },
    {
        labelKey: 'common.profile',
        href: '/settings',
        icon: SettingsOutlined,
        match: ['/settings'],
    },
];

const mobileNavigation = ['/today', '/foods', '/recipes', '/weight'].map(
    (href) => desktopNavigation.find((item) => item.href === href)!,
);

export function AppLayout({
    children,
    title,
    subtitle,
    actions,
}: PropsWithChildren<{
    title?: string;
    subtitle?: string;
    actions?: React.ReactNode;
}>) {
    const page = usePage<SharedProps>();
    const { auth, flash } = page.props;
    const { t } = useTranslation();
    const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success, { id: 'flash-notification' });
        } else if (flash.error) {
            toast.error(flash.error, { id: 'flash-notification' });
        }
    }, [flash.error, flash.success]);

    const isActive = (matches: string[]) =>
        matches.some(
            (path) => page.url === path || page.url.startsWith(`${path}/`),
        );

    const activeIndex = mobileNavigation.findIndex((item) =>
        isActive(item.match),
    );

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh' }}>
            <Drawer
                variant="permanent"
                sx={{
                    display: { xs: 'none', lg: 'block' },
                    width: drawerWidth,
                    flexShrink: 0,
                    '& .MuiDrawer-paper': {
                        width: drawerWidth,
                        borderRightStyle: 'dashed',
                        bgcolor: 'background.default',
                    },
                }}
            >
                <Box sx={{ p: 2 }}>
                    <BrandMark />
                </Box>
                <List sx={{ p: 2 }}>
                    {desktopNavigation.map((item) => {
                        const active = isActive(item.match);
                        const Icon = item.icon;

                        return (
                            <RouterLink
                                key={item.href}
                                href={item.href}
                                style={{ color: 'inherit', textDecoration: 'none' }}
                            >
                                <ListItemButton
                                    selected={active}
                                    sx={{
                                        minHeight: 48,
                                        mb: 2,
                                        borderRadius: 1,
                                        '&.Mui-selected': {
                                            color: 'primary.main',
                                            bgcolor: 'primary.lighter',
                                            '&:hover': {
                                                bgcolor: 'primary.lighter',
                                            },
                                        },
                                    }}
                                >
                                    <ListItemIcon
                                        sx={{
                                            minWidth: 40,
                                            color: active
                                                ? 'primary.main'
                                                : 'text.secondary',
                                        }}
                                    >
                                        <Icon fontSize="small" />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(item.labelKey)}
                                        primaryTypographyProps={{
                                            variant: 'subtitle2',
                                        }}
                                    />
                                </ListItemButton>
                            </RouterLink>
                        );
                    })}
                </List>
                <Box sx={{ mt: 'auto', p: 2 }}>
                    <Paper
                        variant="outlined"
                        sx={{ p: 2, mb: 2, borderColor: 'divider' }}
                    >
                        <Stack direction="row" alignItems="center" spacing={2}>
                            <Avatar
                                sx={{
                                    width: 40,
                                    height: 40,
                                    color: 'primary.main',
                                    bgcolor: 'primary.lighter',
                                }}
                            >
                                {auth.user?.name.slice(0, 1).toUpperCase()}
                            </Avatar>
                            <Box sx={{ minWidth: 0 }}>
                                <Typography variant="subtitle2" noWrap>
                                    {auth.user?.name}
                                </Typography>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    noWrap
                                >
                                    {auth.user?.email}
                                </Typography>
                            </Box>
                        </Stack>
                    </Paper>
                    <Button
                        fullWidth
                        color="inherit"
                        startIcon={<LogoutRounded />}
                        onClick={() => router.post('/logout')}
                        sx={{ justifyContent: 'flex-start' }}
                    >
                        {t('common.sign_out')}
                    </Button>
                </Box>
            </Drawer>

            <SwipeableDrawer
                anchor="right"
                open={mobileDrawerOpen}
                onOpen={() => setMobileDrawerOpen(true)}
                onClose={() => setMobileDrawerOpen(false)}
                swipeAreaWidth={24}
                hysteresis={0.35}
                ModalProps={{ keepMounted: true }}
                sx={(theme) => ({
                    display: { lg: 'none' },
                    zIndex: theme.zIndex.modal + 10,
                    '& .MuiDrawer-paper': {
                        width: 'min(86vw, 340px)',
                        bgcolor: 'background.default',
                        backgroundImage: 'none',
                        zIndex: theme.zIndex.modal + 11,
                    },
                })}
            >
                <Stack sx={{ height: 1 }}>
                    <Stack
                        component="header"
                        direction="row"
                        alignItems="center"
                        justifyContent="space-between"
                        sx={{
                            height: 72,
                            minHeight: 72,
                            px: 2,
                            borderBottom: 1,
                            borderColor: 'divider',
                            bgcolor: 'background.paper',
                        }}
                    >
                        <Typography variant="h6">
                            {t('common.menu')}
                        </Typography>
                        <IconButton
                            onClick={() => setMobileDrawerOpen(false)}
                            aria-label={t('common.close')}
                            sx={{ width: 44, height: 44 }}
                        >
                            <CloseRounded />
                        </IconButton>
                    </Stack>

                    <Box
                        sx={{
                            display: 'flex',
                            minHeight: 0,
                            flex: 1,
                            flexDirection: 'column',
                            overflowY: 'auto',
                            p: 2,
                        }}
                    >
                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Stack
                                direction="row"
                                alignItems="center"
                                justifyContent="space-between"
                                spacing={2}
                            >
                                <LanguageSwitcher />
                                <ColorModeButton withLabel />
                            </Stack>
                        </Paper>

                        <Divider sx={{ my: 2 }} />

                        <List sx={{ p: 0 }}>
                            {desktopNavigation.map((item) => {
                                const active = isActive(item.match);
                                const Icon = item.icon;

                                return (
                                    <RouterLink
                                        key={item.href}
                                        href={item.href}
                                        style={{
                                            color: 'inherit',
                                            textDecoration: 'none',
                                        }}
                                    >
                                        <ListItemButton
                                            selected={active}
                                            onClick={() =>
                                                setMobileDrawerOpen(false)
                                            }
                                            sx={{
                                                minHeight: 50,
                                                mb: 2,
                                                borderRadius: 1.5,
                                                '&.Mui-selected': {
                                                    color: 'primary.main',
                                                    bgcolor: 'primary.lighter',
                                                },
                                            }}
                                        >
                                            <ListItemIcon
                                                sx={{
                                                    minWidth: 42,
                                                    color: active
                                                        ? 'primary.main'
                                                        : 'text.secondary',
                                                }}
                                            >
                                                <Icon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(item.labelKey)}
                                                primaryTypographyProps={{
                                                    variant: 'subtitle2',
                                                }}
                                            />
                                        </ListItemButton>
                                    </RouterLink>
                                );
                            })}
                        </List>

                        <Divider sx={{ my: 2 }} />
                        <InstallAppButton />

                        <Box sx={{ mt: 'auto', pt: 2 }}>
                            <Paper variant="outlined" sx={{ p: 2, mb: 2 }}>
                                <Stack
                                    direction="row"
                                    alignItems="center"
                                    spacing={2}
                                >
                                    <Avatar
                                        sx={{
                                            width: 40,
                                            height: 40,
                                            color: 'primary.main',
                                            bgcolor: 'primary.lighter',
                                        }}
                                    >
                                        {auth.user?.name
                                            .slice(0, 1)
                                            .toUpperCase()}
                                    </Avatar>
                                    <Box sx={{ minWidth: 0 }}>
                                        <Typography variant="subtitle2" noWrap>
                                            {auth.user?.name}
                                        </Typography>
                                        <Typography
                                            variant="caption"
                                            color="text.secondary"
                                            noWrap
                                        >
                                            {auth.user?.email}
                                        </Typography>
                                    </Box>
                                </Stack>
                            </Paper>
                            <Button
                                fullWidth
                                color="inherit"
                                startIcon={<LogoutRounded />}
                                onClick={() => router.post('/logout')}
                                sx={{ justifyContent: 'flex-start' }}
                            >
                                {t('common.sign_out')}
                            </Button>
                        </Box>
                    </Box>
                </Stack>
            </SwipeableDrawer>

            <Box sx={{ minWidth: 0, flexGrow: 1 }}>
                <AppBar
                    position="sticky"
                    color="transparent"
                    elevation={0}
                    sx={(theme) => ({
                        bgcolor: alpha(theme.palette.background.default, 0.84),
                        backdropFilter: 'blur(16px)',
                        borderBottom: 1,
                        borderColor: 'divider',
                    })}
                >
                    <Toolbar
                        sx={{
                            minHeight: { xs: 72, lg: 88 },
                            px: 2,
                        }}
                    >
                        <Box sx={{ display: { xs: 'block', lg: 'none' } }}>
                            <BrandMark compact />
                        </Box>
                        <Box sx={{ display: { xs: 'none', lg: 'block' } }}>
                            {title && <Typography variant="h5">{title}</Typography>}
                            {subtitle && (
                                <Typography variant="body2" color="text.secondary">
                                    {subtitle}
                                </Typography>
                            )}
                        </Box>
                        <Stack
                            direction="row"
                            alignItems="center"
                            spacing={2}
                            sx={{ ml: 'auto' }}
                        >
                            {actions}
                            <Stack
                                direction="row"
                                alignItems="center"
                                spacing={0.5}
                                sx={{
                                    display: { xs: 'none', lg: 'flex' },
                                }}
                            >
                                <LanguageSwitcher compact />
                                <ColorModeButton />
                            </Stack>
                            <IconButton
                                color="inherit"
                                onClick={() => setMobileDrawerOpen(true)}
                                aria-label={t('common.open_menu')}
                                sx={{
                                    display: { lg: 'none' },
                                    width: 44,
                                    height: 44,
                                }}
                            >
                                <MenuRounded />
                            </IconButton>
                        </Stack>
                    </Toolbar>
                </AppBar>

                <Container
                    component="main"
                    maxWidth="xl"
                    sx={{ py: 2, pb: { xs: 13, lg: 2 } }}
                >
                    {(title || subtitle) && (
                        <Box sx={{ display: { lg: 'none' }, mb: 2 }}>
                            {title && <Typography variant="h4">{title}</Typography>}
                            {subtitle && (
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ mt: 2 }}
                                >
                                    {subtitle}
                                </Typography>
                            )}
                        </Box>
                    )}
                    <PageTransition>{children}</PageTransition>
                </Container>
            </Box>

            <MobileBottomNavigation
                ariaLabel={t('common.primary_navigation')}
                value={activeIndex}
                items={mobileNavigation.map((item) => ({
                    href: item.href,
                    label: t(item.labelKey),
                    icon: item.icon,
                }))}
                onNavigate={(href) => router.visit(href)}
            />

        </Box>
    );
}
