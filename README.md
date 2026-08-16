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
- Official generic-food imports from USDA, Canada, Finland, the UK, and Australia
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

`composer dev` starts the Laravel server, queue worker, Vite, application logs,
and the Reverb WebSocket server together. Realtime notifications require both
the queue worker and Reverb process to be running.

## AI nutrition estimates

Google Gemini is the default provider. Create an API key in Google AI Studio,
then configure it in `.env`:

```dotenv
AI_NUTRITION_PROVIDER=gemini
AI_NUTRITION_FULL_DAY_TIMEOUT=120
GEMINI_API_KEY=your-key
GEMINI_API_KEY_2=optional-second-key
GEMINI_API_KEY_3=optional-third-key
GEMINI_NUTRITION_MODEL=gemini-3.6-flash
```

Full-day estimates allow two minutes for the configured provider to respond.
Single-food estimates continue to use the provider-specific timeout.
When multiple different Gemini keys are configured, requests alternate between
all of them. If one key is rejected, rate-limited, or temporarily unavailable,
the remaining keys are tried immediately. Gemini quotas are shared by keys from
the same Google project, so separate keys only provide additional quota when
they use separate projects.

Clear cached configuration after changing providers or keys:

```bash
php artisan config:clear
```

OpenAI remains available as a fallback. Set
`AI_NUTRITION_PROVIDER=openai` and configure `OPENAI_API_KEY` to switch without
changing application code.

## Verification

```bash
php artisan test
npm run typecheck
npm run build
```

## Typesense food search

MySQL remains the source of truth for food and nutrition data. Typesense stores
the fields needed for food-name autocomplete, barcode lookup, visibility
filtering, and ranking. Every user-facing food search, including exact barcode
and favourites-only searches, is executed by Typesense; MySQL hydrates the food
IDs returned by the index with current nutrition data.

The included Compose service defaults to a 2 GB memory ceiling, intended for a
4 GB host while leaving room for MySQL, PHP, and the operating system. Override
`TYPESENSE_MEMORY_LIMIT` after measuring the completed index if needed.

For local development, set a strong API key and start the dedicated service:

```bash
TYPESENSE_API_KEY=replace-with-a-long-random-secret \
    docker compose -f compose.typesense.yaml up -d
```

Configure Laravel before building the first index:

```dotenv
FOOD_SEARCH_DRIVER=database
SCOUT_DRIVER=typesense
SCOUT_QUEUE=true
TYPESENSE_API_KEY=replace-with-a-long-random-secret
TYPESENSE_HOST=127.0.0.1
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
```

Build the collection using queued ID ranges and keep the queue worker running:

```bash
php artisan foods:rebuild-search-index --chunk=500
php artisan queue:work --queue=default --timeout=300
```

After the import jobs finish, switch `FOOD_SEARCH_DRIVER=typesense`. Keeping it
on `database` during the first import makes the rollout atomic: users continue
to use MySQL until the new collection is ready.

Use `--sync` only for small development datasets. Re-run the rebuild after a
bulk catalogue import or deduplication because those maintenance operations use
database upserts and intentionally bypass Eloquent model observers. Ordinary
food, translation, and alias edits are synchronized automatically by Scout.

If Typesense is unavailable, food search returns HTTP 503 rather than querying
the MySQL food table. Keep the Typesense service and its collection healthy
before switching traffic to a deployment.

## Production checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, the public `APP_URL`, and a
   persistent `APP_KEY`.
2. Configure MySQL, mail delivery, HTTPS, and secure session cookies.
3. Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
4. Run `php artisan migrate --force`.
5. Configure the `REVERB_*` and `VITE_REVERB_*` variables, build the frontend,
   and run `php artisan reverb:start` and `php artisan queue:work` under a
   process supervisor.
6. Run `php artisan optimize`.
7. Point the web server document root to `public/`.
8. Configure a scheduler entry for `php artisan schedule:run` if scheduled jobs
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
Foundation, SR Legacy, and FNDDS, stores English descriptions as food
translations, and normalizes nutrients to the source basis. Branded USDA foods
are intentionally excluded because Open Food Facts already supplies the
packaged-product catalog.

Run the migration and import both archives:

```bash
php artisan migrate
php artisan foods:import-usda
```

The import command downloads any missing archives into
`storage/app/imports` before processing them. You can also download them
without importing by running `php artisan foods:download-usda`.

Use `--dataset=foundation`, `--dataset=sr-legacy`, or `--dataset=fndds` to
process one archive. The import is streamed from each ZIP, uses bounded
transactions, and supports `--dry-run`, `--resume`, `--force`, and
`--batch=500`.

The source URLs can be changed with the `USDA_*_URL` variables in `.env`.
The archives are not committed or deployed with the application, so the first
import on each server downloads its own copies.

## Other official generic-food sources

The unified importer supports these provenance-preserving sources:

- Canadian Nutrient File 2026 (`cnf`)
- Fineli raw ingredients (`fineli`)
- UK CoFID 2021 (`cofid`)
- Australian Food Composition Database Release 3 (`afcd`)

Missing source files are downloaded automatically into `storage/app/imports`.
They are intentionally ignored by Git, so the same command should be run on
the production server after deployment.

Validate every source without writing:

```bash
php artisan foods:import-generic-sources --source=all --dry-run
```

Import them and then create the reviewed English/Romanian common-food layer:

```bash
php artisan foods:import-generic-sources --source=all
php artisan foods:import-usda --dataset=fndds
php artisan foods:curate-common --link-exact-duplicates
php artisan foods:deduplicate-generics
```

Use `--source=cnf`, `fineli`, `cofid`, or `afcd` for a single source. Imports
are idempotent by `(source_id, external_id)`, audited in `food_import_runs`,
and can be repeated with `--force` after a source file changes. Spreadsheet
imports raise the CLI memory limit to 768 MB while parsing, so production
workers should allow at least that amount for this maintenance command.

After all generic sources are loaded, `foods:deduplicate-generics` links
records with the same normalized source name and nutrition basis to one
preferred canonical record. It preserves every source row and existing diary
reference while removing duplicate choices from food search.

The curation command never invents nutrition data. It creates a stable simple
food (for example `Egg` / `Ou`) from a reviewed source candidate and stores
the supplying row in `nutrition_source_food_id`. Exact duplicate source rows
can be hidden from normal search without being deleted. Edit
`config/common-foods.php` to review or extend the vocabulary.

EFSA's European Food Composition Database is registered as a source, but is
not imported automatically because EFSA currently exposes it through an
interactive dashboard rather than a stable, documented bulk-download file.
An importer should only be added after EFSA publishes a supported export
schema and URL.

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
