import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';

export default function Register() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
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
            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="name">{t('auth.your_name')}</Label>
                    <Input
                        id="name"
                        autoComplete="name"
                        autoFocus
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                    <FieldError message={form.errors.name} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="email">{t('common.email_address')}</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                    <FieldError message={form.errors.email} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="password">{t('common.password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">
                            {t('auth.confirm')}
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>
                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('auth.create_account')}
                </Button>
            </form>
            <p className="mt-7 text-center text-sm text-muted-foreground">
                {t('auth.already_registered')}{' '}
                <Link href="/login" className="font-semibold text-primary hover:underline">
                    {t('auth.sign_in')}
                </Link>
            </p>
        </AuthLayout>
    );
}
