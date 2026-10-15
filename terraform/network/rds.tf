resource "aws_db_subnet_group" "db" {
  name = "${var.project}-db-subnet-group"
  # TEMP: superset while the instance is migrated off the app-tier subnets (see phase 2 in docs/failure-log.md)
  subnet_ids = [aws_subnet.private_a.id, aws_subnet.private_b.id, aws_subnet.db_a.id, aws_subnet.db_b.id]
  tags       = { Name = "${var.project}-db-subnet-group" }
}

data "aws_db_snapshot" "latest" {
  count                  = var.restore_from_latest_snapshot ? 1 : 0
  db_instance_identifier = "${var.project}-db"
  snapshot_type          = "manual" # ignore automated backups
  most_recent            = true
}

resource "aws_db_instance" "main" {
  identifier                  = "${var.project}-db"
  engine                      = "mysql"
  engine_version              = "8.0"
  instance_class              = var.db_instance_class
  allocated_storage           = 20
  db_name                     = "bookhive"
  username                    = "admin"
  manage_master_user_password = true
  snapshot_identifier         = var.restore_from_latest_snapshot ? data.aws_db_snapshot.latest[0].id : null

  db_subnet_group_name   = aws_db_subnet_group.db.name
  vpc_security_group_ids = [aws_security_group.db.id]
  publicly_accessible    = false

  storage_encrypted = true
  #   kms_key_id              = var.kms_key_arn

  skip_final_snapshot       = var.env == "prod" ? false : true
  final_snapshot_identifier = var.env == "prod" ? "${var.project}-db-final-${formatdate("YYYYMMDD-hhmm", timestamp())}" : null

  lifecycle {
    ignore_changes = [snapshot_identifier]
  }
  enabled_cloudwatch_logs_exports = local.rds_log_types
  depends_on = [
    aws_cloudwatch_log_group.rds,
    terraform_data.db_snapshot,
    terraform_data.db_snapshot_cleanup,
  ]
}

resource "terraform_data" "db_snapshot" {
  triggers_replace = timestamp()

  provisioner "local-exec" {
    interpreter = ["/bin/bash", "-c"]
    command     = <<-EOT
      set -euo pipefail
      DB="${var.project}-db"

      if [ "${var.create_snapshot}" != "true" ]; then
        echo "Snapshot skipped"
        exit 0
      fi

      COUNT=$(aws rds describe-db-snapshots \
        --db-instance-identifier "$DB" \
        --snapshot-type manual \
        --query 'length(DBSnapshots)' --output text)

      if [ "$COUNT" -gt 0 ]; then
        echo "Snapshot already exists, skipping"
        exit 0
      fi

      if OUT=$(aws rds describe-db-instances --db-instance-identifier "$DB" 2>&1); then
        SNAP="$DB-snapshot-$(date -u +%Y%m%d-%H%M%S)"
        aws rds create-db-snapshot \
          --db-instance-identifier "$DB" \
          --db-snapshot-identifier "$SNAP"
        aws rds wait db-snapshot-available --db-snapshot-identifier "$SNAP"
        echo "Created snapshot $SNAP"
      elif echo "$OUT" | grep -q DBInstanceNotFound; then
        echo "DB $DB does not exist yet, skipping snapshot"
      else
        echo "Snapshot check failed: $OUT" >&2
        exit 1
      fi
    EOT
  }
}

resource "terraform_data" "db_snapshot_post" {
  triggers_replace = aws_db_instance.main.resource_id

  provisioner "local-exec" {
    interpreter = ["/bin/bash", "-c"]
    command     = <<-EOT
      set -euo pipefail

      if [ "${var.create_snapshot}" != "true" ]; then
        echo "Snapshot skipped"
        exit 0
      fi

      DB="${aws_db_instance.main.identifier}"

      COUNT=$(aws rds describe-db-snapshots \
        --db-instance-identifier "$DB" \
        --snapshot-type manual \
        --query 'length(DBSnapshots)' --output text)

      if [ "$COUNT" -gt 0 ]; then
        echo "Snapshot already exists, skipping"
        exit 0
      fi

      SNAP="$DB-snapshot-$(date -u +%Y%m%d-%H%M%S)"
      aws rds create-db-snapshot \
        --db-instance-identifier "$DB" \
        --db-snapshot-identifier "$SNAP"
      aws rds wait db-snapshot-available --db-snapshot-identifier "$SNAP"
      echo "Created snapshot $SNAP"
    EOT
  }
}

resource "terraform_data" "db_snapshot_cleanup" {
  input = "${var.project}-db"

  provisioner "local-exec" {
    when        = destroy
    interpreter = ["/bin/bash", "-c"]
    command     = <<-EOT
      set -euo pipefail
      DB="${self.input}"

      aws rds describe-db-snapshots \
        --db-instance-identifier "$DB" \
        --snapshot-type manual \
        --query "DBSnapshots[?starts_with(DBSnapshotIdentifier, '$DB-snapshot-') || starts_with(DBSnapshotIdentifier, '$DB-pre-apply-')].DBSnapshotIdentifier" \
        --output text | tr '\t' '\n' | while read -r SNAP; do
          [ -n "$SNAP" ] || continue
          echo "Deleting snapshot $SNAP"
          aws rds delete-db-snapshot --db-snapshot-identifier "$SNAP" >/dev/null
        done
    EOT
  }
}
