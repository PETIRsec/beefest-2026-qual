#!/bin/bash
set -euo pipefail

echo "Compiling Truman Show heap challenge..."

# Compile truman
gcc \
    -O2 \
    -fstack-protector-strong \
    -fPIE \
    -pie \
    -Wl,-z,relro \
    -Wl,-z,lazy \
    -o truman \
    truman.c

strip --strip-all truman

# Compile serve
gcc \
    -O2 \
    -o serve \
    serve.c

strip --strip-all serve

echo "Compiled successfully."
echo
echo "Binary info:"
file truman
file serve