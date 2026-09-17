data "aws_ami" "al2023" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    # "al2023-ami-*" also matches the minimal variant (no SSM Agent); pin to the standard image
    name   = "name"
    values = ["al2023-ami-2023.*-x86_64"]
  }
  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

resource "aws_instance" "app" {
  ami                         = data.aws_ami.al2023.id
  instance_type               = var.instance_type
  subnet_id                   = aws_subnet.private_a.id
  vpc_security_group_ids      = [aws_security_group.app.id]
  iam_instance_profile        = aws_iam_instance_profile.app_instance.name
  associate_public_ip_address = false
  user_data                   = file("${path.module}/user_data.sh")

  tags = { Name = "${var.project}-app" }
}
