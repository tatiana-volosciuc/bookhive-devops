# Why ECS Instead of Kubernetes — Bookhive

## Purpose

This project is a DevOps learning exercise, and container orchestration is one of the things it's meant to teach. Kubernetes is the default answer most people reach for when they hear "container orchestration," so it's worth explaining explicitly why this project uses **ECS on Fargate** instead.

## TL;DR

Bookhive is a single Symfony app (php-fpm + nginx sidecar) with one task definition, one service, and one ALB. Kubernetes is built to solve problems — multi-team platforms, hundreds of services, complex scheduling, custom controllers — that this project doesn't have. Running it here would mean learning and operating a lot of machinery whose value never actually gets exercised. ECS gives the same core lesson (define a task, run it behind a load balancer, scale it, ship logs/metrics, roll out new versions) with a fraction of the operational surface area, which is a better trade for a learning project with a single maintainer.

## 1. What this project actually needs

Looking at the [network plan](./network-plan.md) and the Terraform in `terraform/network/`, the whole runtime is:

- One ECS service running one task definition (php-fpm container + nginx sidecar, sharing a network namespace)
- One ALB in front of it
- One RDS instance
- Logs to CloudWatch, secrets from Secrets Manager/SSM

There's no need for multiple services talking to each other, no need for custom scheduling constraints, no need for a service mesh, and no multi-team or multi-tenant concerns. That's the entire workload this orchestrator has to manage.

## 2. What Kubernetes would add, and why that's a cost here, not a benefit

| Kubernetes concept  | What it requires you to learn/operate                                                                                                                                                         | Does Bookhive need it?                                                    |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Control plane (EKS) | Cluster version upgrades, API server/etcd management (less so on EKS, but still cluster-level upgrades and add-on compatibility)                                                              | No — one service doesn't need a scheduler this general                    |
| Node management     | Node groups or Fargate profiles, kubelet, CNI plugin choice (e.g. VPC CNI), node-level patching                                                                                               | No — ECS Fargate already removes host management entirely                 |
| YAML surface area   | Deployments, Services, Ingress, ConfigMaps, Secrets, HPA, NetworkPolicies, RBAC, ServiceAccounts                                                                                              | A task definition + one service covers the same ground for one app        |
| Ingress/networking  | Choosing and configuring an Ingress controller, understanding `Service` types (ClusterIP/NodePort/LoadBalancer)                                                                               | ALB integration with ECS is a first-class, built-in, one-resource concept |
| Secrets             | Still needs an external secrets strategy (K8s Secrets are only base64, not encrypted-at-rest by default) — would likely still want Secrets Manager + something like External Secrets Operator | ECS reads directly from Secrets Manager/SSM — no extra controller needed  |
| Observability       | Usually needs its own stack (Prometheus/Grafana, or a managed equivalent) to get what CloudWatch gives ECS for free                                                                           | CloudWatch Logs/metrics work out of the box with zero extra components    |
| Cost                | EKS has a per-cluster control plane fee on top of compute, plus the extra IAM/networking setup to wire OIDC, IRSA, etc.                                                                       | Fargate is pay-for-what-you-run, no separate control plane charge         |

None of the column 2 items are _bad_ to learn — they're valuable, widely-used skills. The point is that for a single-service app, almost all of that machinery would sit unused: no second service to isolate with a `NetworkPolicy`, no fleet of nodes to bin-pack, no multi-tenant RBAC boundaries to enforce. Setting it up would mean configuring it once and never seeing why it exists.

## 3. Why ECS fits the goals of this project better

- **Fewer concepts, same lesson.** ECS still teaches the fundamentals this project is here to teach: task definitions (≈ Pod specs), services (≈ Deployments), target groups/ALB integration (≈ Ingress/Service), CloudWatch (≈ cluster observability), and rolling deployments. The mapping of concepts is close enough that moving to Kubernetes later, if ever needed, isn't starting from zero.
- **Fargate removes node management entirely.** No AMIs, no node group sizing, no patching. That matches the project's deliberate choice (see the [network plan](./network-plan.md#32-core-aws-services)) to avoid server management wherever possible.
- **Tighter AWS integration, less glue code.** Secrets Manager, ECR, ALB, and CloudWatch all plug into ECS directly via Terraform resources already in `terraform/network/` (`ecs.tf`, `alb.tf`, `secrets.tf`, `cloudwatch.tf`), with no extra controllers or operators to install and maintain.
- **Smaller blast radius for a solo learner.** One person maintaining this project benefits from an orchestrator with fewer moving parts to misconfigure, monitor, and keep patched.
- **Cost-appropriate.** No EKS control plane fee, no idle node capacity to size around — spend scales with the one service actually running.

## 4. When Kubernetes would make more sense

This isn't a permanent "Kubernetes is wrong" position — it's a "Kubernetes is the wrong tool for _this_ project's current shape" position. Kubernetes would earn its complexity if the project grew to include things like:

- Multiple independent services/teams needing shared cluster infrastructure with strong isolation (namespaces, RBAC, quotas)
- A need for portability across clouds or on-prem, rather than being AWS-only
- Custom scheduling needs (GPU workloads, affinity/anti-affinity rules, StatefulSets with complex storage needs)
- An existing ecosystem of Helm charts/operators that would be reused rather than built from scratch

None of those apply to Bookhive today. If they ever do, the ECS experience here (task definitions, service discovery via ALB, CloudWatch logging, secrets injection) transfers conceptually to Kubernetes resources.

## 5. Summary

ECS on Fargate was chosen because it matches the actual shape of this workload — one service, one database, one load balancer — without requiring the operation of a general-purpose scheduler and all its supporting infrastructure. It still teaches the core orchestration concepts this project exists to practice, with a much smaller amount of YAML, fewer components to keep patched, and no separate control-plane cost. Kubernetes remains the right choice for larger, multi-service, multi-team platforms — just not for this one, at this size.

