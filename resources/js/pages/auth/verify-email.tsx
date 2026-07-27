import { Head, router } from '@inertiajs/react';
import MarkEmailReadRounded from '@mui/icons-material/MarkEmailReadRounded';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth.verify_title')}
            description={t('auth.verify_description')}
        >
            <Head title={t('auth.verify_head')} />
            <Stack spacing={2}>
                <Box
                    sx={{
                        width: 56,
                        height: 56,
                        display: 'grid',
                        placeItems: 'center',
                        borderRadius: 2,
                        color: 'primary.main',
                        bgcolor: 'primary.lighter',
                    }}
                >
                    <MarkEmailReadRounded fontSize="large" />
                </Box>
                {status === 'verification-link-sent' && (
                    <Alert severity="success">{t('auth.verification_sent')}</Alert>
                )}
                <Button
                    variant="contained"
                    onClick={() =>
                        router.post('/email/verification-notification')
                    }
                >
                    {t('auth.resend_verification')}
                </Button>
                <Button color="inherit" onClick={() => router.post('/logout')}>
                    {t('common.sign_out')}
                </Button>
            </Stack>
        </AuthLayout>
    );
}
