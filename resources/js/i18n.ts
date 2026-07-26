import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from '@/locales/en.json';
import ro from '@/locales/ro.json';

const initialLocale = document.documentElement.lang.startsWith('ro') ? 'ro' : 'en';

void i18n.use(initReactI18next).init({
    resources: {
        en: { translation: en },
        ro: { translation: ro },
    },
    lng: initialLocale,
    fallbackLng: 'en',
    supportedLngs: ['en', 'ro'],
    interpolation: {
        escapeValue: false,
    },
    react: {
        useSuspense: false,
    },
});

export default i18n;
