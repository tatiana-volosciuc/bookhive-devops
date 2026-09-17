# EC2 App Deployment — Manual Steps Log

> Fill this in as you go. The goal is honesty, not tidiness: if you clicked
> something in the console instead of scripting it, write that down. If you
> guessed at a value, write that down too. Someone (including future you)
> should be able to reproduce this exactly, or know precisely where it
> depends on a human.

## 1. Network

- [x] VPC created: `vpc-007f401ac4eaa5e43` (CIDR: 10.0.0.0/16)
- [x] Private subnet created: `subnet-0b6d7fb8f04dc8cc7` (CIDR: 10.0.12.0/24, AZ: use1-az1 (us-east-1a))
- [x] NAT Gateway in public subnet `subnet-0fec020d280715d3e`, NAT `nat-045a0dad25b7b8164`
- [x] Route table for private subnet confirmed: no route to an Internet Gateway.

## 2. IAM

- [x] Instance role created: i-0cdb924d5bdab41c4 (trusted entity: EC2)
- [x] Instance role created: `arn:aws:iam::089340568250:role/SSM-EC2-Role`
- [x] Managed policy attached: `AmazonSSMManagedInstanceCore`
- [x] Instance profile confirmed attached to the instance at launch. (`aws ec2 associate-iam-instance-profile   --instance-id i-0cdb924d5bdab41c4   --iam-instance-profile Name=SSM-EC2-Role`)
- [x] Add ECR pull permission to this role (`AmazonEC2ContainerRegistryReadOnly` or a scoped policy) so instances can `docker pull` from ECR without embedding credentials.

## 3. Security groups

- [x] Instance SG `sg-02d60cadb782f8b34`:
  - [x] inbound rule needed — Custom TCP, port `8080`, source = **ALB security group** (not `0.0.0.0/0`, not an IP range). This is how the app SG stays closed to the open internet while still accepting traffic from the load balancer.
  - [x] Outbound: 443 to VPC endpoints / 0.0.0.0/0 (NAT case)
  - [x] Confirmed: no inbound rule needed for SSM — Session Manager is outbound-only (agent polls SSM endpoints over the existing 443 egress).
- [x] ALB security group: inbound 443 (or 80 redirecting to 443) from `0.0.0.0/0`; outbound to instance SG on 8080.

## 4. Instance launch

- [x] AMI used: ami-00c5ff799c96080f2
- [x] Instance type: t3.micro
- [x] Launched in private subnet `subnet-0b6d7fb8f04dc8cc7`, no public IP assigned
- [x]  Convert this one-off launch into a **Launch Template**, since the instance will be replaced by an ASG (see section 6).

## 5. App setup (mark each as automated-in-user-data or done-by-hand)

- [x] Docker installed — [ ] via user-data / [ ] by hand over SSM session *(pick one — not yet confirmed which)*
- [x] Git installed — [ ] via user-data / [ ] by hand over SSM session *(pick one)*
- [x] App code source (CURRENT / TO BE REPLACED): `git clone https://github.com/tatiana-volosciuc/bookhive-devops.git` into `/opt/bookhive`, image built on the instance itself.
  - **Problem:** every instance needs git + build tooling + repo access at boot; no version pinning; new instances can silently pull different code than older ones; no rollback.
  - **Fix (planned, not done):** build the image in CI, push to **ECR**, instance only pulls a tagged immutable image:
    ```
    aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin <account>.dkr.ecr.us-east-1.amazonaws.com
    docker pull <account>.dkr.ecr.us-east-1.amazonaws.com/bookhive:app-<tag>
    ```
  - Dockerfile stays in the repo as source of truth; the *build step* moves to CI, not the EC2 instance.
- [x] Docker network created: `docker network create bookhive-net`
- [x] Current run command (dev-server, not production-safe):
  ```
  docker run -d -p 8080:8000 --name bookhive-app --entrypoint php bookhive:app -S 0.0.0.0:8000 -t public public/index.php
  ```
  - **Problem:** `php -S` is a single-threaded dev server, not meant for concurrent production traffic.
  - **Fix (planned, not done):** serve via **nginx** (reverse proxy) + **php-fpm**, either as a sidecar container or baked into the image; nginx and awscli installed via user-data on the launch template.
- [x] Confirmed `curl -i http://localhost:8080/health` returned expected response — health check target for the ALB target group (see section 6).

## 6. Load balancing & auto scaling

- [x] Target group: register targets by instance ID (or via ASG attachment), health check path `/health`, port 8080, protocol HTTP.
- [x] Application Load Balancer: listener on 443 (cert via ACM) or 80→443 redirect, forwarding to the target group above.
- [x] Auto Scaling Group: built from the Launch Template (section 4), using instance profile + user-data + corrected SG from section 3. Start at min/desired/max = 1/1/2 — this is what actually fixes the "dead instance" answer in section 7 below.

## 7. "If this instance dies right now" — actual current answer

Write the true answer for what you actually built, not the ideal one:

- Is there an Auto Scaling Group? **N** (as of this log) — if no, a dead instance means: the EC2 instance stops running and does not come back on its own.
  - *Once section 6 is built, this answer changes to: ASG detects the failed health check and launches a replacement automatically.*
- Is app data stored anywhere off-instance (RDS/S3/DynamoDB/EFS)? **Not yet** — planning RDS for the DB and S3 for media files.

## 8. Teardown

- [x] Instance terminated: `i-0cdb924d5bdab41c4`
- [x] NAT Gateway deleted (if created)
- [x] Elastic IP released (if allocated)
- [x] Security groups deleted

---
*Last updated: 14/09/2026 by Tatiana Volosciuc — revised to correct the inbound SG rule and record planned fixes (ALB/target group/ASG, nginx, ECR/CI-CD) discussed but not yet implemented.*
