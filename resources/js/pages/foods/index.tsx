import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Heart,
    LoaderCircle,
    Plus,
    Search,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FieldError } from '@/components/field-error';
import type { Food } from '@/types';
import { formatNumber } from '@/lib/utils';

const mealLabels = {
    breakfast: 'diary.breakfast',
    lunch: 'diary.lunch',
    dinner: 'diary.dinner',
    snacks: 'diary.snacks',
};

type Meal = keyof typeof mealLabels;

export default function FoodsIndex({
    foods,
    filters,
    context,
}: {
    foods: Food[];
    filters: { search: string };
    context: { date: string | null; meal: Meal };
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [showCreate, setShowCreate] = useState(false);
    const logging = Boolean(context.date);

    const navigateToSearch = (value: string) => {
        router.get(
            '/foods',
            {
                ...(value ? { search: value } : {}),
                ...(context.date ? { date: context.date, meal: context.meal } : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    const runSearch = (event: FormEvent) => {
        event.preventDefault();
        navigateToSearch(search.trim());
    };

    const clearSearch = () => {
        setSearch('');
        navigateToSearch('');
    };

    const openCreateForm = () => {
        setShowCreate(true);
        window.requestAnimationFrame(() => {
            document
                .getElementById('create-food-form')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };

    return (
        <AppLayout
            title={
                logging
                    ? t('food.add_to_meal', {
                          meal: t(mealLabels[context.meal]),
                      })
                    : t('food.library')
            }
            subtitle={
                logging
                    ? t('food.logging_description')
                    : t('food.library_description')
            }
            actions={
                logging ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link href={`/diary/${context.date}`}>
                            <ArrowLeft />
                            {t('food.diary')}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Head
                title={
                    logging ? t('food.add_food_head') : t('common.foods')
                }
            />

            <form
                onSubmit={runSearch}
                className="glass-subtle flex gap-2 rounded-2xl p-2"
            >
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('food.search_placeholder')}
                        className="pl-10"
                    />
                </div>
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <Button
                variant="outline"
                className="mt-4 w-full justify-between"
                onClick={() => setShowCreate((value) => !value)}
            >
                <span className="flex items-center gap-2">
                    <Plus />
                    {t('food.create_custom')}
                </span>
                {showCreate ? <ChevronUp /> : <ChevronDown />}
            </Button>

            {showCreate && <CreateFoodForm />}

            <div className="stagger-in mt-6 space-y-3">
                {foods.length === 0 ? (
                    <Card className="border-dashed border-primary/14 bg-card/55 shadow-none">
                        <CardContent className="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                            <div className="soft-well grid size-11 shrink-0 place-items-center rounded-xl text-primary">
                                <Search />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h2 className="font-semibold">
                                    {filters.search
                                        ? t('food.no_results_for', {
                                              search: filters.search,
                                          })
                                        : t('food.empty_library')}
                                </h2>
                                <p className="mt-1 max-w-xl text-sm leading-6 text-muted-foreground">
                                    {filters.search
                                        ? t('food.no_results_copy')
                                        : t('food.empty_library_copy')}
                                </p>
                            </div>
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                {filters.search && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={clearSearch}
                                    >
                                        {t('food.clear_search')}
                                    </Button>
                                )}
                                <Button type="button" onClick={openCreateForm}>
                                    <Plus />
                                    {t('food.create_food')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    foods.map((food) => (
                        <FoodRow
                            key={food.id}
                            food={food}
                            date={context.date}
                            meal={context.meal}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function FoodRow({
    food,
    date,
    meal,
}: {
    food: Food;
    date: string | null;
    meal: Meal;
}) {
    const { t } = useTranslation();
    const serving = food.serving ?? {
        name: `100 ${food.unit_type}`,
        translation_key: null,
        amount: 100,
    };
    const form = useForm({
        food_id: food.id,
        date: date ?? '',
        meal,
        serving_name: serving.name,
        serving_translation_key: serving.translation_key,
        serving_amount: serving.amount,
        quantity: 1,
    });
    const calculatedCalories =
        (food.calories * form.data.serving_amount * form.data.quantity) / 100;

    return (
        <Card className="overflow-hidden transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[0_22px_54px_rgb(25_72_55_/_0.09)]">
            <div className="flex items-start gap-3 p-4 sm:p-5">
                <Button
                    type="button"
                    variant={food.is_favourite ? 'secondary' : 'ghost'}
                    size="icon"
                    aria-label={
                        food.is_favourite
                            ? t('food.remove_favourite', { food: food.name })
                            : t('food.add_favourite', { food: food.name })
                    }
                    onClick={() =>
                        router.post(
                            `/foods/${food.id}/favourite`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                    className="shrink-0"
                >
                    <Heart
                        className={food.is_favourite ? 'fill-current text-primary' : ''}
                    />
                </Button>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 className="font-semibold">{food.name}</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {food.brand ||
                                    (food.is_custom
                                        ? t('food.custom')
                                        : t('food.common'))}{' '}
                                ·{' '}
                                {t('food.per_units', {
                                    unit: food.unit_type,
                                })}
                            </p>
                        </div>
                        <p className="text-sm font-semibold">
                            {formatNumber(food.calories)} kcal
                        </p>
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {t('food.macro_summary', {
                            protein: formatNumber(food.protein ?? 0, 1),
                            carbs: formatNumber(food.carbohydrates ?? 0, 1),
                            fat: formatNumber(food.fat ?? 0, 1),
                        })}
                    </p>
                </div>
            </div>
            {date && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/diary-entries');
                    }}
                    className="flex flex-col gap-3 border-t border-white/65 bg-white/18 p-4 sm:flex-row sm:items-end"
                >
                    <div className="flex-1 space-y-1.5">
                        <Label htmlFor={`serving-${food.id}`}>
                            {t('common.serving')}
                        </Label>
                        <Input
                            id={`serving-${food.id}`}
                            value={form.data.serving_name}
                            onChange={(event) => {
                                form.setData('serving_name', event.target.value);
                                form.setData('serving_translation_key', null);
                            }}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:flex">
                        <div className="space-y-1.5 sm:w-28">
                            <Label htmlFor={`amount-${food.id}`}>
                                {t('common.amount')}
                            </Label>
                            <Input
                                id={`amount-${food.id}`}
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.serving_amount}
                                onChange={(event) =>
                                    form.setData(
                                        'serving_amount',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-1.5 sm:w-24">
                            <Label htmlFor={`quantity-${food.id}`}>
                                {t('common.quantity')}
                            </Label>
                            <Input
                                id={`quantity-${food.id}`}
                                type="number"
                                min="0.25"
                                step="0.25"
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData('quantity', Number(event.target.value))
                                }
                            />
                        </div>
                    </div>
                    <Button type="submit" disabled={form.processing} className="sm:min-w-32">
                        {form.processing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <Plus />
                        )}
                        {formatNumber(calculatedCalories)} kcal
                    </Button>
                </form>
            )}
        </Card>
    );
}

function CreateFoodForm() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        brand: '',
        barcode: '',
        calories: 0,
        protein: 0,
        carbohydrates: 0,
        fat: 0,
        fibre: 0,
        unit_type: 'g',
        serving_name: t('food.default_serving'),
        serving_amount: 100,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/foods', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card
            id="create-food-form"
            className="mt-4 scroll-mt-24 overflow-hidden border-primary/14"
        >
            <CardContent className="pt-5 sm:pt-6">
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label={t('food.food_name')}
                            id="new-name"
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(value) => form.setData('name', value)}
                        />
                        <FormField
                            label={t('common.brand')}
                            id="new-brand"
                            value={form.data.brand}
                            error={form.errors.brand}
                            onChange={(value) => form.setData('brand', value)}
                        />
                    </div>
                    <p className="text-sm font-medium">
                        {t('food.nutrition_per_100')}
                    </p>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                        {[
                            ['calories', t('common.calories')],
                            ['protein', t('common.protein')],
                            ['carbohydrates', t('common.carbs')],
                            ['fat', t('common.fat')],
                            ['fibre', t('common.fibre')],
                        ].map(([key, label]) => (
                            <div key={key} className="space-y-2">
                                <Label htmlFor={`new-${key}`}>{label}</Label>
                                <Input
                                    id={`new-${key}`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={
                                        form.data[
                                            key as keyof Pick<
                                                typeof form.data,
                                                | 'calories'
                                                | 'protein'
                                                | 'carbohydrates'
                                                | 'fat'
                                                | 'fibre'
                                            >
                                        ]
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            key as
                                                | 'calories'
                                                | 'protein'
                                                | 'carbohydrates'
                                                | 'fat'
                                                | 'fibre',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="new-unit">
                                {t('food.base_unit')}
                            </Label>
                            <Select
                                value={form.data.unit_type}
                                onValueChange={(value) =>
                                    form.setData('unit_type', value)
                                }
                            >
                                <SelectTrigger id="new-unit" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="g">
                                        {t('food.grams')}
                                    </SelectItem>
                                    <SelectItem value="ml">
                                        {t('food.millilitres')}
                                    </SelectItem>
                                    <SelectItem value="piece">
                                        {t('food.pieces')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <FormField
                            label={t('food.serving_name')}
                            id="new-serving-name"
                            value={form.data.serving_name}
                            error={form.errors.serving_name}
                            onChange={(value) => form.setData('serving_name', value)}
                        />
                        <div className="space-y-2">
                            <Label htmlFor="new-serving-amount">
                                {t('food.serving_amount')}
                            </Label>
                            <Input
                                id="new-serving-amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.serving_amount}
                                onChange={(event) =>
                                    form.setData(
                                        'serving_amount',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <FieldError message={form.errors.serving_amount} />
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            {t('food.save_custom')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function FormField({
    label,
    id,
    value,
    error,
    onChange,
}: {
    label: string;
    id: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError message={error} />
        </div>
    );
}
