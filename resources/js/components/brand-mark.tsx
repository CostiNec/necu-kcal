import { Flame } from 'lucide-react';
import { cn } from '@/lib/utils';

export function BrandMark({ className }: { className?: string }) {
    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <span className="grid size-10 place-items-center rounded-xl border border-primary/12 bg-secondary/78 text-primary shadow-[inset_0_1px_0_rgb(255_255_255_/_0.72),0_3px_10px_rgb(22_86_62_/_0.09)]">
                <Flame className="size-[1.2rem] stroke-[1.8]" />
            </span>
            <span className="text-lg font-semibold tracking-[-0.025em]">
                Kcal
            </span>
        </div>
    );
}
