# Kcal

A mobile-first calories and macronutrients tracker built with Laravel, MySQL,
Inertia, React, Material UI, and the licensed Minimal UI design system.

## Included

- Registration, login, password reset, email verification, and profile updates
- First-run onboarding for calorie, protein, carbohydrate, fat, and fibre targets
- Daily diary split into breakfast, lunch, dinner, and snacks
- Common food library, custom foods, servings, barcode fields, and favourites
- Mobile camera barcode scanning while logging packaged foods
- Streaming Open Food Facts imports with Romanian-market filtering
- Streaming USDA FoodData Central imports for generic foods
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
- TypeScript and Vite
- Material UI 7 with the Minimal UI palette, typography, shadows, and layouts
- i18next and react-i18next
- MUI icons, Framer Motion, and Recharts

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

## Open Food Facts import

The default import only keeps products tagged as sold in Romania. The dump is
streamed directly from gzip and written in bounded transactions, so it does not
need to fit in PHP memory.

Download the dump on the machine that will run the import:

```bash
php artisan foods:download-open-food-facts
```

Check a small sample without changing the database:

```bash
php artisan foods:import-open-food-facts --scope=all --limit=100 --dry-run
```

Run the Romanian catalog import:

```bash
php artisan foods:import-open-food-facts
```

Use `--resume` after an interrupted import. Use `--force` only when the same
completed dump must be processed again. `--scope=all` imports the global
catalog and will require substantially more storage. Run a full import inside a
persistent terminal session or a process supervisor on the production server.

The source URL and local path can be changed with
`OPEN_FOOD_FACTS_URL` and `OPEN_FOOD_FACTS_IMPORT_PATH`.

## USDA generic food import

USDA FoodData Central complements Open Food Facts with generic foods such as
raw and cooked fruit, vegetables, grains, meat, and dairy. The importer uses
the Foundation and SR Legacy datasets, stores their English descriptions as
food translations, and normalizes nutrients to the source's 100 g edible-food
basis. Branded USDA foods are intentionally excluded because Open Food Facts
already supplies the packaged-product catalog.

Run the migration and import both archives:

```bash
php artisan migrate
php artisan foods:import-usda
```

The import command downloads any missing archives into
`storage/app/imports` before processing them. You can also download them
without importing by running `php artisan foods:download-usda`.

Use `--dataset=foundation` or `--dataset=sr-legacy` to process only one
archive. The import is streamed from each ZIP, uses bounded transactions, and
supports `--dry-run`, `--resume`, `--force`, and `--batch=500`.

The source URLs can be changed with the two `USDA_*_URL` variables in `.env`.
The archives are not committed or deployed with the application, so the first
import on each server downloads its own copies.

## Localization

The supported locales are English (`en`) and Romanian (`ro`). The selected
locale is stored in the Laravel session and shared with the React application
through Inertia.

- Frontend copy: `resources/js/locales/en.json` and `resources/js/locales/ro.json`
- Laravel messages: `lang/en` and `lang/ro`
- Food translations: the `food_translations` database table, with the original
  food name used as fallback
- Supported language menu: `config/locales.php`

Add new user-facing frontend copy to both JSON files. Shared foods retain a
stable translation key in the database, while user-created foods remain in the
language entered by the user. To introduce another language, add it to
`config/locales.php` with its name, two-letter display code, and flag; register
its i18next resource in `resources/js/i18n.ts`; and create the corresponding
frontend and Laravel translation files.
