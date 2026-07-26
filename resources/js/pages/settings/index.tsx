import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, LogOut, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';
import type { NutritionTargets, SharedProps } from '@/types';
import { useTranslation } from 'react-i18next';

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
    const targetForm = useForm({
        calories: targets.calories,
        protein: targets.protein,
        carbohydrates: targets.carbohydrates,
        fat: targets.fat,
        timezone: profile.timezone,
    });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const deleteForm = useForm({
        current_password: '',
    });

    return (
        <AppLayout
            title={t('settings.title')}
            subtitle={t('settings.description')}
        >
            <Head title={t('settings.title')} />
            <div className="stagger-in grid gap-6 lg:grid-cols-2">
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-white/60 bg-white/18">
                        <CardTitle>{t('settings.personal_information')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                profileForm.put('/user/profile-information', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <SettingsField
                                label={t('common.name')}
                                id="profile-name"
                                value={profileForm.data.name}
                                error={profileForm.errors.name}
                                onChange={(value) =>
                                    profileForm.setData('name', value)
                                }
                            />
                            <SettingsField
                                label={t('common.email')}
                                id="profile-email"
                                type="email"
                                value={profileForm.data.email}
                                error={profileForm.errors.email}
                                onChange={(value) =>
                                    profileForm.setData('email', value)
                                }
                            />
                            <Button type="submit" disabled={profileForm.processing}>
                                {profileForm.processing ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Save />
                                )}
                                {t('settings.save_profile')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-white/60 bg-white/18">
                        <CardTitle>{t('settings.daily_targets')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                targetForm.put('/settings/targets', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid grid-cols-2 gap-4">
                                {[
                                    ['calories', t('common.calories')],
                                    ['protein', t('settings.protein_g')],
                                    ['carbohydrates', t('settings.carbs_g')],
                                    ['fat', t('settings.fat_g')],
                                ].map(([key, label]) => (
                                    <div className="space-y-2" key={key}>
                                        <Label htmlFor={`target-${key}`}>{label}</Label>
                                        <Input
                                            id={`target-${key}`}
                                            type="number"
                                            min="0"
                                            value={
                                                targetForm.data[
                                                    key as keyof NutritionTargets
                                                ]
                                            }
                                            onChange={(event) =>
                                                targetForm.setData(
                                                    key as keyof NutritionTargets,
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                        <FieldError
                                            message={
                                                targetForm.errors[
                                                    key as keyof NutritionTargets
                                                ]
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="timezone">
                                    {t('common.timezone')}
                                </Label>
                                <Input
                                    id="timezone"
                                    value={targetForm.data.timezone}
                                    onChange={(event) =>
                                        targetForm.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError message={targetForm.errors.timezone} />
                            </div>
                            <Button type="submit" disabled={targetForm.processing}>
                                {targetForm.processing ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Save />
                                )}
                                {t('settings.save_targets')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-white/60 bg-white/18">
                        <CardTitle>{t('settings.change_password')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                passwordForm.put('/user/password', {
                                    preserveScroll: true,
                                    onSuccess: () => passwordForm.reset(),
                                });
                            }}
                        >
                            <SettingsField
                                label={t('common.current_password')}
                                id="current-password"
                                type="password"
                                value={passwordForm.data.current_password}
                                error={passwordForm.errors.current_password}
                                onChange={(value) =>
                                    passwordForm.setData('current_password', value)
                                }
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label={t('common.new_password')}
                                    id="new-password"
                                    type="password"
                                    value={passwordForm.data.password}
                                    error={passwordForm.errors.password}
                                    onChange={(value) =>
                                        passwordForm.setData('password', value)
                                    }
                                />
                                <SettingsField
                                    label={t('common.confirm_password')}
                                    id="confirm-password"
                                    type="password"
                                    value={passwordForm.data.password_confirmation}
                                    onChange={(value) =>
                                        passwordForm.setData(
                                            'password_confirmation',
                                            value,
                                        )
                                    }
                                />
                            </div>
                            <Button type="submit" disabled={passwordForm.processing}>
                                {t('settings.update_password')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-white/60 bg-white/18">
                        <CardTitle>{t('settings.session')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="mb-5 text-sm leading-6 text-muted-foreground">
                            {t('settings.session_copy')}
                        </p>
                        <Button
                            variant="outline"
                            onClick={() => router.post('/logout')}
                        >
                            <LogOut />
                            {t('common.sign_out')}
                        </Button>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden border-destructive/20 bg-destructive/[0.018] lg:col-span-2">
                    <CardHeader className="border-b border-destructive/10 bg-destructive/[0.025]">
                        <CardTitle>{t('settings.delete_account')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-5 lg:grid-cols-[1fr_22rem] lg:items-end">
                            <div>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {t('settings.delete_copy')}
                                </p>
                            </div>
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (
                                        !window.confirm(
                                            t('settings.delete_confirm'),
                                        )
                                    ) {
                                        return;
                                    }

                                    deleteForm.delete('/settings/account');
                                }}
                            >
                                <SettingsField
                                    label={t('common.current_password')}
                                    id="delete-current-password"
                                    type="password"
                                    value={deleteForm.data.current_password}
                                    error={deleteForm.errors.current_password}
                                    onChange={(value) =>
                                        deleteForm.setData('current_password', value)
                                    }
                                />
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={
                                        deleteForm.processing ||
                                        !deleteForm.data.current_password
                                    }
                                    className="w-full"
                                >
                                    {deleteForm.processing ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Trash2 />
                                    )}
                                    {t('settings.delete_button')}
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function SettingsField({
    label,
    id,
    type = 'text',
    value,
    error,
    onChange,
}: {
    label: string;
    id: string;
    type?: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError message={error} />
        </div>
    );
}
