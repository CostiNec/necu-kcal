import { Head, router, useForm, usePage } from '@inertiajs/react';
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded';
import LogoutRounded from '@mui/icons-material/LogoutRounded';
import SaveRounded from '@mui/icons-material/SaveRounded';
import {
    Alert,
    Box,
    Button,
    Card,
    CardContent,
    CardHeader,
    CircularProgress,
    Grid,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import {
    parseNumberInput,
    type NumberInputValue,
} from '@/lib/utils';
import type { NutritionTargets, SharedProps } from '@/types';

export default function SettingsIndex({
    profile,
    targets,
}: {
    profile: { timezone: string; unit_system: string };
    targets: NutritionTargets;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const profileForm = useForm({
        name: auth.user?.name ?? '',
        email: auth.user?.email ?? '',
    });
    const targetForm = useForm<{
        calories: NumberInputValue;
        protein: NumberInputValue;
        carbohydrates: NumberInputValue;
        fat: NumberInputValue;
        fibre: NumberInputValue;
        timezone: string;
    }>({
        calories: targets.calories,
        protein: targets.protein,
        carbohydrates: targets.carbohydrates,
        fat: targets.fat,
        fibre: targets.fibre,
        timezone: profile.timezone,
    });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const deleteForm = useForm({ current_password: '' });
    const targetFields = [
        ['calories', t('common.calories')],
        ['protein', t('settings.protein_g')],
        ['carbohydrates', t('settings.carbs_g')],
        ['fat', t('settings.fat_g')],
        ['fibre', t('settings.fibre_g')],
    ] as const;

    return (
        <AppLayout title={t('settings.title')} subtitle={t('settings.description')}>
            <Head title={t('settings.title')} />

            <Grid container spacing={2}>
                <Grid size={{ xs: 12, lg: 6 }}>
                    <Card sx={{ height: 1 }}>
                        <CardHeader title={t('settings.personal_information')} />
                        <CardContent>
                            <Stack
                                component="form"
                                spacing={2}
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    profileForm.put('/user/profile-information', {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <SettingsField
                                    label={t('common.name')}
                                    value={profileForm.data.name}
                                    error={profileForm.errors.name}
                                    onChange={(value) =>
                                        profileForm.setData('name', value)
                                    }
                                />
                                <SettingsField
                                    label={t('common.email')}
                                    type="email"
                                    value={profileForm.data.email}
                                    error={profileForm.errors.email}
                                    onChange={(value) =>
                                        profileForm.setData('email', value)
                                    }
                                />
                                <Box>
                                    <LoadingButton
                                        processing={profileForm.processing}
                                        label={t('settings.save_profile')}
                                    />
                                </Box>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid size={{ xs: 12, lg: 6 }}>
                    <Card sx={{ height: 1 }}>
                        <CardHeader title={t('settings.daily_targets')} />
                        <CardContent>
                            <Stack
                                component="form"
                                spacing={2}
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    targetForm.put('/settings/targets', {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <Grid container spacing={2}>
                                    {targetFields.map(([key, label]) => (
                                        <Grid key={key} size={{ xs: 6 }}>
                                            <TextField
                                                fullWidth
                                                label={label}
                                                type="number"
                                                value={targetForm.data[key]}
                                                error={Boolean(
                                                    targetForm.errors[key],
                                                )}
                                                helperText={
                                                    targetForm.errors[key]
                                                }
                                                slotProps={{
                                                    htmlInput: { min: 0 },
                                                }}
                                                onChange={(event) =>
                                                    targetForm.setData(
                                                        key,
                                                        parseNumberInput(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Grid>
                                    ))}
                                </Grid>
                                <TextField
                                    fullWidth
                                    label={t('common.timezone')}
                                    value={targetForm.data.timezone}
                                    error={Boolean(targetForm.errors.timezone)}
                                    helperText={targetForm.errors.timezone}
                                    onChange={(event) =>
                                        targetForm.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Box>
                                    <LoadingButton
                                        processing={targetForm.processing}
                                        label={t('settings.save_targets')}
                                    />
                                </Box>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid size={{ xs: 12, lg: 6 }}>
                    <Card sx={{ height: 1 }}>
                        <CardHeader title={t('settings.change_password')} />
                        <CardContent>
                            <Stack
                                component="form"
                                spacing={2}
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    passwordForm.put('/user/password', {
                                        preserveScroll: true,
                                        onSuccess: () => passwordForm.reset(),
                                    });
                                }}
                            >
                                <SettingsField
                                    label={t('common.current_password')}
                                    type="password"
                                    value={passwordForm.data.current_password}
                                    error={passwordForm.errors.current_password}
                                    onChange={(value) =>
                                        passwordForm.setData(
                                            'current_password',
                                            value,
                                        )
                                    }
                                />
                                <Grid container spacing={2}>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <SettingsField
                                            label={t('common.new_password')}
                                            type="password"
                                            value={passwordForm.data.password}
                                            error={passwordForm.errors.password}
                                            onChange={(value) =>
                                                passwordForm.setData(
                                                    'password',
                                                    value,
                                                )
                                            }
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <SettingsField
                                            label={t('common.confirm_password')}
                                            type="password"
                                            value={
                                                passwordForm.data
                                                    .password_confirmation
                                            }
                                            onChange={(value) =>
                                                passwordForm.setData(
                                                    'password_confirmation',
                                                    value,
                                                )
                                            }
                                        />
                                    </Grid>
                                </Grid>
                                <Box>
                                    <LoadingButton
                                        processing={passwordForm.processing}
                                        label={t('settings.update_password')}
                                    />
                                </Box>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid size={{ xs: 12, lg: 6 }}>
                    <Card sx={{ height: 1 }}>
                        <CardHeader title={t('settings.session')} />
                        <CardContent>
                            <Stack spacing={2} alignItems="flex-start">
                                <Typography variant="body2" color="text.secondary">
                                    {t('settings.session_copy')}
                                </Typography>
                                <Button
                                    variant="outlined"
                                    startIcon={<LogoutRounded />}
                                    onClick={() => router.post('/logout')}
                                >
                                    {t('common.sign_out')}
                                </Button>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid size={{ xs: 12 }}>
                    <Card
                        variant="outlined"
                        sx={{
                            borderColor: 'error.light',
                            bgcolor: 'error.lighter',
                        }}
                    >
                        <CardHeader
                            title={t('settings.delete_account')}
                            titleTypographyProps={{ color: 'error.dark' }}
                        />
                        <CardContent>
                            <Grid container spacing={2} alignItems="flex-end">
                                <Grid size={{ xs: 12, lg: 7 }}>
                                    <Alert severity="error" variant="outlined">
                                        {t('settings.delete_copy')}
                                    </Alert>
                                </Grid>
                                <Grid size={{ xs: 12, lg: 5 }}>
                                    <Stack
                                        component="form"
                                        spacing={2}
                                        onSubmit={(event: FormEvent) => {
                                            event.preventDefault();
                                            if (
                                                window.confirm(
                                                    t('settings.delete_confirm'),
                                                )
                                            ) {
                                                deleteForm.delete(
                                                    '/settings/account',
                                                );
                                            }
                                        }}
                                    >
                                        <SettingsField
                                            label={t('common.current_password')}
                                            type="password"
                                            value={deleteForm.data.current_password}
                                            error={
                                                deleteForm.errors.current_password
                                            }
                                            onChange={(value) =>
                                                deleteForm.setData(
                                                    'current_password',
                                                    value,
                                                )
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            color="error"
                                            variant="soft"
                                            fullWidth
                                            disabled={
                                                deleteForm.processing ||
                                                !deleteForm.data.current_password
                                            }
                                            startIcon={
                                                deleteForm.processing ? (
                                                    <CircularProgress
                                                        size={18}
                                                        color="inherit"
                                                    />
                                                ) : (
                                                    <DeleteOutlineRounded />
                                                )
                                            }
                                        >
                                            {t('settings.delete_button')}
                                        </Button>
                                    </Stack>
                                </Grid>
                            </Grid>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>
        </AppLayout>
    );
}

function SettingsField({
    label,
    type = 'text',
    value,
    error,
    onChange,
}: {
    label: string;
    type?: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            fullWidth
            label={label}
            type={type}
            value={value}
            error={Boolean(error)}
            helperText={error}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function LoadingButton({
    processing,
    label,
}: {
    processing: boolean;
    label: string;
}) {
    return (
        <Button
            type="submit"
            variant="soft"
            color="primary"
            disabled={processing}
            startIcon={
                processing ? (
                    <CircularProgress size={18} color="inherit" />
                ) : (
                    <SaveRounded />
                )
            }
        >
            {label}
        </Button>
    );
}
