output "instance_id" {
  value = aws_instance.app.id
}

output "private_subnet_a_id" {
  value = aws_subnet.private_a.id
}

output "vpc_id" {
  value = aws_vpc.main.id
}

output "s3_bucket_name" {
  value = aws_s3_bucket.main.bucket
}

output "s3_bucket_arn" {
  value = aws_s3_bucket.main.arn
}

output "db_endpoint" {
  value = aws_db_instance.main.endpoint
}

output "db_secret_arn" {
  value = aws_db_instance.main.master_user_secret[0].secret_arn
}

output "ecr_repository_url" {
  value = aws_ecr_repository.app.repository_url
}

output "ecr_repository_name" {
  value = aws_ecr_repository.app.name
}
