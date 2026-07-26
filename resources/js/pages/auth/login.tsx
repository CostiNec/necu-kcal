import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';

export default function Login() {
    const { t } = useTranslation();
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

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
            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="email">{t('common.email_address')}</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                    <FieldError message={form.errors.email} />
                </div>
                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="password">{t('common.password')}</Label>
                        <Link
                            href="/forgot-password"
                            className="text-sm font-semibold text-primary hover:underline"
                        >
                            {t('auth.forgot_password')}
                        </Link>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                    />
                    <FieldError message={form.errors.password} />
                </div>
                <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                    <input
                        type="checkbox"
                        className="size-4 rounded border-input accent-[var(--primary)]"
                        checked={form.data.remember}
                        onChange={(event) =>
                            form.setData('remember', event.target.checked)
                        }
                    />
                    {t('auth.keep_signed_in')}
                </label>
                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('auth.sign_in')}
                </Button>
            </form>
            <p className="mt-7 text-center text-sm text-muted-foreground">
                {t('auth.new_here')}{' '}
                <Link href="/register" className="font-semibold text-primary hover:underline">
                    {t('auth.create_account')}
                </Link>
            </p>
        </AuthLayout>
    );
}
