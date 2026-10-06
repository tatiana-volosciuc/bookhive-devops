# Cost Model & Well-Architected Review Prep

Prep notes for defending this project's cost and architecture out loud, without notes.
Figures are us-east-1 on-demand list prices (verify against Cost Explorer before relying on them — they drift).

## 1. Cost model

### 1.1 What's actually billed 24/7, regardless of traffic

These three run continuously even while `desired_count = 0` on the ECS service —
they are the real "always-on" cost floor, not the app itself:

| Resource                                | Rate                             | Monthly (730h)  |
| --------------------------------------- | -------------------------------- | --------------- |
| NAT Gateway (`aws_nat_gateway.main`)    | $0.045/hr + $0.045/GB processed  | ~$32.85 + data  |
| ALB (`aws_lb.main`)                     | $0.0225/hr + ~$0.008/LCU-hr      | ~$16.43 + LCU   |
| RDS `db.t3.micro` single-AZ (20 GB gp2) | $0.017/hr + $0.115/GB-mo storage | ~$12.41 + $2.30 |

**Fixed floor ≈ $64/month before a single request is served.**

### 1.2 Everything else (small, mostly rounding)

| Resource                                                          | Monthly                                       |
| ----------------------------------------------------------------- | --------------------------------------------- |
| Fargate task (0.5 vCPU / 1 GB), only while `desired_count ≥ 1`    | ~$18.02 if run 24/7; ~$0 at `desired_count=0` |
| Secrets Manager (RDS-managed secret + `app_secret`)               | $0.40 × 2 = $0.80                             |
| CloudWatch (2 alarms, 3 log groups @ 7-day retention, low volume) | ~$0.30–0.50                                   |
| S3 (author photos, tiny volume)                                   | <$1                                           |
| ECR (2 repos, ≤20 images each, lifecycle-capped)                  | ~$0.20                                        |
| SNS (1 topic, 1 email sub)                                        | negligible                                    |

### 1.3 How to validate "within 20% of actual"

1. Pull real spend: **Cost Explorer → group by Service, last 30 days** (or `aws ce get-cost-and-usage`).
2. Compare against the table above. If it's off by more than ~20%, the gap is almost always one of:
   - **NAT data-processing GB** — the model assumes near-zero (no runtime outbound calls, per `docs/network-plan.md`); a bad build loop or forgotten `docker pull` spree inflates this.
   - **Partial month** — Cost Explorer shows day-1-to-today, the model assumes a full 730h month.
   - **Fargate uptime drift** — if the task was left at `desired_count=1` for testing instead of scaled back to 0, that's ~$0.60/day you'll find instantly by checking ECS service history against the date range.
   - **EIP sitting unattached** — `aws_eip.nat` only free while attached to the running NAT; a `destroy`/`apply` churn that leaves it briefly orphaned adds $0.005/hr.
   - **Free Tier credits** still active on the account, making actual billed cost look lower than the model (not a modeling error — explain this distinction explicitly, it's a common gotcha).

## 2. Top 3 line items (memorize these, not the spreadsheet)

1. **NAT Gateway — ~$33/mo** — biggest single line, runs whether or not the app is deployed, exists only for build-time package pulls (not runtime traffic).
2. **ALB — ~$17/mo** — fixed hourly charge for TLS termination/routing, independent of request volume at this scale.
3. **RDS db.t3.micro single-AZ — ~$15/mo** — compute + 20 GB storage, no Multi-AZ premium (deliberately deferred, documented in `docs/network-plan.md`).

One-liner: _"NAT, ALB, and RDS are ~$64/month combined and run 24/7 regardless of whether the ECS task is even scaled up — the compute itself is the cheap part."_

## 3. Well-Architected review — honest weaknesses

1. **Reliability — single NAT Gateway, single AZ.** It sits only in `public_a`; if that AZ has an issue, `private_b` loses outbound access too, even though the subnet layout implies AZ redundancy.
2. **Reliability — RDS is single-AZ by deliberate choice**, and fully ephemeral (`skip_final_snapshot = true` unconditionally) — every `terraform destroy` discards all data with no recovery path. Fine for a sandbox, a real finding if anyone mistook this for prod.
3. **Operational Excellence — local Terraform state**, no S3+DynamoDB remote backend, no locking. Concurrent applies or a lost laptop are real risks, and state currently isn't protected from drift/corruption.
4. **Security — ECR `scan_on_push` is enabled but nothing gates deployment on the results.** A critical CVE in a pushed image doesn't block the task definition from using it.
5. **Security — no WAF in front of the internet-facing ALB.** Security groups control network access but there's no L7 protection (rate limiting, common exploit signatures).
6. **Operational Excellence — alarm coverage is thin.** Only ECS memory and ALB 5xx are wired up; no RDS CPU/storage/connections alarms, no ECS CPU alarm, no latency alarm.
7. **Cost — the NAT Gateway's own stated justification (`docs/network-plan.md` §3.6) is build-time package pulls only**, yet it runs continuously; the plan's own open question ("whether a NAT Gateway is needed long-term") was never closed out.
8. **Security — secret rotation is manual.** Neither the RDS-managed secret nor `app_secret` has an automated rotation schedule configured.

(That's 8 — pick 5–6 that land best live; don't recite all of them unless asked to go deep.)

## 4. Written answers — rehearse these verbatim-ish

**"How would you halve the cost?"**

> The three always-on resources — NAT, ALB, RDS — are ~$64/mo of a likely ~$85/mo full bill, so that's where the leverage is.
>
> 1. Drop the NAT Gateway entirely: the app makes no outbound calls at runtime (only Composer/package pulls at build time), and we already have a free S3 gateway endpoint — add interface endpoints for ECR (`api`/`dkr`) and CloudWatch Logs and NAT becomes unnecessary. That alone removes ~$33/mo, over half the fixed floor.
> 2. Switch Fargate to `FARGATE_SPOT` for this non-prod workload (up to ~70% off compute), and keep the existing scale-to-zero pattern (`desired_count = 0` by default) between test runs — already partially done.
> 3. Stop RDS outside active hours — RDS supports `aws rds stop-db-instance` for up to 7 days at a time with auto-restart; stopping nights/weekends in a learning environment removes a large chunk of that ~$15/mo.
>    Combined, that's comfortably over 50% off the fixed cost without touching the app.

**"How would you make this production-ready?"**

> 1. **Reliability:** Multi-AZ RDS with automated backups and point-in-time recovery; NAT redundancy per-AZ (or remove it via VPC endpoints and avoid the redundancy question entirely).
> 2. **Operational Excellence:** ECS service autoscaling (target-tracking on CPU/memory, min capacity ≥ 1), remote Terraform state (S3 backend + DynamoDB lock table) instead of local state, expanded CloudWatch alarms (RDS CPU/storage/connections, ECS CPU, ALB target latency).
> 3. **Security:** WAF in front of the ALB, CI/CD gated on ECR scan findings before a task definition can reference a new image, automated secret rotation, a real domain through Route 53 with a publicly-issued ACM cert (currently a self-signed import for lack of a domain).
> 4. **Cost governance:** cost allocation tags, budgets/alerts, and a documented decision log for every "deferred for cost" choice (Multi-AZ, NAT, etc.) so they're revisited on a schedule, not forgotten.
>    I'd frame this as a prioritized backlog, not "redo everything" — Multi-AZ RDS and remote state are the two I'd do first.

