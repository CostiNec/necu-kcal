import ChevronLeftRounded from '@mui/icons-material/ChevronLeftRounded';
import ChevronRightRounded from '@mui/icons-material/ChevronRightRounded';
import IconButton from '@mui/material/IconButton';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { RouterLink } from '@/components/router-link';

export function PeriodNavigator({
    title,
    subtitle,
    previousHref,
    nextHref,
    previousLabel,
    nextLabel,
}: {
    title: string;
    subtitle: string;
    previousHref: string;
    nextHref: string;
    previousLabel: string;
    nextLabel: string;
}) {
    return (
        <Paper
            variant="outlined"
            sx={{
                p: 1,
                mb: 3,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                borderColor: 'divider',
            }}
        >
            <RouterLink href={previousHref}>
                <IconButton component="span" aria-label={previousLabel}>
                    <ChevronLeftRounded />
                </IconButton>
            </RouterLink>
            <Stack alignItems="center">
                <Typography variant="subtitle2">{title}</Typography>
                <Typography variant="caption" color="text.secondary">
                    {subtitle}
                </Typography>
            </Stack>
            <RouterLink href={nextHref}>
                <IconButton component="span" aria-label={nextLabel}>
                    <ChevronRightRounded />
                </IconButton>
            </RouterLink>
        </Paper>
    );
}
