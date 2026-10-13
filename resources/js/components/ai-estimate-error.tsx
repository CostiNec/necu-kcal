import ContentCopyRounded from '@mui/icons-material/ContentCopyRounded';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export function AiEstimateError({ message, report }: { message: string; report: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const [copyFailed, setCopyFailed] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(report || message);
            setCopied(true);
            setCopyFailed(false);
        } catch {
            setCopyFailed(true);
        }
    };

    return (
        <Alert severity="error" sx={{ minWidth: 0, '& .MuiAlert-message': { minWidth: 0, width: '100%' } }}>
            <Stack spacing={1}>
                <Box sx={{ overflowWrap: 'anywhere' }}>{message}</Box>
                <Button size="small" color="inherit" startIcon={<ContentCopyRounded />} onClick={() => void copy()} sx={{ alignSelf: 'flex-start' }}>
                    {t(copied ? 'diary.ai_error_copied' : 'diary.ai_copy_error')}
                </Button>
                {copyFailed && <Box>{t('diary.ai_error_copy_failed')}</Box>}
                {report && (
                    <Box component="details">
                        <Box component="summary" sx={{ cursor: 'pointer' }}>{t('diary.ai_error_details')}</Box>
                        <Box component="pre" tabIndex={0} sx={{ maxHeight: 320, overflow: 'auto', whiteSpace: 'pre-wrap', overflowWrap: 'anywhere', fontSize: 12, userSelect: 'text' }}>
                            {report}
                        </Box>
                    </Box>
                )}
            </Stack>
        </Alert>
    );
}
