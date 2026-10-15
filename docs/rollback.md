# Runbook: BookHive Release Rollback

| Parameter | Value |
|---|---|
| Region | `us-east-1` |
| ECS cluster | `bookhive-cluster` |
| ECS service | `bookhive-service` |
| Task definition family | `bookhive-app` |
| ECR repositories | `bookhive-app` (PHP), `bookhive-nginx` (nginx) |
| Image tags | Git commit SHA |

**Rollback time (measured in a rollback drill):**
- Traffic back on the previous version: **[X] min**
- Service reports "stable": **[Y] min**
- Date of last drill: **[date]**

> Fill in these values after a rollback drill. Do not state estimates as facts.

---

## 0. Prerequisites

- The on-call engineer has AWS access (SSO or an admin profile) with `ecs:UpdateService`, `ecs:DescribeServices`, `ecs:DescribeTaskDefinition` and `ecs:ListTaskDefinitions`. The GitHub Actions role is **not** used for rollback.
- Old images do not expire in ECR (check the lifecycle policy of both repositories).
- The deployment circuit breaker is enabled (see "Automatic rollback").

```bash
export AWS_REGION=us-east-1
```

## 1. Confirm the release is the cause

1. ECS → `bookhive-service` → Events: time of the last deployment.
2. CloudWatch Logs: errors after that time.
3. `curl -s https://<domain>/health`
4. 5xx errors and unhealthy targets on the ALB.

If the problem is unrelated to the release (database, external service, infrastructure), a rollback will not help.

## 2. Find the current and previous revision

```bash
# current revision
aws ecs describe-services \
  --cluster bookhive-cluster --services bookhive-service \
  --query 'services[0].taskDefinition' --output text

# recent revisions
aws ecs list-task-definitions \
  --family-prefix bookhive-app --sort DESC --max-items 5
```

If the current revision is `bookhive-app:42`, roll back to `bookhive-app:41`. Verify its images exist:

```bash
aws ecs describe-task-definition --task-definition bookhive-app:41 \
  --query 'taskDefinition.containerDefinitions[].image'
```

## 3. Perform the rollback

```bash
aws ecs update-service \
  --cluster bookhive-cluster \
  --service bookhive-service \
  --task-definition bookhive-app:41

aws ecs wait services-stable \
  --cluster bookhive-cluster --services bookhive-service
```

If a new deployment is still in progress, this command aborts it and returns the service to the specified revision.

## 4. Prevent the broken code from being redeployed

CD runs on every push to `main`. Immediately after the rollback:

1. Tell the team: **do not merge to `main`**.
2. Revert the commit: `git revert <bad-sha>` and push, or temporarily disable the CD workflow (Actions → CD → Disable workflow).
3. Re-enable CD only after the fix is ready.

## 5. Verify the result

- [ ] ECS: `runningCount == desiredCount`, no new errors in Events
- [ ] `curl -s https://<domain>/health` returns `"status":"ok"`
- [ ] 5xx rate on the ALB is back to normal
- [ ] Main user flow checked manually (login, home page)

## 6. If the release included a database migration

| Situation | Action |
|---|---|
| Migration is backward compatible (nullable columns, new tables) | Roll back code only, leave the schema |
| Migration broke the old code (column dropped or renamed) | Prefer a forward hotfix. If not possible: `doctrine:migrations:migrate prev` via a one-off ECS task |
| Data is corrupted | Restore from the RDS snapshot `pre-deploy-<sha>` or point-in-time recovery into a new instance. Data written after the snapshot is lost. Takes 30+ minutes |

Rules: take an RDS snapshot before every production migration; make schema changes following expand/contract (add first, drop only in a later release).

```bash
aws rds create-db-snapshot \
  --db-instance-identifier <db-id> \
  --db-snapshot-identifier pre-deploy-<sha>
```

## Automatic rollback (circuit breaker)

Enable once:

```bash
aws ecs update-service \
  --cluster bookhive-cluster \
  --service bookhive-service \
  --deployment-configuration \
  "deploymentCircuitBreaker={enable=true,rollback=true}"
```

If new tasks fail to start or fail health checks, ECS automatically returns to the last healthy revision. The CD step "Deploy ECS task definition" (`wait-for-service-stability`) will then fail.

## 7. After the incident

1. Write a short post-mortem: what broke, how it was detected, how long the outage lasted.
2. Add a check to CI that would have caught the issue.
3. Update the measured rollback time at the top of this document.

## Rollback drill (quarterly)

1. Deploy a harmless change.
2. Run sections 2-3 and note the start time.
3. In ECS → Service → Events, find when the new tasks became healthy (X) and when the service reached steady state (Y).
4. Record X, Y and the date at the top of this document.
