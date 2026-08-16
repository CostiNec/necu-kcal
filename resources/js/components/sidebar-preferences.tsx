import Divider from '@mui/material/Divider';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import { ColorModeButton } from '@/components/color-mode-button';
import { FontSizeSlider } from '@/components/font-size-slider';
import { LanguageSwitcher } from '@/components/language-switcher';

export function SidebarPreferences() {
    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Stack spacing={2}>
                <Stack
                    direction="row"
                    alignItems="center"
                    justifyContent="space-between"
                    spacing={2}
                >
                    <LanguageSwitcher />
                    <ColorModeButton withLabel />
                </Stack>
                <Divider />
                <FontSizeSlider />
            </Stack>
        </Paper>
    );
}
