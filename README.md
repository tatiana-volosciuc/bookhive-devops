# Bookhive

A Symfony-based bookshop catalog application, built primarily as a **DevOps learning and planning project**.

## Stack

- **PHP** 8.4
- **Symfony** 8.x
- **MySQL** 8.x via Doctrine ORM
- **Twig** for server-rendered HTML views
- **Composer** for dependency management

## Current state

- Core entities in place: `Book`, `Author`, `Category`, `Publisher`, with proper relations (ManyToMany, ManyToOne)
- Full CRUD (list/create/edit/delete) for all four entities, both as HTML forms
- Business logic extracted into dedicated `*Service` classes, keeping controllers thin
- Local MySQL connection configured via `.env` / `DATABASE_URL`

## Endpoints

- HTML CRUD pages are available at /books, /authors, /categories, and /publishers, with matching JSON create endpoints under /api/* for a couple of entities.
- Health endpoint: /health.
## Local setup (quick reference)

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start
```

Then visit `http://127.0.0.1:8000/` for the dashboard.

## Where this is headed

The next phase of work is DevOps-focused rather than feature-focused.

This README will be updated as those pieces land.
