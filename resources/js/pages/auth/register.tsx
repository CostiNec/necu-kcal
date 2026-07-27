import { Head, useForm } from '@inertiajs/react';
import VisibilityOffRounded from '@mui/icons-material/VisibilityOffRounded';
import VisibilityRounded from '@mui/icons-material/VisibilityRounded';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import MuiLink from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { RouterLink } from '@/components/router-link';

export default function Register() {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] =
        useState(false);
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t('auth.start_diary')}
            description={t('auth.register_description')}
        >
            <Head title={t('auth.create_account')} />
            <Stack component="form" spacing={2} onSubmit={submit}>
                <TextField
                    label={t('auth.your_name')}
                    autoComplete="name"
                    autoFocus
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    error={Boolean(form.errors.name)}
                    helperText={form.errors.name}
                />
                <TextField
                    label={t('auth.username')}
                    autoComplete="username"
                    value={form.data.username}
                    onChange={(event) =>
                        form.setData('username', event.target.value)
                    }
                    error={Boolean(form.errors.username)}
                    helperText={
                        form.errors.username ?? t('auth.username_help')
                    }
                />
                <TextField
                    label={t('common.email_address')}
                    type="email"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email}
                />
                <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6 }}>
                        <TextField
                            label={t('common.password')}
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            error={Boolean(form.errors.password)}
                            helperText={form.errors.password}
                            slotProps={{
                                input: {
                                    endAdornment: (
                                        <InputAdornment position="end">
                                            <IconButton
                                                aria-label={t(
                                                    showPassword
                                                        ? 'common.hide_password'
                                                        : 'common.show_password',
                                                )}
                                                edge="end"
                                                onClick={() =>
                                                    setShowPassword(
                                                        (visible) => !visible,
                                                    )
                                                }
                                            >
                                                {showPassword ? (
                                                    <VisibilityOffRounded />
                                                ) : (
                                                    <VisibilityRounded />
                                                )}
                                            </IconButton>
                                        </InputAdornment>
                                    ),
                                },
                            }}
                        />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                        <TextField
                            label={t('common.confirm_password')}
                            type={
                                showPasswordConfirmation ? 'text' : 'password'
                            }
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            slotProps={{
                                input: {
                                    endAdornment: (
                                        <InputAdornment position="end">
                                            <IconButton
                                                aria-label={t(
                                                    showPasswordConfirmation
                                                        ? 'common.hide_password_confirmation'
                                                        : 'common.show_password_confirmation',
                                                )}
                                                edge="end"
                                                onClick={() =>
                                                    setShowPasswordConfirmation(
                                                        (visible) => !visible,
                                                    )
                                                }
                                            >
                                                {showPasswordConfirmation ? (
                                                    <VisibilityOffRounded />
                                                ) : (
                                                    <VisibilityRounded />
                                                )}
                                            </IconButton>
                                        </InputAdornment>
                                    ),
                                },
                            }}
                        />
                    </Grid>
                </Grid>
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
                    {t('auth.create_account')}
                </Button>
            </Stack>
            <Typography
                variant="body2"
                color="text.secondary"
                textAlign="center"
                sx={{ mt: 3 }}
            >
                {t('auth.already_registered')}{' '}
                <RouterLink href="/login">
                    <MuiLink component="span" fontWeight={700}>
                        {t('auth.sign_in')}
                    </MuiLink>
                </RouterLink>
            </Typography>
        </AuthLayout>
    );
}
