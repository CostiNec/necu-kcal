import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Check, LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/field-error';
import type { NutritionTargets, SharedProps } from '@/types';

export default function Onboarding({
    profile,
    targets,
}: {
    profile: { timezone: string } | null;
    targets: NutritionTargets | null;
}) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const form = useForm({
        name: auth.user?.name ?? '',
        calories: targets?.calories ?? 2000,
        protein: targets?.protein ?? 120,
        carbohydrates: targets?.carbohydrates ?? 220,
        fat: targets?.fat ?? 65,
        timezone: profile?.timezone ?? 'Europe/Bucharest',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/onboarding');
    };

    return (
        <main className="relative isolate min-h-screen overflow-hidden px-4 py-6 sm:px-6 sm:py-10">
            <Head title={t('onboarding.head_title')} />
            <div className="pointer-events-none absolute -left-40 -top-52 -z-10 size-[34rem] rounded-full bg-secondary/75 blur-3xl" />
            <div className="pointer-events-none absolute -right-48 top-1/3 -z-10 size-[30rem] rounded-full bg-primary/7 blur-3xl" />
            <div className="page-enter mx-auto max-w-2xl">
                <div className="flex items-center justify-between gap-4">
                    <BrandMark />
                    <LanguageSwitcher compact />
                </div>
                <div className="my-10 sm:my-14">
                    <span className="glass-subtle inline-flex rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em] text-primary">
                        {t('onboarding.step')}
                    </span>
                    <h1 className="mt-4 text-balance text-3xl font-semibold tracking-[-0.04em] sm:text-[2.65rem]">
                        {t('onboarding.title')}
                    </h1>
                    <p className="mt-3 max-w-xl leading-7 text-muted-foreground">
                        {t('onboarding.description')}
                    </p>
                </div>

                <form className="auth-card-enter" onSubmit={submit}>
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-white/60 bg-white/18">
                            <CardTitle>{t('onboarding.card_title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    {t('onboarding.name_question')}
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                <FieldError message={form.errors.name} />
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <TargetInput
                                    id="calories"
                                    label={t('common.calories')}
                                    suffix="kcal"
                                    value={form.data.calories}
                                    error={form.errors.calories}
                                    onChange={(value) => form.setData('calories', value)}
                                />
                                <TargetInput
                                    id="protein"
                                    label={t('common.protein')}
                                    suffix={t('common.grams')}
                                    value={form.data.protein}
                                    error={form.errors.protein}
                                    onChange={(value) => form.setData('protein', value)}
                                />
                                <TargetInput
                                    id="carbohydrates"
                                    label={t('common.carbohydrates')}
                                    suffix={t('common.grams')}
                                    value={form.data.carbohydrates}
                                    error={form.errors.carbohydrates}
                                    onChange={(value) =>
                                        form.setData('carbohydrates', value)
                                    }
                                />
                                <TargetInput
                                    id="fat"
                                    label={t('common.fat')}
                                    suffix={t('common.grams')}
                                    value={form.data.fat}
                                    error={form.errors.fat}
                                    onChange={(value) => form.setData('fat', value)}
                                />
                            </div>
                            <div className="soft-well rounded-2xl p-4">
                                <div className="flex gap-3">
                                    <Check className="mt-0.5 size-5 shrink-0 text-primary" />
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {t('onboarding.hint')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Button
                        size="lg"
                        type="submit"
                        className="mt-5 w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <ArrowRight />
                        )}
                        {t('onboarding.open_diary')}
                    </Button>
                </form>
            </div>
        </main>
    );
}

function TargetInput({
    id,
    label,
    suffix,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    suffix: string;
    value: number;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="relative">
                <Input
                    id={id}
                    type="number"
                    min="0"
                    value={value}
                    onChange={(event) => onChange(Number(event.target.value))}
                    className="pr-20"
                />
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted-foreground">
                    {suffix}
                </span>
            </div>
            <FieldError message={error} />
        </div>
    );
}
