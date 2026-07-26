import { Progress } from '@/components/ui/progress';
import { formatNumber } from '@/lib/utils';

const colors = {
    protein: 'bg-[var(--protein)]',
    carbohydrates: 'bg-[var(--carbs)]',
    fat: 'bg-[var(--fat)]',
};

export function MacroProgress({
    label,
    type,
    value,
    target,
}: {
    label: string;
    type: keyof typeof colors;
    value: number;
    target: number;
}) {
    const percentage = target > 0 ? (value / target) * 100 : 0;

    return (
        <div className="space-y-2">
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm font-medium">{label}</span>
                <span className="text-xs text-muted-foreground">
                    <strong className="font-semibold text-foreground">
                        {formatNumber(value)}
                    </strong>{' '}
                    / {formatNumber(target)} g
                </span>
            </div>
            <Progress value={percentage} indicatorClassName={colors[type]} />
        </div>
    );
}
