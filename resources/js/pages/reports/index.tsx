import { Head, Link } from '@inertiajs/react';
import { Flame, Target } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PeriodNavigator } from '@/components/period-navigator';
import type { NutritionTargets } from '@/types';
import { formatDate, formatNumber } from '@/lib/utils';

type ChartPoint = {
    date: string;
    day: string;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
};

export default function ReportsIndex({
    week,
    chart,
    averages,
    loggedDays,
    topFoods,
    targets,
}: {
    week: {
        start: string;
        end: string;
        previous: string;
        next: string;
    };
    chart: ChartPoint[];
    averages: NutritionTargets;
    loggedDays: number;
    topFoods: { name: string; times: number; calories: number }[];
    targets: NutritionTargets;
}) {
    const { t } = useTranslation();
    const localizedChart = chart.map((point) => ({
        ...point,
        day: formatDate(point.date, {
            weekday: 'short',
            month: undefined,
            day: undefined,
            year: undefined,
        }),
    }));

    return (
        <AppLayout
            title={t('reports.title')}
            subtitle={t('reports.description')}
        >
            <Head title={t('common.reports')} />

            <PeriodNavigator
                title={t('reports.week_overview')}
                subtitle={`${formatDate(week.start, { year: undefined })} – ${formatDate(week.end, { year: undefined })}`}
                previousHref={`/reports?week=${week.previous}`}
                nextHref={`/reports?week=${week.next}`}
                previousLabel={t('common.previous_week')}
                nextLabel={t('common.next_week')}
            />

            <div className="stagger-in grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label={t('reports.daily_average')}
                    value={`${formatNumber(averages.calories)} kcal`}
                    context={t('reports.days_logged', { count: loggedDays })}
                />
                <StatCard
                    label={t('reports.protein_average')}
                    value={`${formatNumber(averages.protein, 1)} g`}
                    context={t('reports.target', {
                        target: formatNumber(targets.protein),
                    })}
                    color="var(--protein)"
                />
                <StatCard
                    label={t('reports.carb_average')}
                    value={`${formatNumber(averages.carbohydrates, 1)} g`}
                    context={t('reports.target', {
                        target: formatNumber(targets.carbohydrates),
                    })}
                    color="var(--carbs)"
                />
                <StatCard
                    label={t('reports.fat_average')}
                    value={`${formatNumber(averages.fat, 1)} g`}
                    context={t('reports.target', {
                        target: formatNumber(targets.fat),
                    })}
                    color="var(--fat)"
                />
            </div>

            <div className="stagger-in mt-5 grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
                <Card className="overflow-hidden">
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle>
                                    {t('reports.calories_by_day')}
                                </CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('reports.calories_comparison', {
                                        target: formatNumber(targets.calories),
                                    })}
                                </p>
                            </div>
                            <div className="soft-well grid size-10 place-items-center rounded-xl text-primary">
                                <Flame />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            className="h-72 w-full"
                            role="img"
                            aria-label={t('reports.calorie_chart')}
                        >
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart
                                    data={localizedChart}
                                    margin={{ top: 10, right: 6, left: -22, bottom: 0 }}
                                >
                                    <defs>
                                        <linearGradient
                                            id="calorieFill"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="var(--primary)"
                                                stopOpacity={0.28}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="var(--primary)"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid
                                        vertical={false}
                                        stroke="var(--border)"
                                    />
                                    <XAxis
                                        dataKey="day"
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                                    />
                                    <YAxis
                                        domain={[
                                            0,
                                            Math.max(
                                                targets.calories,
                                                ...chart.map((point) => point.calories),
                                            ),
                                        ]}
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                    />
                                    <Tooltip content={<ChartTooltip unit="kcal" />} />
                                    <ReferenceLine
                                        y={targets.calories}
                                        stroke="var(--muted-foreground)"
                                        strokeDasharray="4 4"
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="calories"
                                        stroke="var(--primary)"
                                        strokeWidth={3}
                                        fill="url(#calorieFill)"
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>{t('reports.most_logged')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {topFoods.length === 0 ? (
                            <div className="soft-well rounded-2xl py-10 text-center">
                                <Target className="mx-auto size-8 text-primary/70" />
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {t('reports.empty_patterns')}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-5">
                                {topFoods.map((food, index) => (
                                    <div
                                        key={food.name}
                                        className="flex items-center gap-3 rounded-xl p-1.5 transition-colors hover:bg-white/45"
                                    >
                                        <span className="soft-well grid size-8 shrink-0 place-items-center rounded-lg text-xs font-semibold">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">
                                                {food.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('reports.times', {
                                                    count: food.times,
                                                })}
                                            </p>
                                        </div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {formatNumber(food.calories)} kcal
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-5 overflow-hidden">
                <CardHeader>
                    <CardTitle>{t('reports.macros_by_day')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        className="h-72 w-full"
                        role="img"
                        aria-label={t('reports.macro_chart')}
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={localizedChart}
                                margin={{ top: 4, right: 4, left: -22, bottom: 0 }}
                            >
                                <CartesianGrid vertical={false} stroke="var(--border)" />
                                <XAxis
                                    dataKey="day"
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                                />
                                <YAxis
                                    domain={[
                                        0,
                                        Math.max(
                                            targets.protein,
                                            targets.carbohydrates,
                                            targets.fat,
                                            ...chart.flatMap((point) => [
                                                point.protein,
                                                point.carbohydrates,
                                                point.fat,
                                            ]),
                                        ),
                                    ]}
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                />
                                <Tooltip content={<ChartTooltip unit="g" />} />
                                <Bar
                                    dataKey="protein"
                                    fill="var(--protein)"
                                    radius={[8, 8, 2, 2]}
                                />
                                <Bar
                                    dataKey="carbohydrates"
                                    fill="var(--carbs)"
                                    radius={[8, 8, 2, 2]}
                                />
                                <Bar
                                    dataKey="fat"
                                    fill="var(--fat)"
                                    radius={[8, 8, 2, 2]}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="mt-4 flex flex-wrap justify-center gap-5 text-xs text-muted-foreground">
                        <Legend
                            color="var(--protein)"
                            label={t('common.protein')}
                        />
                        <Legend
                            color="var(--carbs)"
                            label={t('common.carbohydrates')}
                        />
                        <Legend color="var(--fat)" label={t('common.fat')} />
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function StatCard({
    label,
    value,
    context,
    color = 'var(--primary)',
}: {
    label: string;
    value: string;
    context: string;
    color?: string;
}) {
    return (
        <Card className="group overflow-hidden transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[0_20px_46px_rgb(25_72_55_/_0.09)]">
            <CardContent className="pt-5 sm:pt-6">
                <span
                    className="mb-4 block h-1.5 w-9 rounded-full shadow-[inset_0_1px_0_rgb(255_255_255_/_0.4)] transition-transform group-hover:scale-x-110"
                    style={{ background: color }}
                />
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-semibold tracking-[-0.035em]">
                    {value}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">{context}</p>
            </CardContent>
        </Card>
    );
}

function ChartTooltip({
    active,
    payload,
    label,
    unit,
}: {
    active?: boolean;
    payload?: { name: string; value: number; color: string }[];
    label?: string;
    unit: string;
}) {
    const { t } = useTranslation();

    if (!active || !payload?.length) return null;

    return (
        <div className="glass-panel rounded-xl p-3 text-xs">
            <p className="mb-2 font-semibold">{label}</p>
            {payload.map((item) => (
                <p key={item.name} className="mt-1 text-muted-foreground">
                    {t(
                        item.name === 'carbohydrates'
                            ? 'common.carbohydrates'
                            : `common.${item.name}`,
                    )}
                    :{' '}
                    <strong className="text-foreground">
                        {formatNumber(item.value, 1)} {unit}
                    </strong>
                </p>
            ))}
        </div>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="flex items-center gap-2">
            <span className="size-2.5 rounded-sm" style={{ background: color }} />
            {label}
        </span>
    );
}
