# Truman Show - Heap UAF Challenge

## Overview

A medium-difficulty 64-bit heap exploitation challenge themed around *The Truman Show*.

The challenge revolves around a use-after-free in the `Segment` management logic combined with glibc 2.31 allocator behavior.

### Hardening

- PIE enabled
- ASLR enabled in Docker
- Partial RELRO
- Stack canary enabled
- NX enabled
- Symbols stripped
- Runtime target: Ubuntu 20.04 / glibc 2.31

### Intended Solve Path

The intended exploitation chain is:

```text
Use-after-free
    ↓
Heap leak from freed Segment metadata
    ↓
0x40 tcache / fastbin heap grooming
    ↓
Exploit calloc() allocation ordering
    ↓
Segment / script chunk overlap
    ↓
Arbitrary read primitive
    ↓
PIE leak
    ↓
GOT leak
    ↓
Libc base calculation
    ↓
Crafted Segment
    ↓
system("/bin/sh")

