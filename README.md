# Bookhive

A Symfony-based bookshop catalog application, built primarily as a **DevOps learning and planning project**.

> **Estimated AWS cost: ~$64/month fixed floor** (NAT Gateway ~$33, ALB ~$17, RDS single-AZ ~$15), running 24/7 regardless of traffic — plus a few dollars for compute/storage/logs when the ECS service is actually scaled up. See [docs/cost-and-wa-review-prep.md](docs/cost-and-wa-review-prep.md) for the full breakdown and cost-cutting options.

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
- Author photos are stored in S3 (via Flysystem) and streamed through the app — the bucket itself is private
- Dockerized local environment: PHP-FPM, Nginx, and MySQL run as separate containers via Docker Compose
- Database migrations run automatically on container startup via a custom PHP entrypoint script
- Automated test suite (PHPUnit) covering core application logic
- CI pipeline runs unit tests, runs real migrations against MySQL, then builds the Docker image and verifies the container boots correctly and runs as a non-root user
- CD pipeline builds/pushes images to ECR and deploys them to an ECS Fargate service on every successful CI run on `main`
- Full AWS deployment target provisioned via Terraform: VPC, ALB, ECS Fargate, RDS MySQL, S3, ECR, CloudWatch alarms/logs (see [Infrastructure (AWS)](#infrastructure-aws) below)

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

### Teardown

```bash
# Stop and remove containers + network, keep the database volume
docker compose down

# Also wipe the database volume (full reset — next `up` starts from an empty DB)
docker compose down -v
```

### Common commands

```bash
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

Two GitHub Actions workflows ([.github/workflows/ci.yaml](.github/workflows/ci.yaml) and [.github/workflows/cd.yaml](.github/workflows/cd.yaml)):

**CI** — runs on every push/PR to `main`:

1. **Test job** — sets up a SQLite test database and runs the PHPUnit suite.
2. **Test-migrations job** — runs real Doctrine migrations against a MySQL 8.0 service container, then checks for schema drift.
3. **Build & verify job** — builds the production Docker image, boots it standalone, checks the `/health` endpoint, and confirms the container runs as a non-root user.

**CD** — triggered automatically when CI succeeds on `main`:

1. Authenticates to AWS via OIDC (no long-lived credentials).
2. Builds and pushes both images (PHP-FPM app + Nginx sidecar) to their ECR repositories, tagged with the Git commit SHA (never `latest` — see [docs/ docker-tagging-scheme.md](docs/%20docker-tagging-scheme.md)).
3. Renders a new ECS task definition with both image tags and deploys it to the `bookhive-service` ECS service, waiting for the rollout to stabilize.

## Infrastructure (AWS)

Provisioned via Terraform in [terraform/network](terraform/network). See [docs/architecture.md](docs/architecture.md) for a diagram kept in sync with what's actually deployed. Current target architecture:

- **Networking** — a VPC across 2 Availability Zones with public subnets (ALB, NAT Gateway) and private subnets (ECS tasks, RDS). A single NAT Gateway provides outbound internet access for the private subnets; an S3 gateway VPC endpoint avoids routing S3 traffic through it.
- **Compute** — ECS Fargate only (no EC2 to patch/manage). Each task runs two containers in one network namespace: the PHP-FPM app and an Nginx sidecar that receives ALB traffic on port 80 and forwards it to `127.0.0.1:9000`. Service starts at `desired_count = 0` and is scaled up manually/by CD after the first image is pushed.
- **Load balancing** — a public Application Load Balancer terminates HTTPS (ACM certificate, TLS 1.2+ policy) and redirects plain HTTP to HTTPS, forwarding to the ECS service's target group.
- **Database** — RDS for MySQL 8.0, single-AZ, in a private DB subnet group with no public access. Master password is managed by RDS and stored in Secrets Manager (`manage_master_user_password`), never set directly in Terraform. The instance is restored from the latest snapshot on each apply.
- **Object storage** — a private S3 bucket (versioned, AES-256 encrypted, public access fully blocked) used for author photos. The app streams objects itself via the ECS task role (`s3:GetObject`/`PutObject`/`DeleteObject` scoped to `authors/*`) — there is no CloudFront/public layer, since there's no static frontend to serve.
- **Container registry** — two ECR repositories (`bookhive-app`, `bookhive-nginx`), immutable tags, image scanning on push, lifecycle policy keeping the last 20 images.
- **Secrets** — Secrets Manager holds the RDS master password and a Terraform-generated `APP_SECRET`; both are injected into the task via the `secrets` block (not baked into the image), readable only by the ECS execution role.
- **IAM** — least-privilege roles: an execution role (ECR pull + Secrets Manager read) and a task role (S3 access + ECS Exec/SSM for `aws ecs execute-command` debugging), no wildcard resource grants.
- **Observability** — application and Nginx logs ship to CloudWatch Logs (`/ecs/bookhive`); RDS error/slow-query logs ship to their own log groups. CloudWatch alarms watch ECS memory utilization and ALB/target 5xx rates, notifying an SNS topic (email subscription).
- **DNS/TLS** — no real domain is registered yet, so the ACM certificate is a self-signed cert imported directly (see `terraform/network/variables.tf`); Route 53 is not yet wired up.

An EC2 + SSM-based deployment path (`terraform/network/ec2.tf`) was prototyped first and is kept commented out as a reference — the project now runs on Fargate exclusively.

## Infrastructure teardown (AWS)

This is a cost-sensitive learning environment — leaving it running is the single biggest way to waste money, so teardown gets the same attention as setup:

```bash
cd terraform/network
terraform destroy
```

- Everything is destroyable in one pass: RDS, S3, and ECR all have `force_destroy`/`force_delete` set outside `prod`, so `destroy` never gets stuck waiting for manual emptying.
- **This is destructive by design.** RDS is fully ephemeral (`skip_final_snapshot = true` outside `prod`) — `destroy` discards all data with no recovery path. That's intentional for an environment rebuilt often, not an oversight.
- No manual scale-down step is needed first — the ECS service already runs at `desired_count = 0` between sessions, and Terraform tears down the service/cluster regardless of task count.
- After `destroy`, double-check the three big recurring costs are actually gone — NAT Gateway, ALB, and RDS (see [cost breakdown](docs/cost-and-wa-review-prep.md)) — in case the run was interrupted partway through.

## Where this is headed

Remaining open items (see [docs/network-plan.md](docs/network-plan.md)):

- Register a real domain and wire up Route 53 + a CA-issued ACM certificate
- Decide whether the NAT Gateway is needed long-term or can be dropped once outbound needs are confirmed
- Move session storage off native PHP sessions before running more than one app task at a time

This README will be updated as those pieces land.

