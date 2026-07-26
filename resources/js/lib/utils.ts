import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatNumber(value: number, maximumFractionDigits = 0) {
    return new Intl.NumberFormat(document.documentElement.lang, {
        maximumFractionDigits,
    }).format(value);
}

export function formatDate(date: string, options?: Intl.DateTimeFormatOptions) {
    return new Intl.DateTimeFormat(document.documentElement.lang, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        ...options,
    }).format(new Date(`${date}T12:00:00`));
}
