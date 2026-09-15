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
