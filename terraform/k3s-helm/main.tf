terraform {
  required_version = ">= 1.9"
  required_providers {
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.17"
    }
  }
}

provider "helm" {
  kubernetes {
    config_path = var.kubeconfig_path
  }
}

variable "kubeconfig_path" {
  description = "Path to a k3s kubeconfig with private network access to the API."
  type        = string
}

variable "php_image" {
  description = "PHP ECR repository URL, without a tag."
  type        = string
}

variable "image_tag" {
  description = "Tag of an existing PHP image built with the k3s-compatible entrypoint."
  type        = string
}

variable "db_host" {
  description = "RDS hostname, without the port."
  type        = string
}

variable "aws_region" {
  type = string
}

variable "s3_bucket" {
  type = string
}

resource "helm_release" "bookhive" {
  name      = "bookhive"
  namespace = "default"
  chart     = "${path.module}/../../symfony-chart"
  timeout   = 600
  wait      = true

  values = [yamlencode({
    php     = { image = var.php_image, tag = var.image_tag, appEnv = "prod", imagePullSecret = "ecr-pull" }
    mysql   = { enabled = false }
    db      = { host = var.db_host, existingSecret = "bookhive-runtime" }
    aws     = { region = var.aws_region, bucket = var.s3_bucket }
    ingress = { enabled = false }
    cleanup = { enabled = false }
  })]
}
