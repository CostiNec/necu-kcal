import type { PageProps as InertiaPageProps } from '@inertiajs/core';

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
    unit_type: 'g' | 'ml' | 'piece';
    is_custom: boolean;
    is_favourite?: boolean;
    serving?: {
        name: string;
        translation_key: string | null;
        amount: number;
    } | null;
};

export type DiaryEntry = {
    id: number;
    meal: 'breakfast' | 'lunch' | 'dinner' | 'snacks';
    food_id: number | null;
    food_name: string;
    brand: string | null;
    serving_name: string;
    serving_translation_key?: string | null;
    quantity: number;
    amount: number;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
};
