# Bookhive

A Symfony-based bookshop catalog application, built primarily as a **DevOps learning and planning project**.

## Stack

- **PHP** 8.4
- **Symfony** 8.x
- **MySQL** 8.x via Doctrine ORM
- **Twig** for server-rendered HTML views
- **Composer** for dependency management
- **Docker** / **Docker Compose** for local development and containerized runtime
- **Nginx** as the web server / FastCGI proxy in front of PHP-FPM
- **PHPUnit** for automated tests
- **GitHub Actions** for CI (tests, Docker image build, container smoke tests)

## Current state

- Core entities in place: `Book`, `Author`, `Category`, `Publisher`, with proper relations (ManyToMany, ManyToOne)
- Full CRUD (list/create/edit/delete) for all four entities, both as HTML forms
- Business logic extracted into dedicated `*Service` classes, keeping controllers thin
- Dockerized local environment: PHP-FPM, Nginx, and MySQL run as separate containers via Docker Compose
- Database migrations run automatically on container startup via a custom PHP entrypoint script
- Automated test suite (PHPUnit) covering core application logic
- CI pipeline runs tests, builds the Docker image, and verifies the container boots correctly and runs as a non-root user

## Endpoints

- HTML CRUD pages are available at `/books`, `/authors`, `/categories`, and `/publishers`, with matching JSON create endpoints under `/api/*` for a couple of entities.
- Health endpoint: `/health`

## Local setup (Docker)

The project runs as three containers: `php` (PHP-FPM + app code), `nginx` (web server), and `mysql` (database).

### Prerequisites

- Docker
- Docker Compose

### First-time setup

1. Copy the example environment file and adjust credentials if needed:
   ```bash
   cp .env.example .env
   ```

2. Build and start the stack:
   ```bash
   docker compose up -d --build
   ```

3. On startup, the `php` container automatically:
    - waits for MySQL to be healthy
    - creates the database if it doesn't exist
    - runs pending Doctrine migrations

   You can follow this process in the logs:
   ```bash
   docker compose logs php -f
   ```

4. Visit the app:
   ```
   http://localhost:8080/
   ```
   (or whichever host port is configured for `nginx` in `docker-compose.yml`)

### Common commands

```bash
# Stop the stack
docker compose down

# Stop the stack and wipe the database volume (fresh start)
docker compose down -v

# Rebuild after Dockerfile or dependency changes
docker compose up -d --build

# Run a Symfony console command inside the php container
docker compose exec php php bin/console <command>

# Run the test suite
docker compose exec php vendor/bin/phpunit

# Tail logs for a specific service
docker compose logs php -f
docker compose logs mysql -f
docker compose logs nginx -f
```

## Running tests

Tests run against SQLite in CI for speed, and can be run the same way locally inside the `php` container:

```bash
docker compose exec php vendor/bin/phpunit
```

## CI/CD

GitHub Actions runs on every push and pull request to `main`:

1. **Test job** — installs dependencies, sets up a SQLite test database, and runs the PHPUnit suite.
2. **Build & verify job** — builds the production Docker image, boots it standalone, checks the `/health` endpoint responds correctly, and confirms the container runs as a non-root user.

## Where this is headed

The next phase of work is DevOps-focused rather than feature-focused: hardening the CI/CD pipeline (including migration testing against MySQL in CI), and defining a proper deployment target and release process.

This README will be updated as those pieces land.
