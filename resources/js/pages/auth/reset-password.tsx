import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';

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
            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="email">{t('common.email_address')}</Label>
                    <Input id="email" type="email" value={form.data.email} readOnly />
                    <FieldError message={form.errors.email} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="password">{t('common.new_password')}</Label>
                    <Input
                        id="password"
                        type="password"
                        autoFocus
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
                        {t('common.confirm_password')}
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
                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('auth.reset_password')}
                </Button>
            </form>
        </AuthLayout>
    );
}
