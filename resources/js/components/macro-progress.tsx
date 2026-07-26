import Box from '@mui/material/Box';
import LinearProgress from '@mui/material/LinearProgress';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatNumber } from '@/lib/utils';

const colors = {
    protein: '#00B8D9',
    carbohydrates: '#FFAB00',
    fat: '#8E33FF',
    fibre: '#22C55E',
};

export function MacroProgress({
    label,
    type,
    value,
    target,
}: {
    label: string;
    type: keyof typeof colors;
    value: number;
    target: number;
}) {
    const percentage = target > 0 ? Math.min((value / target) * 100, 100) : 0;

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" sx={{ mb: 1 }}>
                <Typography variant="subtitle2">{label}</Typography>
                <Typography variant="caption" color="text.secondary">
                    <Box component="strong" sx={{ color: 'text.primary' }}>
                        {formatNumber(value)}
                    </Box>{' '}
                    / {formatNumber(target)} g
                </Typography>
            </Stack>
            <LinearProgress
                variant="determinate"
                value={percentage}
                sx={{
                    bgcolor: 'action.hover',
                    '& .MuiLinearProgress-bar': { bgcolor: colors[type] },
                }}
            />
        </Box>
    );
}
