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
- [x] Docker network created: `docker network create bookhive-net`
- [x] docker run -d -p 8080:8000 --name bookhive-app   --entrypoint php   bookhive:app   -S 0.0.0.0:8000 -t public public/index.php
- [x] Confirmed `curl -i http://localhost:8080/health` returned expected response.

## 7. "If this instance dies right now" — actual current answer

Write the true answer for what you actually built, not the ideal one:

- Is there an Auto Scaling Group? Y/N — if no, a dead instance means: It means the EC2 instance stops running and isn't come back on it's own.
- Is app data stored anywhere off-instance (RDS/S3/DynamoDB/EFS)? Not yet, planning RDS for db and S3 for media files.

## 8. Teardown

- [x] Instance terminated: `i-0cdb924d5bdab41c4`
- [x] NAT Gateway deleted (if created)
- [x] Elastic IP released (if allocated)
- [x] Security groups deleted

---
*Last updated: 07/09/2026 by Tatiana Volosciuc*
