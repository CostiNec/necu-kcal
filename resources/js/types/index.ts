import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type { MassUnit } from '@/lib/mass-units';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
};

export type NutritionTargets = {
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
};

export type SharedProps = InertiaPageProps & {
    auth: {
        user: User | null;
    };
    flash: {
        success?: string;
        error?: string;
        createdFoodId?: number;
    };
    locale: string;
    availableLocales: Record<
        string,
        {
            name: string;
            code: string;
            flag: string;
        }
    >;
};

export type Food = {
    id: number;
    name: string;
    brand: string | null;
    barcode: string | null;
    calories: number;
    protein: number | null;
    carbohydrates: number | null;
    fat: number | null;
    fibre: number | null;
    is_custom: boolean;
    is_favourite?: boolean;
};

export type DiaryEntry = {
    id: number;
    meal: 'breakfast' | 'lunch' | 'dinner' | 'snacks';
    food_id: number | null;
    food_name: string;
    brand: string | null;
    unit: MassUnit;
    quantity: number;
    amount: number;
    total_grams: number | null;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
};
