#!/bin/sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

docker build \
    --target artifact \
    --output "type=local,dest=$script_dir" \
    "$script_dir"
