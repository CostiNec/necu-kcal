import Box from '@mui/material/Box';
import Slider from '@mui/material/Slider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useTranslation } from 'react-i18next';
import { useColorMode } from '@/theme/theme-provider';

const minFontSize = 12;
const maxFontSize = 18;

export function FontSizeSlider() {
    const { t } = useTranslation();
    const { fontSize, setFontSize } = useColorMode();

    return (
        <Box>
            <Stack
                direction="row"
                alignItems="center"
                justifyContent="space-between"
                sx={{ mb: 1 }}
            >
                <Typography variant="subtitle2">
                    {t('theme.font_size')}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                    {fontSize}px
                </Typography>
            </Stack>
            <Stack direction="row" alignItems="center" spacing={1.5}>
                <Typography
                    aria-hidden="true"
                    color="text.secondary"
                    sx={{ fontSize: 12, lineHeight: 1 }}
                >
                    A
                </Typography>
                <Slider
                    min={minFontSize}
                    max={maxFontSize}
                    step={1}
                    value={fontSize}
                    onTouchStart={(event) => event.stopPropagation()}
                    onChange={(_, value) => {
                        if (typeof value === 'number') setFontSize(value);
                    }}
                    valueLabelDisplay="auto"
                    valueLabelFormat={(value) => `${value}px`}
                    aria-label={t('theme.font_size')}
                    sx={{ flex: 1 }}
                />
                <Typography
                    aria-hidden="true"
                    color="text.secondary"
                    sx={{ fontSize: 20, lineHeight: 1 }}
                >
                    A
                </Typography>
            </Stack>
        </Box>
    );
}
