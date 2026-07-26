import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';

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
            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-2">
                    <Label htmlFor="code">
                        {t('auth.authentication_code')}
                    </Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        autoFocus
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                    />
                    <FieldError message={form.errors.code} />
                </div>
                <Button className="w-full" type="submit" disabled={form.processing}>
                    {t('common.continue')}
                </Button>
            </form>
        </AuthLayout>
    );
}
