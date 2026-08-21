#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"
docker compose up --build --force-recreate -d
