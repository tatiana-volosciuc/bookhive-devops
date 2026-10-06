# Architecture — as actually deployed

Last verified against `terraform/network` at commit `e3afcb4` (2026-10-06). If the
Terraform changes, update this diagram in the same PR — it documents what's
really provisioned, not the aspirational plan (see [docs/network-plan.md](network-plan.md)
for that).

```mermaid
flowchart TB
    Internet((Internet))
    GHA[GitHub Actions CI/CD<br/>OIDC, no long-lived creds]
    Email[/Email subscriber/]

    subgraph AWS["AWS account — us-east-1"]
        subgraph VPC["VPC — 10.0.0.0/16"]
            IGW[Internet Gateway]

            subgraph PubA["Public subnet A"]
                ALB[Application Load Balancer<br/>:443 HTTPS → :80 redirect]
                NAT[NAT Gateway]
            end

            subgraph PubB["Public subnet B"]
                ALB_B[" "]
            end

            subgraph PrivA["Private subnet A"]
                ECS1[ECS Fargate task<br/>nginx sidecar + php-fpm]
                RDS[(RDS MySQL 8.0<br/>single-AZ, db.t3.micro)]
            end

            subgraph PrivB["Private subnet B"]
                ECS2[ECS Fargate task<br/>2nd AZ, scales with service]
            end

            S3EP[[S3 Gateway VPC Endpoint]]
        end

        ECR[(ECR<br/>bookhive-app, bookhive-nginx)]
        SM[(Secrets Manager<br/>RDS secret + APP_SECRET)]
        CWL[(CloudWatch Logs<br/>app, nginx, RDS error/slowquery)]
        CWA{{CloudWatch Alarms<br/>ECS memory, ALB 5xx}}
        SNS[SNS topic]
        S3[(S3 bucket<br/>author photos — private)]
    end

    Internet -- "HTTPS :443" --> ALB
    ALB -- "attached to" --- IGW
    ALB -- ":80 container port" --> ECS1
    ALB -. "same target group" .-> ECS2
    ECS1 -- ":3306" --> RDS
    ECS1 -- "GetObject/PutObject authors/*" --> S3EP --> S3
    ECS1 -. "image pull, GetSecretValue, PutLogEvents — no interface endpoints exist" .-> NAT
    NAT --- IGW
    NAT -. egress .-> ECR
    NAT -. egress .-> SM
    NAT -. egress .-> CWL
    GHA -- "push :sha-tagged images" --> ECR
    RDS --> CWL
    CWA --> SNS --> Email
```

## Reading this diagram

- **NAT Gateway is a runtime dependency, not just a build-time one.** The ECS
  task's security group egress comment in `terraform/network/security_groups.tf`
  is explicit: _"Tasks need outbound for ECR/Secrets/S3 via the NAT gateway."_
  There are no VPC interface endpoints for ECR (`api`/`dkr`), Secrets Manager, or
  CloudWatch Logs — only the S3 **gateway** endpoint exists. So every task start
  (image pull + secret fetch) and every log line genuinely leaves the VPC through
  the NAT Gateway today. This is a correction against `docs/network-plan.md`,
  which assumed no runtime outbound calls — that assumption is only true for the
  _application's own logic_, not for the ECS control plane around it.
- **Single NAT Gateway, single AZ (`public_a` only).** Private subnet B's task
  still routes outbound through the same NAT in subnet A — there's no per-AZ
  redundancy, and no AZ-local fallback if that NAT/AZ has an issue.
- **ECS desired_count defaults to 0.** Both task slots in the diagram are
  illustrative of the service's _possible_ placement across 2 AZs, not a
  guarantee two tasks are running — check `terraform output ecs_service_name`
  and the live desired count before assuming steady-state capacity.
- **Not shown because it doesn't exist yet:** Route 53 hosted zone / real domain,
  a CA-issued ACM cert (current cert is a self-signed import), WAF, CloudFront,
  and any VPC interface endpoints.

## Keeping this in sync

Treat drift between this file and `terraform/network/*.tf` as a bug. Quick way
to spot drift: `terraform plan` should show no unexpected changes, and anything
added/removed as a `resource` block (new security group rule, new VPC endpoint,
new subnet) is a prompt to update the diagram in the same change.

