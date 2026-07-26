import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Coffee,
    Cookie,
    Minus,
    Plus,
    Salad,
    Soup,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { MacroProgress } from '@/components/macro-progress';
import { PeriodNavigator } from '@/components/period-navigator';
import type { DiaryEntry, NutritionTargets } from '@/types';
import { formatDate, formatNumber } from '@/lib/utils';

const meals = [
    { key: 'breakfast', labelKey: 'diary.breakfast', icon: Coffee },
    { key: 'lunch', labelKey: 'diary.lunch', icon: Salad },
    { key: 'dinner', labelKey: 'diary.dinner', icon: Soup },
    { key: 'snacks', labelKey: 'diary.snacks', icon: Cookie },
] as const;

type Totals = {
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
};

export default function DiaryShow({
    date,
    isToday,
    previousDate,
    nextDate,
    entries,
    totals,
    targets,
    notes,
}: {
    date: string;
    isToday: boolean;
    previousDate: string;
    nextDate: string;
    entries: DiaryEntry[];
    totals: Totals;
    targets: NutritionTargets;
    notes: string | null;
}) {
    const { t } = useTranslation();
    const remaining = targets.calories - totals.calories;
    const calorieProgress =
        targets.calories > 0
            ? Math.min(100, (totals.calories / targets.calories) * 100)
            : 0;

    return (
        <AppLayout
            title={t('diary.title')}
            subtitle={formatDate(date, { weekday: 'long' })}
            actions={
                !isToday ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/today">{t('common.today')}</Link>
                    </Button>
                ) : undefined
            }
        >
            <Head title={isToday ? t('common.today') : formatDate(date)} />

            <PeriodNavigator
                title={
                    isToday
                        ? t('common.today')
                        : formatDate(date, { weekday: 'long' })
                }
                subtitle={formatDate(date, { year: undefined })}
                previousHref={`/diary/${previousDate}`}
                nextHref={`/diary/${nextDate}`}
                previousLabel={t('common.previous_day')}
                nextLabel={t('common.next_day')}
            />

            <div className="grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
                <Card className="relative overflow-hidden border-primary/10 before:pointer-events-none before:absolute before:-right-16 before:-top-20 before:size-52 before:rounded-full before:bg-secondary/70 before:blur-3xl">
                    <CardContent className="p-5 sm:p-6">
                        <div className="grid gap-7 sm:grid-cols-[auto_1fr] sm:items-center lg:grid-cols-1">
                            <div className="mx-auto">
                                <CalorieRing
                                    value={totals.calories}
                                    target={targets.calories}
                                    progress={calorieProgress}
                                />
                            </div>
                            <div>
                                <div className="soft-well mb-5 rounded-2xl p-4 text-center sm:text-left lg:text-center">
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {remaining >= 0
                                            ? t('diary.remaining')
                                            : t('diary.over_target')}
                                    </p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {formatNumber(Math.abs(remaining))} kcal
                                    </p>
                                </div>
                                <div className="space-y-4">
                                    <MacroProgress
                                        label={t('common.protein')}
                                        type="protein"
                                        value={totals.protein}
                                        target={targets.protein}
                                    />
                                    <MacroProgress
                                        label={t('common.carbohydrates')}
                                        type="carbohydrates"
                                        value={totals.carbohydrates}
                                        target={targets.carbohydrates}
                                    />
                                    <MacroProgress
                                        label={t('common.fat')}
                                        type="fat"
                                        value={totals.fat}
                                        target={targets.fat}
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="stagger-in space-y-4">
                    {meals.map((meal) => {
                        const mealEntries = entries.filter(
                            (entry) => entry.meal === meal.key,
                        );
                        return (
                            <MealCard
                                key={meal.key}
                                date={date}
                                meal={meal}
                                entries={mealEntries}
                            />
                        );
                    })}
                </div>
            </div>

            <DailyNotes date={date} notes={notes ?? ''} />
        </AppLayout>
    );
}

function CalorieRing({
    value,
    target,
    progress,
}: {
    value: number;
    target: number;
    progress: number;
}) {
    const { t } = useTranslation();
    const radius = 74;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (progress / 100) * circumference;

    return (
        <div
            className="relative size-48"
            role="img"
            aria-label={t('diary.calorie_progress', { value, target })}
        >
            <svg className="-rotate-90" viewBox="0 0 176 176" aria-hidden="true">
                <defs>
                    <linearGradient
                        id="calorie-ring-gradient"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="color-mix(in oklch, var(--primary) 65%, white)"
                        />
                        <stop offset="100%" stopColor="var(--primary)" />
                    </linearGradient>
                </defs>
                <circle
                    cx="88"
                    cy="88"
                    r={radius}
                    fill="none"
                    stroke="var(--secondary)"
                    strokeWidth="12"
                />
                <circle
                    cx="88"
                    cy="88"
                    r={radius}
                    fill="none"
                    stroke="url(#calorie-ring-gradient)"
                    strokeLinecap="round"
                    strokeWidth="12"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className="ring-enter drop-shadow-[0_5px_7px_rgb(27_103_75_/_0.18)] transition-all duration-500"
                />
            </svg>
            <div className="absolute inset-0 grid place-items-center text-center">
                <div>
                    <p className="text-3xl font-semibold tracking-tight">
                        {formatNumber(value)}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('diary.of_calories', {
                            target: formatNumber(target),
                        })}
                    </p>
                </div>
            </div>
        </div>
    );
}

