import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Check,
    CircleGauge,
    Search,
    Sparkles,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { SharedProps } from '@/types';

export default function Welcome() {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();

    return (
        <div className="relative isolate min-h-screen overflow-hidden">
            <Head title={t('landing.head_title')} />
            <header className="sticky top-0 z-20 border-b border-white/55 bg-background/60 backdrop-blur-2xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                    <BrandMark />
                    <div className="flex items-center gap-2">
                        <LanguageSwitcher compact />
                        {auth.user ? (
                            <Button asChild>
                                <Link href="/today">
                                    {t('landing.open_diary')} <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="ghost"
                                    asChild
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href="/login">
                                        {t('landing.sign_in')}
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href="/register">
                                        {t('landing.start_tracking')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main>
                <section className="page-enter relative mx-auto grid max-w-7xl items-center gap-14 px-5 pb-24 pt-14 sm:px-8 lg:grid-cols-[1fr_0.9fr] lg:pb-32 lg:pt-24">
                    <div className="absolute -left-48 -top-64 -z-10 size-[42rem] rounded-full bg-secondary/70 blur-3xl" />
                    <div className="absolute -right-64 top-12 -z-10 size-[38rem] rounded-full bg-primary/8 blur-3xl" />
                    <div className="stagger-in">
                        <div className="glass-subtle mb-6 inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-semibold">
                            <Sparkles className="size-4 text-primary" />
                            {t('landing.badge')}
                        </div>
                        <h1 className="text-balance text-5xl font-semibold leading-[1.01] tracking-[-0.055em] sm:text-6xl lg:text-[4.6rem]">
                            {t('landing.headline')}
                        </h1>
                        <p className="mt-7 max-w-xl text-lg leading-8 text-muted-foreground">
                            {t('landing.description')}
                        </p>
                        <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Button size="lg" asChild>
                                <Link href={auth.user ? '/today' : '/register'}>
                                    {auth.user
                                        ? t('landing.open_your_diary')
                                        : t('landing.create_free_account')}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            {!auth.user && (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href="/login">
                                        {t('landing.already_have_account')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <div className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                            {[
                                'landing.private_default',
                                'landing.no_subscription',
                                'landing.mobile_first',
                            ].map((key) => (
                                    <span
                                        key={key}
                                        className="glass-subtle flex items-center gap-2 rounded-full px-3 py-1.5"
                                    >
                                        <span className="grid size-5 place-items-center rounded-full bg-secondary text-primary">
                                            <Check className="size-3.5" />
                                        </span>
                                        {t(key)}
                                    </span>
                                ))}
                        </div>
                    </div>

                    <div className="auth-card-enter relative mx-auto w-full max-w-md">
                        <div className="absolute -inset-10 -z-10 rounded-[3rem] bg-primary/10 blur-3xl" />
                        <Card className="relative overflow-hidden border-primary/12 p-5 shadow-[0_32px_80px_rgb(22_84_61_/_0.16)] before:pointer-events-none before:absolute before:-right-20 before:-top-24 before:size-64 before:rounded-full before:bg-secondary/80 before:blur-3xl sm:p-6">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {t('common.today')}
                                    </p>
                                    <h2 className="mt-1 text-2xl font-semibold">
                                        {t('landing.good_afternoon')}
                                    </h2>
                                </div>
                                <span className="soft-well rounded-full px-3 py-1 text-xs font-semibold text-primary">
                                    {t('landing.on_track')}
                                </span>
                            </div>
                            <div className="my-7 grid place-items-center">
                                <div className="relative grid size-48 place-items-center rounded-full bg-[conic-gradient(var(--primary)_0_68%,var(--secondary)_68%_100%)]">
                                    <div className="grid size-40 place-items-center rounded-full bg-card text-center">
                                        <div>
                                            <p className="text-4xl font-semibold tracking-tight">
                                                1,368
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {t('landing.of_target', {
                                                    target: '2,000',
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    ['common.protein', '84 / 120g', 'var(--protein)'],
                                    ['common.carbs', '146 / 220g', 'var(--carbs)'],
                                    ['common.fat', '42 / 65g', 'var(--fat)'],
                                ].map(([label, value, color]) => (
                                    <div
                                        key={label}
                                        className="soft-well rounded-2xl p-3"
                                    >
                                        <span
                                            className="mb-2 block size-2 rounded-full"
                                            style={{ background: color }}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t(label)}
                                        </p>
                                        <p className="mt-0.5 text-xs font-semibold">{value}</p>
                                    </div>
                                ))}
                            </div>
                            <Button className="mt-5 w-full">
                                <Search /> {t('landing.add_food')}
                            </Button>
                        </Card>
                    </div>
                </section>

                <section className="border-y border-white/60 bg-card/48 backdrop-blur-xl">
                    <div className="stagger-in mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 md:grid-cols-3">
                        {[
                            [
                                Search,
                                'landing.feature_log_title',
                                'landing.feature_log_copy',
                            ],
                            [
                                CircleGauge,
                                'landing.feature_targets_title',
                                'landing.feature_targets_copy',
                            ],
                            [
                                BarChart3,
                                'landing.feature_reports_title',
                                'landing.feature_reports_copy',
                            ],
                        ].map(([Icon, title, copy]) => {
                            const ItemIcon = Icon as typeof Search;
                            return (
                                <div
                                    key={title as string}
                                    className="glass-subtle rounded-2xl p-5 transition-[transform,box-shadow] hover:-translate-y-1 hover:shadow-[0_18px_42px_rgb(25_72_55_/_0.08)]"
                                >
                                    <div className="soft-well mb-4 grid size-11 place-items-center rounded-xl text-primary">
                                        <ItemIcon />
                                    </div>
                                    <h3 className="font-semibold">
                                        {t(title as string)}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {t(copy as string)}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>
            </main>
        </div>
    );
}
