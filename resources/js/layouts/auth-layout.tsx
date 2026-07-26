import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';

export function AuthLayout({
    children,
    title,
    description,
}: PropsWithChildren<{ title: string; description: string }>) {
    const { t } = useTranslation();

    return (
        <main className="relative isolate grid min-h-screen overflow-hidden lg:grid-cols-[0.95fr_1.05fr]">
            <div className="pointer-events-none absolute -left-40 -top-52 -z-10 size-[34rem] rounded-full bg-secondary/70 blur-3xl" />
            <section className="flex min-h-screen flex-col px-5 py-6 sm:px-8 lg:px-12 xl:px-16">
                <div className="flex items-center justify-between gap-4">
                    <Link href="/" className="w-fit">
                        <BrandMark />
                    </Link>
                    <LanguageSwitcher compact />
                </div>
                <div className="stagger-in mx-auto flex w-full max-w-[29rem] flex-1 flex-col justify-center py-10">
                    <div className="mb-7 px-1">
                        <h1 className="text-3xl font-semibold tracking-[-0.04em] sm:text-[2.55rem]">
                            {title}
                        </h1>
                        <p className="mt-3 text-base leading-7 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <div className="auth-card-enter glass-panel rounded-3xl p-5 sm:p-7">
                        {children}
                    </div>
                </div>
                <p className="text-center text-xs text-muted-foreground sm:text-left">
                    {t('layout.private_data')}
                </p>
            </section>
            <section className="auth-visual-enter relative m-3 hidden overflow-hidden rounded-3xl border border-white/20 bg-[radial-gradient(circle_at_78%_12%,rgb(255_255_255_/_0.18),transparent_25rem),linear-gradient(145deg,color-mix(in_oklch,var(--primary)_78%,white),color-mix(in_oklch,var(--primary)_82%,black))] p-12 text-primary-foreground shadow-[inset_0_1px_0_rgb(255_255_255_/_0.24),0_24px_70px_rgb(20_72_52_/_0.22)] lg:flex lg:flex-col lg:justify-end xl:p-16">
                <div className="absolute -right-28 -top-28 size-[32rem] rounded-full border border-white/14" />
                <div className="absolute -right-4 top-24 size-80 rounded-full border border-white/10" />
                <div className="absolute left-12 top-12 size-36 rounded-full bg-white/8 blur-2xl" />
                <div className="relative max-w-xl rounded-3xl border border-white/14 bg-white/8 p-7 shadow-[inset_0_1px_0_rgb(255_255_255_/_0.16)] backdrop-blur-md">
                    <span className="mb-5 inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-sm font-medium shadow-[inset_0_1px_0_rgb(255_255_255_/_0.14)]">
                        {t('layout.promo_badge')}
                    </span>
                    <blockquote className="text-balance text-4xl font-medium leading-tight tracking-[-0.04em]">
                        {t('layout.promo_quote')}
                    </blockquote>
                    <p className="mt-6 max-w-md text-base leading-7 text-white/70">
                        {t('layout.promo_copy')}
                    </p>
                </div>
            </section>
        </main>
    );
}
