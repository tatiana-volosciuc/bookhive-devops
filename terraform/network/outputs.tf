output "private_subnet_a_id" {
  value = aws_subnet.private_a.id
}

output "vpc_id" {
  value = aws_vpc.main.id
}

# --- S3 Bucket ---
output "s3_bucket_name" {
  value = aws_s3_bucket.main.bucket
}

output "s3_bucket_arn" {
  value = aws_s3_bucket.main.arn
}

# --- Database ---
output "db_endpoint" {
  value = aws_db_instance.main.endpoint
}

output "db_secret_arn" {
  value = aws_db_instance.main.master_user_secret[0].secret_arn
}

# --- ECR ---
output "ecr_repository_url" {
  value = aws_ecr_repository.app.repository_url
}

output "ecr_repository_name" {
  value = aws_ecr_repository.app.name
}

# --- Fargate ---
output "ecs_cluster_name" {
  value = aws_ecs_cluster.main.name
}

output "ecs_service_name" {
  value = aws_ecs_service.app.name
}

# --- Alb ---
output "alb_dns_name" {
  description = "Point your DNS (CNAME or Route 53 alias) at this"
  value       = aws_lb.main.dns_name
}

# EC2 disabled
# output "instance_id" {
#   value = aws_instance.app.id
# }
