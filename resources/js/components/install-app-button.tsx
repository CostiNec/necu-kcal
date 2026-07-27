import InstallMobileRounded from '@mui/icons-material/InstallMobileRounded';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
};

type StandaloneNavigator = Navigator & {
    standalone?: boolean;
};

const isRunningAsApp = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    Boolean((navigator as StandaloneNavigator).standalone);

const isIosDevice = () =>
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

export function InstallAppButton() {
    const { t } = useTranslation();
    const [installPrompt, setInstallPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [instructionsOpen, setInstructionsOpen] = useState(false);
    const [installed, setInstalled] = useState(isRunningAsApp);
    const [ios] = useState(isIosDevice);

    useEffect(() => {
        const handleInstallPrompt = (event: Event) => {
            event.preventDefault();
            setInstallPrompt(event as BeforeInstallPromptEvent);
        };
        const handleInstalled = () => {
            setInstallPrompt(null);
            setInstalled(true);
            setInstructionsOpen(false);
        };

        window.addEventListener(
            'beforeinstallprompt',
            handleInstallPrompt,
        );
        window.addEventListener('appinstalled', handleInstalled);

        return () => {
            window.removeEventListener(
                'beforeinstallprompt',
                handleInstallPrompt,
            );
            window.removeEventListener('appinstalled', handleInstalled);
        };
    }, []);

    if (installed) return null;

    const install = async () => {
        if (!installPrompt) {
            setInstructionsOpen(true);
            return;
        }

        try {
            await installPrompt.prompt();
            const choice = await installPrompt.userChoice;
            setInstallPrompt(null);

            if (choice.outcome === 'accepted') {
                setInstalled(true);
            }
        } catch {
            setInstallPrompt(null);
            setInstructionsOpen(true);
        }
    };

    return (
        <>
            <ListItemButton
                onClick={() => void install()}
                sx={{
                    minHeight: 50,
                    borderRadius: 1.5,
                    color: 'primary.main',
                    bgcolor: 'primary.lighter',
                }}
            >
                <ListItemIcon
                    sx={{ minWidth: 42, color: 'primary.main' }}
                >
                    <InstallMobileRounded />
                </ListItemIcon>
                <ListItemText
                    primary={t('install_app.action')}
                    secondary={t('install_app.action_help')}
                    primaryTypographyProps={{ variant: 'subtitle2' }}
                    secondaryTypographyProps={{ variant: 'caption' }}
                />
            </ListItemButton>

            <Dialog
                open={instructionsOpen}
                onClose={() => setInstructionsOpen(false)}
                maxWidth="xs"
                fullWidth
                sx={(theme) => ({ zIndex: theme.zIndex.modal + 20 })}
            >
                <DialogTitle>{t('install_app.title')}</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" color="text.secondary">
                        {t(
                            ios
                                ? 'install_app.ios_instructions'
                                : 'install_app.browser_instructions',
                        )}
                    </Typography>
                </DialogContent>
                <DialogActions>
                    <Button
                        variant="contained"
                        onClick={() => setInstructionsOpen(false)}
                    >
                        {t('common.close')}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
