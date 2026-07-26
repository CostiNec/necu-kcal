import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function PeriodNavigator({
    title,
    subtitle,
    previousHref,
    nextHref,
    previousLabel,
    nextLabel,
}: {
    title: string;
    subtitle: string;
    previousHref: string;
    nextHref: string;
    previousLabel: string;
    nextLabel: string;
}) {
    return (
        <div className="glass-subtle mb-6 flex items-center justify-between rounded-2xl p-2">
            <Button
                variant="ghost"
                size="icon"
                asChild
                aria-label={previousLabel}
            >
                <Link href={previousHref}>
                    <ChevronLeft />
                </Link>
            </Button>
            <div className="text-center">
                <p className="text-sm font-semibold">{title}</p>
                <p className="text-xs text-muted-foreground">{subtitle}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                asChild
                aria-label={nextLabel}
            >
                <Link href={nextHref}>
                    <ChevronRight />
                </Link>
            </Button>
        </div>
    );
}
