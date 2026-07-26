import { Head, router } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth.verify_title')}
            description={t('auth.verify_description')}
        >
            <Head title={t('auth.verify_head')} />
            <div className="mb-6 grid size-14 place-items-center rounded-2xl bg-secondary text-primary">
                <MailCheck className="size-7" />
            </div>
            {status === 'verification-link-sent' && (
                <p className="mb-5 rounded-xl bg-secondary p-4 text-sm">
                    {t('auth.verification_sent')}
                </p>
            )}
            <div className="space-y-3">
                <Button
                    className="w-full"
                    onClick={() => router.post('/email/verification-notification')}
                >
                    {t('auth.resend_verification')}
                </Button>
                <Button
                    variant="ghost"
                    className="w-full"
                    onClick={() => router.post('/logout')}
                >
                    {t('common.sign_out')}
                </Button>
            </div>
        </AuthLayout>
    );
}
