# Network Plan — Bookhive

## Purpose

This document plans how network traffic flows to, from, and within Bookhive — first in local development, then for an eventual cloud deployment. It's written ahead of containerization/CI-CD work so those phases have a target topology to build toward, rather than improvising networking decisions later.

## 1. Current state (local development)

| Component | Address | Notes |
|---|---|---|
| Symfony app (dev server) | `127.0.0.1:8000` | Symfony CLI local server, HTTPS via self-signed cert |
| MySQL | `127.0.0.1:3306` | Local MySQL instance, not containerized yet |

No network isolation exists today — everything runs directly on the host machine, app and database both reachable only via `localhost`. This is fine for local dev but doesn't reflect how the pieces will need to talk to each other once containerized.

## 2. Target state (containerized, still local)

Once Docker Compose is introduced, services move onto a private Docker network:

| Service | Internal hostname | Internal port | Exposed to host? |
|---|---|---|---|
| `app` (PHP-FPM + Symfony) | `app` | 9000 (FPM) | No (via web server only) |
| `web` (Nginx/Caddy) | `web` | 80/443 | Yes → `localhost:8000` |
| `db` (MySQL) | `db` | 3306 | Optional, for local inspection only |

**Key decisions to make here:**
- App and database communicate over the Docker-internal network using service names (`db`, not `127.0.0.1`) — `DATABASE_URL` will need to change accordingly.
- Only the web server container exposes a port to the host; the app and database containers should not be directly reachable from outside the Docker network.

## 3. Target state (cloud deployment — AWS)

### 3.1 Network topology

```
Internet
   │
   ▼
[Application Load Balancer]  ← TLS termination via ACM certificate
   │
   ▼
[VPC]
   │
   ├── Public subnets (2 AZs)   — ALB only
   │
   ├── Private subnets (2 AZs) — App (ECS/EC2), no public IP
   │
   └── Private subnets (2 AZs) — RDS (MySQL), isolated further via its own subnet group
```

### 3.2 Core AWS services

| Layer | Service | Notes |
|---|---|---|
| Compute | **ECS (Fargate)** or EC2 | Fargate recommended — no server patching, scales with the container image built in CI |
| Load balancing | **Application Load Balancer (ALB)** | Public-facing, terminates TLS, forwards to app target group |
| Database | **RDS for MySQL** | Multi-AZ optional later; start single-AZ for a learning project to control cost |
| Networking | **VPC** with public + private subnets across 2 Availability Zones | Standard 2-AZ setup for basic resilience |
| DNS | **Route 53** | Domain (TBD) → ALB via alias record |
| TLS | **ACM (AWS Certificate Manager)** | Free, auto-renewing cert attached to the ALB listener |
| Container registry | **ECR** | CI pipeline pushes built images here; ECS pulls from here |
| Secrets | **Secrets Manager** or SSM Parameter Store | `DATABASE_URL`, `APP_SECRET` — never baked into the image |
| Logs | **CloudWatch Logs** | App already logs to stdout — ECS/Fargate ships stdout to CloudWatch automatically, no extra config needed |

### 3.3 Subnets

- **Public subnets** (2 AZs) — ALB only
- **Private subnets** (2 AZs) — ECS tasks (app), no public IP assigned
- **Private subnets** (2 AZs) — RDS, via a dedicated DB subnet group, no route to the internet at all

### 3.4 Security groups (planned)

| Security group | Inbound from | Port | Purpose |
|---|---|---|---|
| `alb-sg` | 0.0.0.0/0 | 443 | Public HTTPS in |
| `app-sg` | `alb-sg` only | 8000 (container port) | ALB → app tasks |
| `db-sg` | `app-sg` only | 3306 | App → RDS |

No security group allows direct internet ingress to `app-sg` or `db-sg` — both are only reachable from the layer above them.

### 3.5 DNS

- Domain (TBD) registered or delegated to **Route 53**
- A-record (alias) → ALB DNS name
- No direct DNS entry ever points at ECS tasks or RDS

### 3.6 Outbound access

App tasks route outbound traffic (for Composer/package pulls during build, not runtime) through a **NAT Gateway** in the public subnet — only needed at build/deploy time; the running app itself makes no outbound calls today, so this can be tightened later (e.g., VPC endpoints only, no NAT) once confirmed unnecessary at runtime.

## 4. Open questions / decisions still needed

- [ ] ECS Fargate vs. EC2-backed ECS (Fargate recommended for a learning project — less to manage)
- [ ] Single app task vs. multiple (affects whether session storage needs to move off native PHP sessions to something shared, e.g. ElastiCache/Redis)
- [ ] RDS instance size and Multi-AZ vs. single-AZ (cost vs. resilience tradeoff)
- [ ] Domain name to register/use with Route 53
- [ ] Whether a NAT Gateway is needed long-term, or if VPC endpoints (S3, ECR) can remove that cost entirely

## 5. Non-goals

- No CDN planning at this stage (traffic volume doesn't warrant it yet)
- No multi-region setup — single region/zone is sufficient for this project's purpose