function MealCard({
    date,
    meal,
    entries,
}: {
    date: string;
    meal: (typeof meals)[number];
    entries: DiaryEntry[];
}) {
    const { t } = useTranslation();
    const Icon = meal.icon;
    const total = entries.reduce((sum, entry) => sum + entry.calories, 0);

    return (
        <Card className="overflow-hidden transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[0_22px_54px_rgb(25_72_55_/_0.09)]">
            <CardHeader className="flex-row items-center justify-between p-4 pb-3 sm:p-5 sm:pb-3">
                <div className="flex items-center gap-3">
                    <span className="soft-well grid size-10 place-items-center rounded-xl text-primary">
                        <Icon className="size-5" />
                    </span>
                    <div>
                        <CardTitle>{t(meal.labelKey)}</CardTitle>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {formatNumber(total)} kcal
                        </p>
                    </div>
                </div>
                <Button size="sm" variant="secondary" asChild>
                    <Link href={`/foods?date=${date}&meal=${meal.key}`}>
                        <Plus />
                        {t('common.add')}
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="p-0">
                {entries.length === 0 ? (
                    <div className="border-t border-white/65 bg-white/16 px-5 py-7 text-center">
                        <p className="text-sm text-muted-foreground">
                            {t('diary.empty_meal')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-white/65 border-t border-white/65 bg-white/14">
                        {entries.map((entry) => (
                            <DiaryEntryRow key={entry.id} entry={entry} />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function DiaryEntryRow({ entry }: { entry: DiaryEntry }) {
    const { t } = useTranslation();
    const update = (quantity: number) => {
        if (quantity <= 0) return;
        router.put(
            `/diary-entries/${entry.id}`,
            { quantity },
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 px-4 py-3.5 transition-colors hover:bg-white/42 sm:flex sm:px-5">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">{entry.food_name}</p>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {entry.quantity} × {entry.serving_name}
                    {entry.brand ? ` · ${entry.brand}` : ''}
                </p>
            </div>
            <div className="text-right">
                <p className="text-sm font-semibold">{formatNumber(entry.calories)} kcal</p>
                <p className="mt-0.5 hidden text-[11px] text-muted-foreground sm:block">
                    {t('diary.macro_short', {
                        protein: formatNumber(entry.protein, 1),
                        carbs: formatNumber(entry.carbohydrates, 1),
                        fat: formatNumber(entry.fat, 1),
                    })}
                </p>
            </div>
            <div className="col-span-2 flex items-center justify-between sm:col-span-1">
                <p className="text-[11px] text-muted-foreground sm:hidden">
                    {t('diary.macro_short', {
                        protein: formatNumber(entry.protein, 1),
                        carbs: formatNumber(entry.carbohydrates, 1),
                        fat: formatNumber(entry.fat, 1),
                    })}
                </p>
                <div className="flex shrink-0">
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={t('diary.reduce_food', {
                            food: entry.food_name,
                        })}
                        onClick={() => update(entry.quantity - 0.5)}
                    >
                        <Minus />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={t('diary.increase_food', {
                            food: entry.food_name,
                        })}
                        onClick={() => update(entry.quantity + 0.5)}
                    >
                        <Plus />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="text-muted-foreground hover:text-destructive"
                        aria-label={t('diary.remove_food', {
                            food: entry.food_name,
                        })}
                        onClick={() =>
                            router.delete(`/diary-entries/${entry.id}`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function DailyNotes({ date, notes }: { date: string; notes: string }) {
    const { t } = useTranslation();
    const form = useForm({ notes });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/diary/${date}/notes`, { preserveScroll: true });
    };

    return (
        <Card className="mt-5 overflow-hidden">
            <CardHeader>
                <CardTitle>{t('diary.daily_note')}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit}>
                    <Textarea
                        className="min-h-28 resize-y"
                        placeholder={t('diary.note_placeholder')}
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                    />
                    <div className="mt-3 flex justify-end">
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {t('diary.save_note')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
