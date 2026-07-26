import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';

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
            {status && (
                <div className="mb-5 rounded-xl bg-secondary p-4 text-sm text-secondary-foreground">
                    {status}
                </div>
            )}
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
                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('auth.email_reset_link')}
                </Button>
            </form>
            <Button variant="ghost" asChild className="mt-5 w-full">
                <Link href="/login">
                    <ArrowLeft />
                    {t('auth.back_to_sign_in')}
                </Link>
            </Button>
        </AuthLayout>
    );
}
