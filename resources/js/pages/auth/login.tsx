import { Head, useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import CircularProgress from '@mui/material/CircularProgress';
import FormControlLabel from '@mui/material/FormControlLabel';
import MuiLink from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { RouterLink } from '@/components/router-link';

export default function Login() {
    const { t } = useTranslation();
    const form = useForm({ email: '', password: '', remember: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout
            title={t('auth.welcome_back')}
            description={t('auth.login_description')}
        >
            <Head title={t('auth.sign_in')} />
            <Stack component="form" spacing={2.5} onSubmit={submit}>
                <TextField
                    label={t('common.email_address')}
                    type="email"
                    autoComplete="email"
                    autoFocus
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email}
                />
                <TextField
                    label={t('common.password')}
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    error={Boolean(form.errors.password)}
                    helperText={form.errors.password}
                />
                <Stack
                    direction="row"
                    alignItems="center"
                    justifyContent="space-between"
                >
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={form.data.remember}
                                onChange={(event) =>
                                    form.setData('remember', event.target.checked)
                                }
                            />
                        }
                        label={t('auth.keep_signed_in')}
                    />
                    <RouterLink href="/forgot-password">
                        <MuiLink component="span" variant="subtitle2">
                            {t('auth.forgot_password')}
                        </MuiLink>
                    </RouterLink>
                </Stack>
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
                    {t('auth.sign_in')}
                </Button>
            </Stack>
            <Typography
                variant="body2"
                color="text.secondary"
                textAlign="center"
                sx={{ mt: 3 }}
            >
                {t('auth.new_here')}{' '}
                <RouterLink href="/register">
                    <MuiLink component="span" fontWeight={700}>
                        {t('auth.create_account')}
                    </MuiLink>
                </RouterLink>
            </Typography>
        </AuthLayout>
    );
}
