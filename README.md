# Kcal

A mobile-first calories and macronutrients tracker built with Laravel, MySQL,
Inertia, React, Tailwind CSS, and shadcn-style UI primitives.

## Included

- Registration, login, password reset, email verification, and profile updates
- First-run onboarding for calorie, protein, carbohydrate, and fat targets
- Daily diary split into breakfast, lunch, dinner, and snacks
- Common food library, custom foods, servings, barcode fields, and favourites
- Nutrition snapshots so historical diary entries do not change when a food is edited
- Daily notes and quick serving adjustments
- Weekly calorie and macro charts, averages, and most-logged foods
- English and Romanian UI, server validation, flash messages, and food names
- Session-persisted language switcher with locale-aware dates and numbers
- Account export-ready data model and permanent account deletion
- Responsive sidebar on desktop and thumb-friendly bottom navigation on mobile

## Stack

- PHP 8.3+ and Laravel 13
- MySQL 8+
- Laravel Fortify
- Inertia 3 and React 19
- TypeScript, Vite, and Tailwind CSS 4
- i18next and react-i18next
- Radix UI primitives, Lucide icons, Sonner, and Recharts

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the MySQL database configured in `.env`, then run:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

The development seeder creates `test@example.com` with password `password`.
Never run the development seeder against a production database.

For Vite hot reloading, use `npm run dev` in a second terminal.

## Verification

```bash
php artisan test
npm run typecheck
npm run build
```

## Production checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, the public `APP_URL`, and a
   persistent `APP_KEY`.
2. Configure MySQL, mail delivery, HTTPS, and secure session cookies.
3. Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
4. Run `php artisan migrate --force`.
5. Run `php artisan optimize`.
6. Point the web server document root to `public/`.
7. Configure a scheduler entry for `php artisan schedule:run` if scheduled jobs
   are added later.

The built-in food library is intentionally small and can be extended through
seeders or a future external food-data integration. Barcode values are stored
and searchable, but camera scanning and third-party nutrition lookup are not
part of this first release.

## Localization

The supported locales are English (`en`) and Romanian (`ro`). The selected
locale is stored in the Laravel session and shared with the React application
through Inertia.

- Frontend copy: `resources/js/locales/en.json` and `resources/js/locales/ro.json`
- Laravel messages: `lang/en` and `lang/ro`
- Translated seeded foods: `lang/{locale}/foods.php` and
  `lang/{locale}/servings.php`
- Supported language menu: `config/locales.php`

Add new user-facing frontend copy to both JSON files. Shared foods retain a
stable translation key in the database, while user-created foods remain in the
language entered by the user. To introduce another language, add it to
`config/locales.php` with its name, two-letter display code, and flag; register
its i18next resource in `resources/js/i18n.ts`; and create the corresponding
frontend and Laravel translation files.
