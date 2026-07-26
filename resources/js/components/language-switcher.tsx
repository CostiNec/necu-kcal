import { router, usePage } from '@inertiajs/react';
import KeyboardArrowDownRounded from '@mui/icons-material/KeyboardArrowDownRounded';
import Button from '@mui/material/Button';
import ListItemIcon from '@mui/material/ListItemIcon';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Typography from '@mui/material/Typography';
import { useState, type MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { SharedProps } from '@/types';

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
    const { availableLocales } = usePage<SharedProps>().props;
    const { t, i18n } = useTranslation();
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const resolvedLocale = i18n.resolvedLanguage?.split('-')[0] ?? 'en';
    const activeLocale = availableLocales[resolvedLocale]
        ? resolvedLocale
        : Object.keys(availableLocales)[0] ?? 'en';
    const current = availableLocales[activeLocale];

    const handleOpen = (event: MouseEvent<HTMLButtonElement>) =>
        setAnchorEl(event.currentTarget);

    const changeLocale = (locale: string) => {
        setAnchorEl(null);
        if (locale === activeLocale) return;

        document.documentElement.lang = locale;
        void i18n.changeLanguage(locale);
        router.post(
            '/locale',
            { locale },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Button
                color="inherit"
                onClick={handleOpen}
                aria-label={t('language.label')}
                endIcon={<KeyboardArrowDownRounded />}
                sx={{ minWidth: compact ? 80 : 96 }}
            >
                <Typography component="span" sx={{ mr: 0.75, fontSize: 18 }}>
                    {current?.flag}
                </Typography>
                {current?.code}
            </Button>
            <Menu
                anchorEl={anchorEl}
                open={Boolean(anchorEl)}
                onClose={() => setAnchorEl(null)}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                transformOrigin={{ vertical: 'top', horizontal: 'right' }}
                transitionDuration={160}
                sx={(theme) => ({
                    zIndex: theme.zIndex.modal + 20,
                })}
                slotProps={{
                    paper: {
                        sx: {
                            minWidth: 190,
                            overflow: 'hidden',
                        },
                    },
                }}
            >
                {Object.entries(availableLocales).map(([locale, language]) => (
                    <MenuItem
                        key={locale}
                        selected={locale === activeLocale}
                        onClick={() => changeLocale(locale)}
                        sx={{
                            gap: 0.5,
                            '&.Mui-selected': {
                                bgcolor: 'primary.lighter',
                            },
                        }}
                    >
                        <ListItemIcon
                            sx={{
                                minWidth: 34,
                                fontSize: 21,
                                opacity: 1,
                            }}
                        >
                            {language.flag}
                        </ListItemIcon>
                        <Typography
                            variant="subtitle2"
                            sx={{ minWidth: 28, color: 'text.primary' }}
                        >
                            {language.code}
                        </Typography>
                        <Typography
                            variant="body2"
                            sx={{ color: 'text.primary' }}
                        >
                            {language.name}
                        </Typography>
                    </MenuItem>
                ))}
            </Menu>
        </>
    );
}
