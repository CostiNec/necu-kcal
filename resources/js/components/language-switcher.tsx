import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SharedProps } from '@/types';

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
    const { availableLocales } = usePage<SharedProps>().props;
    const { t, i18n } = useTranslation();
    const resolvedLocale = i18n.resolvedLanguage?.split('-')[0] ?? 'en';
    const activeLocale = availableLocales[resolvedLocale]
        ? resolvedLocale
        : Object.keys(availableLocales)[0] ?? 'en';

    const changeLocale = (locale: string) => {
        if (locale === activeLocale) return;

        document.documentElement.lang = locale;
        void i18n.changeLanguage(locale);
        router.post(
            '/locale',
            { locale },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <Select value={activeLocale} onValueChange={changeLocale}>
            <SelectTrigger
                className={compact ? 'w-[5.75rem]' : 'w-[6.25rem]'}
                aria-label={t('language.label')}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent align="end" className="min-w-28">
                {Object.entries(availableLocales).map(([locale, language]) => (
                    <SelectItem
                        key={locale}
                        value={locale}
                        textValue={language.name}
                        aria-label={language.name}
                    >
                        <span className="flex items-center gap-2">
                            <span aria-hidden="true">{language.flag}</span>
                            <span>{language.code}</span>
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
