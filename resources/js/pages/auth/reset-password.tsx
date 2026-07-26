import { Head, useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';

export default function ResetPassword({
    email,
    token,
}: {
    email: string;
    token: string;
}) {
    const { t } = useTranslation();
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t('auth.choose_password')}
            description={t('auth.choose_password_description')}
        >
            <Head title={t('auth.reset_password')} />
            <Stack component="form" spacing={2.5} onSubmit={submit}>
                <TextField
                    label={t('common.email_address')}
                    type="email"
                    value={form.data.email}
                    slotProps={{ input: { readOnly: true } }}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email}
                />
                <TextField
                    label={t('common.new_password')}
                    type="password"
                    autoFocus
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    error={Boolean(form.errors.password)}
                    helperText={form.errors.password}
                />
                <TextField
                    label={t('common.confirm_password')}
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(event) =>
                        form.setData('password_confirmation', event.target.value)
                    }
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
                    {t('auth.reset_password')}
                </Button>
            </Stack>
        </AuthLayout>
    );
}
