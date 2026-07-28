import InstallMobileRounded from '@mui/icons-material/InstallMobileRounded';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import { useSyncExternalStore } from 'react';
import { useTranslation } from 'react-i18next';
import {
    getPwaInstallPrompt,
    requestPwaInstall,
    subscribeToPwaInstallPrompt,
} from '@/lib/pwa-install';

export function InstallAppButton({ compact = false }: { compact?: boolean }) {
    const { t } = useTranslation();
    const installPrompt = useSyncExternalStore(
        subscribeToPwaInstallPrompt,
        getPwaInstallPrompt,
        () => null,
    );

    if (!installPrompt) return null;

    if (compact) {
        return (
            <Button
                variant="soft"
                startIcon={<InstallMobileRounded />}
                onClick={() => void requestPwaInstall()}
            >
                {t('install_app.action')}
            </Button>
        );
    }

    return (
        <Box sx={{ width: 1, flex: '0 0 auto' }}>
            <ListItemButton
                onClick={() => void requestPwaInstall()}
                sx={{
                    width: 1,
                    minHeight: 48,
                    px: 1.5,
                    py: 0.75,
                    borderRadius: 1.5,
                    color: 'primary.main',
                    bgcolor: 'primary.lighter',
                }}
            >
                <ListItemIcon
                    sx={{
                        minWidth: 0,
                        mr: 1.25,
                        color: 'primary.main',
                    }}
                >
                    <InstallMobileRounded fontSize="small" />
                </ListItemIcon>
                <ListItemText
                    primary={t('install_app.action')}
                    secondary={t('install_app.action_help')}
                    sx={{ my: 0 }}
                    primaryTypographyProps={{ variant: 'subtitle2' }}
                    secondaryTypographyProps={{ variant: 'caption' }}
                />
            </ListItemButton>
        </Box>
    );
}
