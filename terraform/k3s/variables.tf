variable "aws_region" {
  type = string

  validation {
    condition     = can(regex("^[a-z0-9-]+$", var.aws_region))
    error_message = "Use a valid AWS region identifier."
  }
}

variable "project" {
  type    = string
  default = "bookhive"
}

variable "vpc_id" {
  type = string
}

variable "private_subnet_id" {
  type = string
}

variable "alb_security_group_id" {
  type = string
}

variable "database_security_group_id" {
  type = string
}

variable "api_client_cidr" {
  description = "Private CIDR of the machine running Helm Terraform, permitted to reach the k3s API."
  type        = string

  validation {
    condition     = can(cidrhost(var.api_client_cidr, 0)) && can(regex("^[0-9.]+/[0-9]+$", var.api_client_cidr)) && var.api_client_cidr != "0.0.0.0/0"
    error_message = "Use a specific IPv4 CIDR, not 0.0.0.0/0."
  }
}

variable "ecr_repository_url" {
  description = "Existing PHP ECR repository URL, without a tag."
  type        = string

  validation {
    condition     = can(regex("^[0-9]{12}\\.dkr\\.ecr\\.[a-z0-9-]+\\.amazonaws\\.com/[a-z0-9._/-]+$", var.ecr_repository_url))
    error_message = "Use a standard private ECR repository URL without a tag."
  }
}

variable "bucket_arn" {
  description = "Existing application S3 bucket ARN."
  type        = string
}

variable "instance_type" {
  type    = string
  default = "t3.medium"
}

variable "k3s_version" {
  description = "Pinned k3s release accepted by the official installer."
  type        = string

  validation {
    condition     = can(regex("^v[0-9]+\\.[0-9]+\\.[0-9]+\\+k3s[0-9]+$", var.k3s_version))
    error_message = "Use a k3s release such as v1.30.14+k3s1."
  }
}
