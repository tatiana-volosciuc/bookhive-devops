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

## 3. Security groups

- [x] Instance SG `sg-02d60cadb782f8b34`:
    - [x] Inbound rules: **none** (confirm this — the whole point is no port 22)
    - [x] Outbound: 443 to VPC endpoints / 0.0.0.0/0 (NAT case)

## 4. Instance launch

- [x] AMI used: ami-00c5ff799c96080f2
- [x] Instance type: t3.micro
- [x] Launched in private subnet `subnet-0b6d7fb8f04dc8cc7`, no public IP assigned


## 5. App setup (mark each as automated-in-user-data or done-by-hand)

- [x] Docker installed — [ ] via user-data / [ ] by hand over SSM session
  (no Compose plugin needed — running plain `docker run`, not Compose)
- [x] Git installed 
- [x] App code source: `git clone https://github.com/tatiana-volosciuc/bookhive-devops.git` into `/opt/bookhive`
- [ ] `.env` / `DATABASE_URL` and other secrets set on the instance — how: __________
  (note: for this phase these live directly on the instance, passed via `-e` flags on `docker run`; flag this as a gap, not a final answer — Secrets Manager/SSM Parameter Store is the real target per the network plan)
- [ ] Docker network created: `docker network create bookhive-net`
- [ ] `db` container run:
```
  docker run -d --name db --network bookhive-net \
    -e MYSQL_DATABASE=____ -e MYSQL_USER=____ \
    -e MYSQL_PASSWORD=____ -e MYSQL_ROOT_PASSWORD=____ \
    -v db-data:/var/lib/mysql mysql:8
```
- [ ] `app` image built: `docker build -t bookhive-app .`
- [ ] `app` container run:
```
  docker run -d --name app --network bookhive-net \
    -e DATABASE_URL="mysql://____:____@db:3306/____" bookhive-app
```
- [ ] `nginx.conf` written with `fastcgi_pass app:9000;` at: `____________`
- [ ] `web` container run:
```
  docker run -d --name web --network bookhive-net -p 8000:80 \
    -v ____________/nginx.conf:/etc/nginx/conf.d/default.conf:ro nginx:alpine
```
- [ ] All three confirmed running: `docker ps` shows `app`, `web`, `db`
- [ ] **Database data volume**: `db-data` — confirmed this is a **named Docker volume on the instance's own root EBS volume**, not a separate EBS volume. It survives container restart/recreate but **not** instance termination.
  (this is the thing that makes "if this instance dies" either survivable or not — write it down precisely, don't leave it vague)
- [ ] Any manual edits made directly on the instance that aren't captured anywhere else: __________
## 6. Verifying access without port 22

- [ ] Connected via: `aws ssm start-session --target i-________`
- [ ] Verified app response via SSM port forwarding:
  ```
  aws ssm start-session --target i-________ \
    --document-name AWS-StartPortForwardingSession \
    --parameters '{"portNumber":["____"],"localPortNumber":["____"]}'
  ```
- [x] Confirmed `curl -i http://localhost:8080/health` returned expected response.

## 7. "If this instance dies right now" — actual current answer

Write the true answer for what you actually built, not the ideal one:

- Is there an Auto Scaling Group? Y/N — if no, a dead instance means: __________
- Is app data stored anywhere off-instance (RDS/S3/DynamoDB/EFS)? __________
- MySQL running as a container on this same instance — is its data volume on a **separate EBS volume** that could theoretically be reattached, or on the instance's root volume / an unbacked container layer? __________
- If no ASG and no external data, what is the manual recovery procedure? __________
- Honest bottom line for this phase: __________

## 8. Teardown

- [x] Instance terminated: `i-0cdb924d5bdab41c4`
- [x] NAT Gateway deleted (if created)
- [x] Elastic IP released (if allocated)
- [ ] Security groups deleted
- [ ] Subnets / VPC deleted (if purpose-built for this)

---
*Last updated: 07/09/2026 by Tatiana Volosciuc*
