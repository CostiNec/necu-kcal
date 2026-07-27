import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type {
    MeasurementUnit,
    NutritionBasisUnit,
} from '@/lib/measurement-units';

export type User = {
    id: number;
    name: string;
    username: string;
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
    notificationSummary: {
        unread_count: number;
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
    nutrition_basis_amount: number;
    nutrition_basis_unit: NutritionBasisUnit;
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
    unit: MeasurementUnit;
    quantity: number;
    amount: number;
    total_grams: number | null;
    total_milliliters: number | null;
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number;
};
