# Klik Laundry

Responsive multi-tenant laundry MVP built as a Laravel 13 modern monolith with Inertia, React, TypeScript, and Tailwind CSS.

Project decisions and current implementation status live in [`docs/`](docs/README.md). Read that index before changing product scope, architecture, domain rules, security, or roadmap.

## Local setup

Requirements: PHP 8.3+, Composer 2, Node.js version from `.nvmrc`, and npm. The current foundation uses SQLite, so PostgreSQL and Redis are not required locally yet.

```powershell
nvm use 24.21.0
composer setup
composer dev
```

Open `http://localhost:8000`. The database-backed Milestone 1 reference slice is available at `/foundation/platform-settings`.

## Quality checks

```powershell
composer test
composer analyse
composer format:check
npm run lint
npm run types:check
npm run build
composer audit --locked
npm audit --audit-level=high
```

GitHub Actions repeats these gates with PostgreSQL 18 and Redis 8 to catch database portability issues. Hosted CI must pass before Milestone 1 exit criteria are considered complete.

## Security

Copy only safe placeholders from `.env.example`. Keep real application keys, database credentials, and Duitku credentials in `.env` or an external secret manager; never expose them through `VITE_*`, Inertia props, logs, fixtures, or commits.
