import * as React from 'react';
import * as ProgressPrimitive from '@radix-ui/react-progress';
import { cn } from '@/lib/utils';

function Progress({
    className,
    value,
    indicatorClassName,
    ...props
}: React.ComponentProps<typeof ProgressPrimitive.Root> & {
    indicatorClassName?: string;
}) {
    const safeValue = Math.max(0, Math.min(100, value ?? 0));

    return (
        <ProgressPrimitive.Root
            className={cn(
                'relative h-2 w-full overflow-hidden rounded-full bg-secondary shadow-[inset_0_1px_2px_rgb(24_70_53_/_0.1),0_1px_0_rgb(255_255_255_/_0.7)]',
                className,
            )}
            {...props}
        >
            <ProgressPrimitive.Indicator
                className={cn(
                    'progress-enter relative h-full w-full flex-1 rounded-full bg-primary shadow-[inset_0_1px_0_rgb(255_255_255_/_0.35)] transition-transform duration-500 after:absolute after:inset-x-0 after:top-0 after:h-1/2 after:rounded-full after:bg-white/18',
                    indicatorClassName,
                )}
                style={{ transform: `translateX(-${100 - safeValue}%)` }}
            />
        </ProgressPrimitive.Root>
    );
}

export { Progress };
