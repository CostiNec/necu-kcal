import LocalFireDepartmentOutlined from '@mui/icons-material/LocalFireDepartmentOutlined';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export function BrandMark({ compact = false }: { compact?: boolean }) {
    return (
        <Box sx={{ display: 'inline-flex', alignItems: 'center', gap: 1.25 }}>
            <Box
                sx={{
                    width: 40,
                    height: 40,
                    display: 'grid',
                    placeItems: 'center',
                    borderRadius: 1.5,
                    color: 'primary.main',
                    bgcolor: 'primary.lighter',
                }}
            >
                <LocalFireDepartmentOutlined />
            </Box>
            {!compact && (
                <Typography variant="h6" component="span">
                    Kcal
                </Typography>
            )}
        </Box>
    );
}
