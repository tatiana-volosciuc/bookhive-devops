output "instance_id" {
  value = aws_instance.app.id
}

output "private_subnet_a_id" {
  value = aws_subnet.private_a.id
}

output "vpc_id" {
  value = aws_vpc.main.id
}
