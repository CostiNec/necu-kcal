import { Head, useForm } from '@inertiajs/react';
import ArrowBackRounded from '@mui/icons-material/ArrowBackRounded';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { RouterLink } from '@/components/router-link';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();
    const form = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/forgot-password');
    };

    return (
        <AuthLayout
            title={t('auth.reset_title')}
            description={t('auth.reset_description')}
        >
            <Head title={t('auth.forgot_head')} />
            <Stack spacing={2}>
                {status && <Alert severity="success">{status}</Alert>}
                <Stack component="form" spacing={2} onSubmit={submit}>
                    <TextField
                        label={t('common.email_address')}
                        type="email"
                        autoComplete="email"
                        autoFocus
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email}
                    />
                    <Button
                        type="submit"
                        size="large"
                        variant="contained"
                        disabled={form.processing}
                        startIcon={
                            form.processing ? (
                                <CircularProgress size={18} color="inherit" />
                            ) : undefined
                        }
                    >
                        {t('auth.email_reset_link')}
                    </Button>
                </Stack>
                <RouterLink href="/login" style={{ textDecoration: 'none' }}>
                    <Button
                        fullWidth
                        color="inherit"
                        startIcon={<ArrowBackRounded />}
                    >
                        {t('auth.back_to_sign_in')}
                    </Button>
                </RouterLink>
            </Stack>
        </AuthLayout>
    );
}
