resource "aws_ecs_cluster" "main" {
  name = "${var.project}-cluster"
}

# Rules live in security_groups.tf / alb.tf as standalone resources.
resource "aws_security_group" "ecs" {
  name        = "${var.project}-ecs"
  description = "ECS service"
  vpc_id      = aws_vpc.main.id
  tags        = { Name = "${var.project}-ecs-sg" }
}

resource "aws_cloudwatch_log_group" "app" {
  name              = "/ecs/${var.project}"
  retention_in_days = 7
}

# ---------------------------------------------------------------------------
# Task: php-fpm container + nginx sidecar sharing one network namespace
# ---------------------------------------------------------------------------
resource "aws_ecs_task_definition" "app" {
  family = "${var.project}-app"

  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"

  cpu    = 512
  memory = 1024

  execution_role_arn = aws_iam_role.ecs_execution_role.arn
  task_role_arn      = aws_iam_role.ecs_task_role.arn

  container_definitions = jsonencode([
    {
      # PHP app. No port exposed to the ALB: nginx reaches it on 127.0.0.1:9000.
      # CD replaces this image; the name must match container-name in cd.yml.
      name      = var.project
      image     = "${aws_ecr_repository.app.repository_url}:${var.image_tag}"
      essential = true

      # Time between stop signal and SIGKILL. Must be >= deregistration_delay.
      stopTimeout = 60

      environment = [
        { name = "APP_ENV", value = "prod" },
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_NAME", value = "bookhive" },
        { name = "DB_USER", value = "admin" },
        { name = "AWS_REGION", value = var.aws_region },
        { name = "AWS_S3_BUCKET", value = aws_s3_bucket.main.bucket }
      ]

      secrets = [
        {
          name      = "DB_PASSWORD"
          valueFrom = "${aws_db_instance.main.master_user_secret[0].secret_arn}:password::"
        },
        {
          name      = "APP_SECRET"
          valueFrom = aws_secretsmanager_secret.app_secret.arn
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = aws_cloudwatch_log_group.app.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "php"
        }
      }
    },
    {
      # Receives traffic from the ALB on port 80 and forwards it to php-fpm.
      # CD replaces this image too; the name must match container_name in the service.
      name      = "nginx"
      image     = "${aws_ecr_repository.nginx.repository_url}:${var.image_tag}"
      essential = true

      stopTimeout = 60

      portMappings = [
        {
          containerPort = local.container_port
          hostPort      = local.container_port
          protocol      = "tcp"
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = aws_cloudwatch_log_group.app.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "nginx"
        }
      }
    }
  ])
}

resource "aws_ecs_service" "app" {
  name                   = "${var.project}-service"
  cluster                = aws_ecs_cluster.main.id
  task_definition        = aws_ecs_task_definition.app.arn
  enable_execute_command = true

  # Learning environment: start at 0, CD pushes the image, then raise it
  # with: aws ecs update-service --desired-count 1
  desired_count = 0

  launch_type = "FARGATE"

  network_configuration {
    subnets = [
      aws_subnet.private_a.id,
      aws_subnet.private_b.id
    ]
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "nginx" # the ALB talks to nginx, not to php-fpm
    container_port   = local.container_port
  }

  # Migrations run in the php entrypoint before php-fpm starts,
  # so give the first health checks time.
  health_check_grace_period_seconds = 120

  # Zero-downtime rolling deploy: new tasks first, old ones drain afterwards.
  deployment_minimum_healthy_percent = 100
  deployment_maximum_percent         = 200

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  # CD owns the task definition revision and you own the task count
  lifecycle {
    ignore_changes = [task_definition, desired_count]
  }

  depends_on = [aws_lb_listener.https]
}
