import { Head, useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';

export default function TwoFactorChallenge() {
    const { t } = useTranslation();
    const form = useForm({ code: '', recovery_code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/two-factor-challenge');
    };

    return (
        <AuthLayout
            title={t('auth.two_factor_title')}
            description={t('auth.two_factor_description')}
        >
            <Head title={t('auth.two_factor_head')} />
            <Stack component="form" spacing={2.5} onSubmit={submit}>
                <TextField
                    label={t('auth.authentication_code')}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    autoFocus
                    value={form.data.code}
                    onChange={(event) => form.setData('code', event.target.value)}
                    error={Boolean(form.errors.code)}
                    helperText={form.errors.code}
                />
                <Button
                    variant="contained"
                    type="submit"
                    disabled={form.processing}
                >
                    {t('common.continue')}
                </Button>
            </Stack>
        </AuthLayout>
    );
}
