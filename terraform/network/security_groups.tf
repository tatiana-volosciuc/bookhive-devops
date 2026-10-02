resource "aws_security_group" "db" {
  name   = "${var.project}-db-sg"
  vpc_id = aws_vpc.main.id
  tags   = { Name = "${var.project}-db-sg" }
}

resource "aws_vpc_security_group_egress_rule" "db_egress" {
  security_group_id = aws_security_group.db.id
  ip_protocol       = "-1"
  cidr_ipv4         = "0.0.0.0/0"
}

# ALB security groups
resource "aws_vpc_security_group_ingress_rule" "ecs_to_db" {
  security_group_id            = aws_security_group.db.id
  referenced_security_group_id = aws_security_group.ecs.id
  from_port                    = 3306
  to_port                      = 3306
  ip_protocol                  = "tcp"
}


resource "aws_security_group" "alb" {
  name        = "${var.project}-alb"
  description = "Public ALB"
  vpc_id      = aws_vpc.main.id
  tags        = { Name = "${var.project}-alb-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "alb_https" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  from_port         = 443
  to_port           = 443
  ip_protocol       = "tcp"
}

# Only used to redirect to HTTPS
resource "aws_vpc_security_group_ingress_rule" "alb_http" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  from_port         = 80
  to_port           = 80
  ip_protocol       = "tcp"
}

# ALB may only talk to the ECS tasks, on the container port
resource "aws_vpc_security_group_egress_rule" "alb_to_ecs" {
  security_group_id            = aws_security_group.alb.id
  referenced_security_group_id = aws_security_group.ecs.id
  from_port                    = local.container_port
  to_port                      = local.container_port
  ip_protocol                  = "tcp"
}

# ECS tasks: ingress ONLY from the ALB security group (no more 0.0.0.0/0)
resource "aws_vpc_security_group_ingress_rule" "ecs_from_alb" {
  security_group_id            = aws_security_group.ecs.id
  referenced_security_group_id = aws_security_group.alb.id
  from_port                    = local.container_port
  to_port                      = local.container_port
  ip_protocol                  = "tcp"
}

# Tasks need outbound for ECR/Secrets/S3 via the NAT gateway
resource "aws_vpc_security_group_egress_rule" "ecs_all" {
  security_group_id = aws_security_group.ecs.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
}

# EC2 is disabled at this moment. Commented EC2 implementation for the example.

# resource "aws_security_group" "app" {
#   name        = "${var.project}-app-sg"
#   description = "No inbound. Access is via SSM Session Manager only."
#   vpc_id      = aws_vpc.main.id
#
#   egress {
#     description = "Allow all outbound (Docker Hub, GitHub, SSM, apt/yum)"
#     from_port   = 0
#     to_port     = 0
#     protocol    = "-1"
#     cidr_blocks = ["0.0.0.0/0"]
#   }
#
#   tags = { Name = "${var.project}-app-sg" }
# }

# resource "aws_vpc_security_group_ingress_rule" "app_to_db" {
#   security_group_id            = aws_security_group.db.id
#   referenced_security_group_id = aws_security_group.app.id
#   from_port                    = 3306
#   to_port                      = 3306
#   ip_protocol                  = "tcp"
# }
