data "aws_iam_policy_document" "ec2_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

data "aws_iam_policy_document" "app_s3" {
  statement {
    actions   = ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.main.arn}/authors/*"]
  }
  statement {
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.main.arn]
  }
}

data "aws_iam_policy_document" "app_ecr" {
  statement {
    effect = "Allow"
    actions = [
      "ecr:GetAuthorizationToken",
      "ecr:BatchCheckLayerAvailability",
      "ecr:BatchGetImage",
      "ecr:GetDownloadUrlForLayer"
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role" "app_instance" {
  name               = "${var.project}-app-instance-role"
  assume_role_policy = data.aws_iam_policy_document.ec2_assume.json
}

resource "aws_iam_role_policy_attachment" "ssm_core" {
  role       = aws_iam_role.app_instance.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy" "app_secrets" {
  name = "${var.project}-app-secrets-read"
  role = aws_iam_role.app_instance.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = "secretsmanager:GetSecretValue"
      Resource = aws_db_instance.main.master_user_secret[0].secret_arn
    }]
  })
}

resource "aws_iam_instance_profile" "app_instance" {
  name = "${var.project}-app-instance-profile"
  role = aws_iam_role.app_instance.name
}

resource "aws_iam_policy" "app_s3" {
  name   = "${var.project}-app-s3"
  policy = data.aws_iam_policy_document.app_s3.json
}

resource "aws_iam_role_policy_attachment" "app_s3" {
  role       = aws_iam_role.app_instance.name
  policy_arn = aws_iam_policy.app_s3.arn
}

resource "aws_iam_policy" "app_ecr" {
  name   = "${var.project}-app-ecr"
  policy = data.aws_iam_policy_document.app_ecr.json
}

resource "aws_iam_role_policy_attachment" "app_ecr" {
  role       = aws_iam_role.app_instance.name
  policy_arn = aws_iam_policy.app_ecr.arn
}
