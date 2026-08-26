# Rental Property Management System

Laravel web portal + MySQL, built against [docs/SRS.md](docs/SRS.md) (v1.0, 31 July 2026).
The backend is layered so the same business logic can be exposed as REST APIs for the
future Flutter clients (SRS §4, §9.3).

**Current state: all seven SRS phases delivered.** Every workflow in SRS §5 runs end to
end - property publication, renter inquiry, renter assignment, monthly submission, owner
approval and freeze, maintenance tracking, reporting, and the versioned REST API.

A house can also be rented **floor by floor**: each floor is listed, priced, and let on its
own, while an independent house stays a single listing - see
[docs/MULTI-FLOOR-HOUSES.md](docs/MULTI-FLOOR-HOUSES.md). Every listing carries 3 to 12
photos and optional walkthrough videos, and deleting one hides it from renters without
losing it - see [docs/LISTING-MEDIA-AND-DELETION.md](docs/LISTING-MEDIA-AND-DELETION.md).

See [docs/PHASE-1.md](docs/PHASE-1.md), [docs/PHASE-2.md](docs/PHASE-2.md), and
[docs/PHASE-3-TO-7.md](docs/PHASE-3-TO-7.md) for what is built, the decisions taken along
the way, and the gaps that remain deliberate.

## Requirements

- PHP 8.3+
- MySQL 8 (production/local); the test suite runs on in-memory SQLite
- Node 20+ (Vite 8 / Tailwind 4)

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the MySQL database named in DB_DATABASE first (SRS 2.4 requires MySQL).
php artisan migrate --seed

# Profile and property images are served from the public disk.
php artisan storage:link

npm install
npm run build     # or: npm run dev
php artisan serve
```

### Demo accounts

`php artisan migrate:fresh --seed` creates two accounts and nothing else, both with the
password `password`. Properties, tenancies, records, and applications are created through
the application itself, so the seeded state never diverges from what the workflows produce.

| Role   | Email                |
| ------ | -------------------- |
| Owner  | `owner@example.com`  |
| Renter | `renter@example.com` |

There are two roles. A **renter** covers the whole journey: they browse the public feed,
apply for a listing, and become its tenant once an owner accepts - so the same account
keeps searching for other properties while renting one.

To see the whole system: sign in as the owner, add and publish a property, sign in as the
renter to apply for it, then assign the renter to it as the owner and submit a month and
raise a maintenance request as the renter.

## REST API

Versioned at `/api/v1`, authenticated with Sanctum tokens (SRS §9.3). Every endpoint reuses
the same actions and policies as the web portal.

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H 'Accept: application/json' \
  -d 'identifier=owner@example.com&password=password&device_name=cli'

curl http://localhost:8000/api/v1/listings?area=Gulberg -H 'Accept: application/json'
```

`php artisan route:list --path=api` lists all 34 endpoints.

## Tests and style

```bash
php artisan test          # 222 tests
./vendor/bin/pint         # code style
```

## Layout

| Path                | Contents                                                       |
| ------------------- | -------------------------------------------------------------- |
| `app/Enums`         | Domain state machines (statuses, roles, priorities, categories) |
| `app/Models`        | The 13 entities from SRS §8 with their relationships and scopes |
| `app/Policies`      | Record-level authorisation (SRS §3.1, BR-010, NFR-SEC-004)      |
| `app/Actions`       | Single-purpose write operations reusable by web and API         |
| `app/Services`      | File handling, notifications, reporting, and the audit logger   |
| `app/Http/Resources/V1` | Versioned JSON shapes for the Flutter clients (§9.3)        |
| `app/Support`       | Small helpers (money formatting, BR-012)                        |
| `config/rental.php` | Currency, registration, upload, and listing configuration       |
| `docs/`             | SRS and phase notes                                             |
