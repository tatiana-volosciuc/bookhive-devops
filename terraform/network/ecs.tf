resource "aws_ecs_cluster" "main" {
  name = "${var.project}-cluster"
}

# Rules live in alb.tf as standalone resources (ingress from ALB SG only).
resource "aws_security_group" "ecs" {
  name        = "${var.project}-ecs"
  description = "ECS service"
  vpc_id      = aws_vpc.main.id
  tags        = { Name = "${var.project}-ecs-sg" }
}

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
      name      = var.project
      image     = "${aws_ecr_repository.app.repository_url}:${var.image_tag}"
      essential = true

      # Time between SIGTERM and SIGKILL. Must be >= deregistration_delay,
      # and your app must finish in-flight requests on SIGTERM.
      stopTimeout = 60

      portMappings = [
        {
          containerPort = local.container_port
          hostPort      = local.container_port
          protocol      = "tcp"
        }
      ]
    }
  ])
}

resource "aws_ecs_service" "app" {
  name            = "${var.project}-service"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.app.arn

  # Learning environment only: 0 means the ALB has no targets and returns 503.
  # Set to 1+ (or set it from CD) to actually serve traffic.
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
    container_name   = var.project
    container_port   = local.container_port
  }

  # Don't count slow-starting containers as unhealthy
  health_check_grace_period_seconds = 60

  # Zero-downtime rolling deploy: start new tasks first, keep all old ones
  # serving until the new ones are healthy, then drain the old ones.
  deployment_minimum_healthy_percent = 100
  deployment_maximum_percent         = 200

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  # Listener must exist before the service can register targets
  depends_on = [aws_lb_listener.https]
}
