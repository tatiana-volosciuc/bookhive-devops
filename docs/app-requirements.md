# App Requirements — Bookhive

## Purpose

Bookhive is a bookshop catalog management application, built as the subject application for a DevOps learning and planning exercise. The functional requirements below exist to give the DevOps work (CI/CD, containerization, deployment) something real to operate on.

## Functional Requirements

### Catalog entities

| Entity | Required fields | Optional fields | Relations |
|---|---|---|---|
| **Book** | title, isbn, slug, price, stock, category | description, publishedAt, publisher, authors | ManyToOne → Category (required), ManyToOne → Publisher (optional), ManyToMany → Author |
| **Author** | name | bio, photo | ManyToMany → Book |
| **Category** | name, slug | — | OneToMany → Book |
| **Publisher** | name | website, description | OneToMany → Book |

### CRUD operations

For each of the four entities above, the system must support:
- **List** — view all records
- **Create** — add a new record via an HTML form
- **Edit** — update an existing record via an HTML form
- **Delete** — remove a record, with a confirmation prompt

All list/create/edit views render server-side via Twig. Books additionally support a JSON creation endpoint (`POST /api/books`), as does Category and Author, for programmatic access outside the HTML forms.

### Dashboard

A home page (`/`) must show live counts of all four entity types and provide quick-access links into each section's list and create form.

### Validation

- Required fields must be enforced both at the HTML form level and via Symfony's Validator component before persistence.
- Referential integrity: a Book cannot be created/updated without a valid, existing Category. Publisher and Author references, if provided, must also resolve to existing records.

### User feedback

- Successful create/update/delete actions must show a confirmation message.
- Validation failures must show the specific error(s) and preserve the user's entered values in the form.

## Technical Requirements

- **Language/runtime:** PHP 8.4
- **Framework:** Symfony 8.x
- **Database:** MySQL 8.x, accessed via Doctrine ORM
- **Templating:** Twig
- **Dependency management:** Composer
- **Business logic separation:** Entity creation/update/validation logic must live in dedicated `*Service` classes, not directly in controllers.
- **Logging:** Application logs must be written to `stdout` (via Monolog), not to log files, so they can be captured uniformly by a container runtime in later DevOps phases.

## Non-Functional Requirements

- **Local reproducibility:** A fresh clone of the repository must be runnable locally via `composer install`, a database migration run, and `symfony server:start`, with no manual file creation required.
- **Environment awareness:** Configuration (database credentials, secrets) must be sourced from `.env`, not hardcoded.

## Explicitly Out of Scope (for now)

These are known, intentional gaps — not defects — deferred until the DevOps phase of the project:

- User authentication / authorization
- Payment processing, cart, or order management
- Automated tests
- Containerization (Docker)
- CI/CD pipeline
- Staging/production environment configuration
- Deployment target and release process

## Acceptance Criteria (current phase)

- [x] All four entities have working CRUD via HTML forms
- [x] Book correctly enforces its Category/Publisher/Author relations
- [x] Dashboard shows accurate live counts
- [x] Application logs are visible on stdout
- [ ] Application containerized
- [ ] CI pipeline in place
