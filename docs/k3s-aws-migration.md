# Staged ECS to k3s migration

This is a single-node learning deployment, not a highly available production
cluster. Terraform creates a private EC2 instance running k3s and a separate
ALB target group. The existing ECS service, listener, RDS database, and ECR
repositories remain in place until you deliberately switch the listener.
The AWS console can be used to inspect these resources; Terraform creates them.

## Prerequisites

- Terraform 1.9+, AWS CLI, kubectl, an AWS account and credentials with the
  permissions needed to manage the resources. The existing
  `terraform/network` deployment and its state must be available.
- A machine with private network access to the VPC and node port 6443, for
  example through a VPN. An SSM session alone is not network access for the
  Helm provider. The node needs outbound access through the existing NAT
  gateway for k3s installation, image pulls, and SSM.
- An RDS backup and a tested rollback plan. The same RDS database is used by
  both deployments; schema migrations are not automatically reversible.
- A PHP image built from this version of `docker/php/entrypoint.sh`, pushed to
  the existing ECR PHP repository under a new immutable tag. Old images treat
  k3s as local development and attempt to connect to MySQL as root. The chart
  uses the public `nginx:stable-alpine` image, not the ECS nginx image.
- A secure Terraform backend with encryption, locking and restricted access.
  The existing network state already contains sensitive data. The separate
  Helm configuration does not fetch application passwords, but Kubernetes
  stores the runtime secret and its kubeconfig grants cluster-admin access.

## 1. Create the node

Apply the network configuration first to expose its new output values; leave
`k3s_target_group_arn` unset. Check the plan for unrelated changes before
applying. Existing state must be used, not a newly initialized empty state.

In `terraform/k3s`, create an untracked `terraform.tfvars` using values from
`terraform output` in `terraform/network`:

```hcl
aws_region                 = "us-east-1"
vpc_id                     = "vpc-..."
private_subnet_id          = "subnet-..."
alb_security_group_id      = "sg-..."
database_security_group_id = "sg-..."
api_client_cidr            = "10.0.2.10/32" # private address of your Terraform runner
ecr_repository_url         = "123456789012.dkr.ecr.us-east-1.amazonaws.com/bookhive-app"
bucket_arn                 = "arn:aws:s3:::your-existing-bucket"
k3s_version                = "REPLACE_WITH_SUPPORTED_K3S_VERSION"
```

Use the existing network outputs `vpc_id`, `private_subnet_a_id`,
`alb_security_group_id`, `database_security_group_id`, `ecr_repository_url`
and `s3_bucket_arn`. Adjust `api_client_cidr` to the actual source address
seen by the node. Replace the version placeholder with a currently supported,
pinned release such as `v1.30.14+k3s1` (the example may no longer be
supported); maintain it as security updates become available.

```bash
cd terraform/k3s
terraform init
terraform plan
terraform apply
terraform output
```

The node is private and has no SSH ingress. Use Systems Manager Session Manager
to inspect `/var/log/cloud-init-output.log`, `sudo systemctl status k3s`, and
`sudo systemctl status k3s-ecr-refresh.timer`. The timer refreshes the
`default/ecr-pull` Kubernetes secret every six hours; check
`sudo systemctl status k3s-ecr-refresh.service` for errors. To create it
immediately, run `sudo systemctl start k3s-ecr-refresh.service`.

The EC2 role permits ECR reads and S3 access to `authors/*`. IMDSv2 hop limit
2 lets application pods use that role; **other pods on this node can also
obtain its credentials**. Do not run untrusted workloads here. A production
design needs workload-specific credentials and isolation.

## 2. Configure access and runtime secrets

Securely copy `/etc/rancher/k3s/k3s.yaml` from the node to the private
Terraform runner, restrict the file to its owner, and replace `127.0.0.1`
in its server URL with the node's private IP from `terraform output`.
Treat the file as a cluster-admin credential. Verify connectivity:

```bash
kubectl --kubeconfig /secure/path/k3s.yaml get nodes
kubectl --kubeconfig /secure/path/k3s.yaml -n default get secret ecr-pull
```

Create a Kubernetes secret named `bookhive-runtime` in `default`, independently
of Terraform. It must have `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and
`APP_SECRET` keys. Use the existing RDS credentials and the existing Symfony
APP_SECRET from Secrets Manager, not new values. For example, after securely
preparing a restricted local env file with those keys:

```bash
kubectl --kubeconfig /secure/path/k3s.yaml -n default \
  create secret generic bookhive-runtime --from-env-file=/secure/path/runtime.env
```

Delete the temporary env file securely according to your environment's
policies. Do not commit it or put passwords in Terraform variables, shell
history, or command-line arguments. Protect Kubernetes API access, secret
storage, backups and node access. Updating this secret requires restarting
the PHP deployment; secret rotation is not automated.

## 3. Deploy the chart

In `terraform/k3s-helm`, create an untracked `terraform.tfvars`:

```hcl
kubeconfig_path = "/secure/path/k3s.yaml"
php_image       = "123456789012.dkr.ecr.us-east-1.amazonaws.com/bookhive-app"
image_tag       = "your-new-image-tag"
db_host         = "your-rds-hostname"
aws_region      = "us-east-1"
s3_bucket       = "your-existing-bucket"
```

Use the RDS hostname without `:3306`. Then run:

```bash
cd terraform/k3s-helm
terraform init
terraform plan
terraform apply
kubectl --kubeconfig /secure/path/k3s.yaml -n default get pods,svc
```

The chart uses RDS and does not create its MySQL deployment. Cleanup is
disabled until its database and credential handling is adapted for RDS.
Check pod logs, migrations and S3 upload/download before routing traffic.
From an SSM session on the node, test
`curl -fsS http://<node-private-ip>:30080/health`. Only the ALB security
group can access the application NodePort from outside the node. The new
target group may show `unused` until the listener points to it; ALB health
checks begin after association.

## 4. Cut over and roll back

Set `k3s_target_group_arn` in the **existing** `terraform/network` variables
to the `target_group_arn` output from `terraform/k3s`. Review and apply the
network plan: the intended traffic change is the HTTPS listener switching to
the new target group. The existing network configuration may also plan its
snapshot provisioners; review every proposed action before applying.
The existing HTTP-to-HTTPS redirect and certificate remain in use. Verify the
application via the ALB, including `/health`. To roll back, unset the variable
and apply the network configuration again; make sure an ECS task is running
and healthy first. Database migrations may prevent a safe rollback.

This staged configuration deliberately leaves ECS resources in place. Disable
the ECS deployment steps in the current GitHub Actions CD workflow before
cutover; they still deploy to ECS and do not update k3s. Replace them with a
private-network-capable k3s deployment process before relying on automated
releases. After cutover is proven, separately remove ECS resources,
ECS-only IAM and alarms, and update the documentation and CD workflow.
Do not destroy the existing network stack: it also owns RDS, ECR and S3.

One node is a single point of failure; replacing it loses node-local state and
causes downtime. A replacement needs a new kubeconfig and runtime secret before
reapplying the Helm configuration. Changes to node user data trigger instance
replacement. Neither k3s upgrades, Kubernetes backups, log shipping nor secret
rotation are automated. ALB, NAT, RDS and EC2 all incur ongoing charges.
Destroy the Helm release before destroying the node, and restore the listener
to ECS before removing the k3s target group.
