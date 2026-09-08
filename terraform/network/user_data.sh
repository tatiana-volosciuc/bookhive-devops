#!/bin/bash
set -euxo pipefail

dnf update -y
dnf install -y docker git
systemctl enable docker
systemctl start docker
usermod -aG docker ec2-user
