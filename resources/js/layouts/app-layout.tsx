import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    LogOut,
    Settings,
    Utensils,
} from 'lucide-react';
import { useEffect, type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import type { SharedProps } from '@/types';
import { cn } from '@/lib/utils';

const navigation = [
    { labelKey: 'common.today', href: '/today', icon: Utensils, match: ['/today', '/diary'] },
    { labelKey: 'common.foods', href: '/foods', icon: BookOpen, match: ['/foods'] },
    { labelKey: 'common.reports', href: '/reports', icon: BarChart3, match: ['/reports'] },
    { labelKey: 'common.profile', href: '/settings', icon: Settings, match: ['/settings'] },
];

export function AppLayout({
    children,
    title,
    subtitle,
    actions,
}: PropsWithChildren<{
    title?: string;
    subtitle?: string;
    actions?: React.ReactNode;
}>) {
    const page = usePage<SharedProps>();
    const { auth, flash } = page.props;
    const { t } = useTranslation();

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.error, flash.success]);

    const isActive = (matches: string[]) =>
        matches.some((path) => page.url === path || page.url.startsWith(`${path}/`));

    return (
        <div className="relative isolate min-h-screen overflow-x-clip">
            <div
                aria-hidden="true"
                className="ambient-orb pointer-events-none fixed -right-32 top-24 -z-10 size-[30rem] rounded-full bg-secondary/55 blur-3xl"
            />
            <div
                aria-hidden="true"
                className="ambient-orb-reverse pointer-events-none fixed bottom-12 left-48 -z-10 size-80 rounded-full bg-primary/5 blur-3xl"
            />

            <aside className="fixed inset-y-0 left-0 z-30 hidden w-72 border-r border-white/65 bg-card/62 shadow-[10px_0_40px_rgb(27_75_58_/_0.035)] backdrop-blur-2xl lg:flex lg:flex-col">
                <div className="flex h-24 items-center px-7">
                    <BrandMark />
                </div>
                <nav className="flex-1 space-y-1.5 px-4 py-3">
                    {navigation.map((item) => {
                        const active = isActive(item.match);
                        const Icon = item.icon;

                        return (
                            <Button
                                key={item.href}
                                asChild
                                variant={active ? 'secondary' : 'ghost'}
                                className="group h-11 w-full justify-start gap-3 px-3"
                            >
                                <Link href={item.href}>
                                    <span
                                        className={cn(
                                            'grid size-8 place-items-center rounded-md',
                                            active
                                                ? 'bg-background text-primary'
                                                : 'text-muted-foreground group-hover:text-foreground',
                                        )}
                                    >
                                        <Icon className="size-[1.1rem] stroke-[1.8]" />
                                    </span>
                                    {t(item.labelKey)}
                                </Link>
                            </Button>
                        );
                    })}
                </nav>
                <div className="border-t border-white/60 p-4">
                    <div className="glass-subtle mb-2 rounded-2xl p-3">
                        <div className="flex items-center gap-3">
                            <div className="grid size-10 place-items-center rounded-full border border-white/70 bg-secondary text-sm font-semibold shadow-[inset_0_1px_0_rgb(255_255_255_/_0.8)]">
                                {auth.user?.name.slice(0, 1).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">
                                    {auth.user?.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {auth.user?.email}
                                </p>
                            </div>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        className="w-full justify-start text-muted-foreground"
                        onClick={() => router.post('/logout')}
                    >
                        <LogOut />
                        {t('common.sign_out')}
                    </Button>
                </div>
            </aside>

            <div className="lg:pl-72">
                <header className="sticky top-0 z-20 border-b border-white/60 bg-background/68 shadow-[0_1px_0_rgb(255_255_255_/_0.7)] backdrop-blur-2xl">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-24 lg:px-8">
                        <BrandMark className="lg:hidden" />
                        <div className="hidden min-w-0 lg:block">
                            {title && (
                                <h1 className="truncate text-[1.35rem] font-semibold tracking-[-0.025em]">
                                    {title}
                                </h1>
                            )}
                            {subtitle && (
                                <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <LanguageSwitcher compact />
                            {actions}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                                aria-label={t('common.sign_out')}
                                onClick={() => router.post('/logout')}
                            >
                                <LogOut />
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="page-enter content-stagger mx-auto max-w-7xl px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12 lg:pt-8">
                    {(title || subtitle) && (
                        <div className="mb-6 lg:hidden">
                            {title && (
                                <h1 className="text-[1.7rem] font-semibold tracking-[-0.035em]">
                                    {title}
                                </h1>
                            )}
                            {subtitle && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            <nav className="mobile-tab-glass fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 rounded-3xl p-1.5 lg:hidden">
                <div className="relative z-10 mx-auto grid max-w-md grid-cols-4">
                    {navigation.map((item) => {
                        const active = isActive(item.match);
                        const Icon = item.icon;

                        return (
                            <Button
                                key={item.href}
                                asChild
                                variant={active ? 'secondary' : 'ghost'}
                                className="h-14 flex-col gap-1 px-2 text-[11px]"
                            >
                                <Link href={item.href}>
                                    <Icon className="size-5 stroke-[1.8]" />
                                    {t(item.labelKey)}
                                </Link>
                            </Button>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
