output "instance_id" {
  value = aws_instance.node.id
}

output "private_ip" {
  value = aws_instance.node.private_ip
}

output "target_group_arn" {
  value = aws_lb_target_group.k3s.arn
}
