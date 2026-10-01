variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "project" {
  type    = string
  default = "bookhive"
}

variable "vpc_cidr" {
  type    = string
  default = "10.0.0.0/16"
}

variable "public_subnet_cidr_a" {
  type    = string
  default = "10.0.1.0/24"
}

variable "public_subnet_cidr_b" {
  type    = string
  default = "10.0.3.0/24"
}

variable "private_subnet_cidr_a" {
  type    = string
  default = "10.0.2.0/24"
}

variable "private_subnet_cidr_b" {
  type    = string
  default = "10.0.4.0/24"
}

variable "instance_type" {
  type    = string
  default = "t3.micro"
}

variable "db_instance_class" {
  type    = string
  default = "db.t3.micro"
}

variable "kms_key_arn" {
  description = "ARN of the KMS key used to encrypt the RDS storage"
  type        = string
  default     = null
}

variable "env" {
  description = "Environment name (dev/staging/prod)"
  type        = string
  default     = "dev"
}

variable "image_tag" {
  description = "Image tag to deploy"
  type        = string
  default     = "bootstrap"
}

# For learning purposes as I don't have domain to get real arn certificate
# openssl req -x509 -newkey rsa:2048 -nodes -days 365 \
#    -keyout key.pem -out cert.pem \
#    -subj "/CN=bookhive.local" \
#    -addext "subjectAltName=DNS:bookhive.local"

# aws acm import-certificate --certificate fileb://cert.pem --private-key fileb://key.pem --region us-east-1

variable "certificate_arn" {
  description = "ACM certificate ARN (same region as the ALB) for the HTTPS listener"
  type        = string
}

variable "health_check_path" {
  description = "Path the ALB probes on each task"
  type        = string
  default     = "/"
}
