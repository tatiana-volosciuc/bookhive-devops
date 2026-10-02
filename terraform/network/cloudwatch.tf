locals {
  rds_log_types = ["error", "slowquery"]
}

resource "aws_cloudwatch_log_group" "rds" {
  for_each          = toset(local.rds_log_types)
  name              = "/aws/rds/instance/${var.project}-db/${each.key}"
  retention_in_days = 7
}

resource "aws_cloudwatch_log_metric_filter" "app_errors" {
  name           = "${var.project}-app-errors"
  log_group_name = aws_cloudwatch_log_group.app.name
  pattern        = "?ERROR ?CRITICAL ?\"Fatal error\" ?\"Uncaught\""

  metric_transformation {
    name          = "AppErrorCount"
    namespace     = "${var.project}/App"
    value         = "1"
    default_value = "0"
  }
}

resource "aws_cloudwatch_metric_alarm" "ecs_memory" {
  alarm_name  = "${var.project}-ecs-memory-high"
  namespace   = "AWS/ECS"
  metric_name = "MemoryUtilization"
  dimensions = {
    ClusterName = aws_ecs_cluster.main.name
    ServiceName = aws_ecs_service.app.name
  }
  statistic           = "Average"
  period              = 60
  evaluation_periods  = 3
  threshold           = 80
  comparison_operator = "GreaterThanThreshold"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

resource "aws_sns_topic" "alerts" {
  name = "${var.project}-alerts"
}

resource "aws_sns_topic_subscription" "email" {
  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email # you must click the confirmation email
}

locals {
  alb_dims = {
    LoadBalancer = aws_lb.main.arn_suffix
    TargetGroup  = aws_lb_target_group.app.arn_suffix
  }
}

# 1. App returning 5xx
resource "aws_cloudwatch_metric_alarm" "target_5xx" {
  alarm_name          = "${var.project}-target-5xx"
  namespace           = "AWS/ApplicationELB"
  metric_name         = "HTTPCode_Target_5XX_Count"
  dimensions          = { LoadBalancer = aws_lb.main.arn_suffix }
  statistic           = "Sum"
  period              = 60
  evaluation_periods  = 2
  threshold           = 5
  comparison_operator = "GreaterThanOrEqualToThreshold"
  treat_missing_data  = "notBreaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
  ok_actions          = [aws_sns_topic.alerts.arn]
}

# 2. ALB itself failing (e.g. no healthy targets -> 503)
resource "aws_cloudwatch_metric_alarm" "elb_5xx" {
  alarm_name          = "${var.project}-elb-5xx"
  namespace           = "AWS/ApplicationELB"
  metric_name         = "HTTPCode_ELB_5XX_Count"
  dimensions          = { LoadBalancer = aws_lb.main.arn_suffix }
  statistic           = "Sum"
  period              = 60
  evaluation_periods  = 1
  threshold           = 1
  comparison_operator = "GreaterThanOrEqualToThreshold"
  treat_missing_data  = "notBreaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

# 3. Unhealthy targets
resource "aws_cloudwatch_metric_alarm" "unhealthy_hosts" {
  alarm_name          = "${var.project}-unhealthy-hosts"
  namespace           = "AWS/ApplicationELB"
  metric_name         = "UnHealthyHostCount"
  dimensions          = local.alb_dims
  statistic           = "Maximum"
  period              = 60
  evaluation_periods  = 2
  threshold           = 1
  comparison_operator = "GreaterThanOrEqualToThreshold"
  treat_missing_data  = "notBreaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

# 4. Zero healthy targets (missing data = breaching on purpose)
resource "aws_cloudwatch_metric_alarm" "no_healthy_hosts" {
  alarm_name          = "${var.project}-no-healthy-hosts"
  namespace           = "AWS/ApplicationELB"
  metric_name         = "HealthyHostCount"
  dimensions          = local.alb_dims
  statistic           = "Minimum"
  period              = 60
  evaluation_periods  = 1
  threshold           = 1
  comparison_operator = "LessThanThreshold"
  treat_missing_data  = "breaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

# 5. Latency
resource "aws_cloudwatch_metric_alarm" "latency_p95" {
  alarm_name          = "${var.project}-latency-p95"
  namespace           = "AWS/ApplicationELB"
  metric_name         = "TargetResponseTime"
  dimensions          = { LoadBalancer = aws_lb.main.arn_suffix }
  extended_statistic  = "p95"
  period              = 60
  evaluation_periods  = 3
  threshold           = 1.5 # seconds
  comparison_operator = "GreaterThanThreshold"
  treat_missing_data  = "notBreaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

# 6. ECS CPU
resource "aws_cloudwatch_metric_alarm" "ecs_cpu" {
  alarm_name  = "${var.project}-ecs-cpu-high"
  namespace   = "AWS/ECS"
  metric_name = "CPUUtilization"
  dimensions = {
    ClusterName = aws_ecs_cluster.main.name
    ServiceName = aws_ecs_service.app.name
  }
  statistic           = "Average"
  period              = 60
  evaluation_periods  = 3
  threshold           = 80
  comparison_operator = "GreaterThanThreshold"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

resource "aws_cloudwatch_metric_alarm" "app_errors" {
  alarm_name          = "${var.project}-app-errors"
  namespace           = "${var.project}/App"
  metric_name         = "AppErrorCount"
  statistic           = "Sum"
  period              = 60
  evaluation_periods  = 1
  threshold           = 3
  comparison_operator = "GreaterThanOrEqualToThreshold"
  treat_missing_data  = "notBreaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}
