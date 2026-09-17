resource "aws_security_group" "app" {
  name        = "${var.project}-app-sg"
  description = "No inbound. Access is via SSM Session Manager only."
  vpc_id      = aws_vpc.main.id

  egress {
    description = "Allow all outbound (Docker Hub, GitHub, SSM, apt/yum)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project}-app-sg" }
}

resource "aws_security_group" "db" {
  name   = "${var.project}-db-sg"
  vpc_id = aws_vpc.main.id
  tags   = { Name = "${var.project}-db-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "app_to_db" {
  security_group_id            = aws_security_group.db.id
  referenced_security_group_id = aws_security_group.app.id
  from_port                    = 3306
  to_port                      = 3306
  ip_protocol                  = "tcp"
}


resource "aws_vpc_security_group_egress_rule" "db_egress" {
  security_group_id = aws_security_group.db.id
  ip_protocol        = "-1"
  cidr_ipv4          = "0.0.0.0/0"
}
