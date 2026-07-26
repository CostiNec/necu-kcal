import * as React from 'react';
import { cn } from '@/lib/utils';

function Label({ className, ...props }: React.ComponentProps<'label'>) {
    return (
        <label
            className={cn(
                'text-[13px] font-semibold leading-none tracking-[-0.006em] text-foreground/85 peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
                className,
            )}
            {...props}
        />
    );
}

export { Label };
