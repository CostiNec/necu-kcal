import DarkModeRounded from '@mui/icons-material/DarkModeRounded';
import LightModeRounded from '@mui/icons-material/LightModeRounded';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import { useTranslation } from 'react-i18next';
import { useColorMode } from '@/theme/theme-provider';

export function ColorModeButton({ withLabel = false }: { withLabel?: boolean }) {
    const { t } = useTranslation();
    const { mode, toggleMode } = useColorMode();
    const dark = mode === 'dark';
    const label = dark ? t('theme.light_mode') : t('theme.dark_mode');
    const Icon = dark ? LightModeRounded : DarkModeRounded;

    if (withLabel) {
        return (
            <Button
                fullWidth
                color="inherit"
                startIcon={<Icon />}
                onClick={toggleMode}
                sx={{ justifyContent: 'flex-start' }}
            >
                {label}
            </Button>
        );
    }

    return (
        <Tooltip title={label}>
            <IconButton color="inherit" onClick={toggleMode} aria-label={label}>
                <Icon />
            </IconButton>
        </Tooltip>
    );
}
