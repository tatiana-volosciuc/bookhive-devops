# Bookhive

A Symfony-based bookshop catalog application, built primarily as a **DevOps learning and planning project**.

## Stack

- **PHP** 8.4
- **Symfony** 8.x (skeleton-based, built up manually rather than via `--webapp`)
- **MySQL** 8.x via Doctrine ORM
- **Twig** for server-rendered HTML views
- **Composer** for dependency management

## Current state

- Core entities in place: `Book`, `Author`, `Category`, `Publisher`, with proper relations (ManyToMany, ManyToOne)
- Full CRUD (list/create/edit/delete) for all four entities, both as HTML forms and (partially) as a JSON API
- Business logic extracted into dedicated `*Service` classes, keeping controllers thin
- Local MySQL connection configured via `.env` / `DATABASE_URL`
- Session-based flash messaging for form feedback (CSRF currently disabled — flagged as a known gap, see below)

## Local setup (quick reference)

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start
```

Then visit `http://127.0.0.1:8000/` for the dashboard.

## Where this is headed

The next phase of work is DevOps-focused rather than feature-focused:

- Containerize the app (PHP-FPM + MySQL + Nginx/Caddy via Docker Compose)
- Add a CI pipeline (lint, static analysis, migrations check, basic smoke tests)
- Introduce environment-specific configuration (dev/staging/prod)
- Plan a deployment target and release process

This README will be updated as those pieces land.
