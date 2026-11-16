# Repository Instructions

## Architecture

- This is a Laravel 12 / PHP 8.2+ application with Vue 3, TypeScript, Inertia.js, and Vite. Laravel serves Inertia pages from `routes/web.php`; JSON endpoints are defined in `routes/api.php` and implemented primarily in `app/Http/Controllers/Api`.
- The financial domain is modeled with Eloquent models and migrations under `app/Models` and `database/migrations`. Keep business logic in existing services under `app/Services`, request validation in Form Requests under `app/Http/Requests`, and UI pages in `resources/js/pages`.
- Authentication uses Sanctum/Fortify and Google OAuth via Socialite. Protected APIs apply `company.isolation`; write routes additionally use `track.changes`. Root and master-admin access use separate middleware.
- Operational models use `BelongsToCompany` and its explicit `forCompany()` query scope. The isolation middleware validates company association but does not scope every query automatically: scope all tenant-owned reads and writes, including route model lookups. See [MULTI_TENANT_ARCHITECTURE.md](../MULTI_TENANT_ARCHITECTURE.md).
- Company onboarding provisions the protected `Não definido` entry type, exit type, and collaborator through `CompanyDefaultsService`; keep this behavior consistent with `EnsureCompanyDefaultsSeeder` and `ProtectsDefaultRecords`.
- Inertia page names in `routes/web.php` map to Vue files in `resources/js/pages`. Frontend API calls use Axios; Wayfinder route/action files are generated, so change their source routes/controllers rather than editing generated output.

## Commands

Run commands from the repository root using the `laravel.test` Docker Compose service:

```sh
# Start Vite with hot reload for development
docker compose exec laravel.test npm run dev

# Run the full PHP test suite
docker compose exec laravel.test php artisan test

# Run one test file or one test method
docker compose exec laravel.test php artisan test tests/Feature/Api/FinancialRecordApiTest.php
docker compose exec laravel.test php artisan test --filter=test_root_can_create_financial_record

# Check PHP formatting without modifying files
docker compose exec laravel.test vendor/bin/pint --test

# Check frontend formatting; npm run lint runs ESLint with --fix
docker compose exec laravel.test npm run format:check
docker compose exec laravel.test npm run lint

# Production asset build (not needed for normal Vite development)
docker compose exec laravel.test npm run build
```

`composer test` also runs the Laravel test suite after clearing configuration. The corresponding npm scripts are defined in `package.json`, and the PHP scripts/dependencies are in `composer.json`.

## Conventions

- Treat migrations as the source of truth for columns, nullability, constraints, and enums before changing models, Form Requests, factories, or tests.
- Preserve tenant isolation explicitly with `forCompany()` and company-scoped validation/relationships. Add cross-company isolation coverage when changing tenant-owned endpoints.
- Keep fixed/custom API paths before parameterized paths so a route such as `.../mark-as-paid` is not matched as an `{id}` route. Read APIs are grouped separately from audited write APIs in `routes/api.php`.
- Preserve each endpoint's existing JSON contract. Many endpoints return records under `data` and put pagination metadata alongside it; test exact response paths and decimal values (often serialized as strings).
- UI text and user-facing errors are in Portuguese; code identifiers are in English. Use SVG icons (the project uses `lucide-vue-next`), not emoji in the interface.
- Never create commits automatically. The repository's broader project notes are in [DEVELOPMENT_CHECKLIST.md](../DEVELOPMENT_CHECKLIST.md); project-wide multi-tenant details are in [MULTI_TENANT_ARCHITECTURE.md](../MULTI_TENANT_ARCHITECTURE.md).
