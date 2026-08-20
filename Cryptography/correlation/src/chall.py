import hashlib
import struct
from secrets import SystemRandom, token_bytes

import numpy as np
from Crypto.Cipher import AES

N_VARS = 20
C = 5
T = 14
SIZE = 1 << N_VARS
INV = pow(pow(2, N_VARS, 3), -1, 3)


def wht3(v):
    v = v.astype(np.int8).copy()
    for k in range(N_VARS):
        v = v.reshape(-1, 2, 1 << k)
        u, w = v[:, 0].copy(), v[:, 1].copy()
        v[:, 0] = (u + w) % 3
        v[:, 1] = (u - w) % 3
    return v.reshape(-1)


def pack(coeffs):
    v = np.concatenate([coeffs, np.zeros(-coeffs.size % 5, np.int8)])
    return (v.reshape(-1, 5).astype(np.int64) * [1, 3, 9, 27, 81]).sum(1).astype(np.uint8).tobytes()


def public_polys(seed):
    raw = np.frombuffer(hashlib.shake_256(seed).digest(C * SIZE // 4), np.uint8)
    raw = raw[raw < 243]
    assert raw.size * 5 >= C * SIZE
    trits = np.stack([raw // 3**k % 3 for k in range(5)], 1).reshape(-1)[: C * SIZE]
    return trits.astype(np.int8).reshape(C, SIZE)


def independent(exps):
    basis = {}
    for v in exps:
        while v:
            h = v.bit_length() - 1
            if h not in basis:
                basis[h] = v
                break
            v ^= basis[h]
        if not v:
            return False
    return True


def sparse_secret(rng):
    while True:
        exps = rng.sample(range(1, SIZE), T)
        if independent(exps):
            return rng.randrange(3), sorted((e, rng.choice((1, 2))) for e in exps)


def to_ring(m, terms):
    v = np.zeros(SIZE, np.int8)
    v[0] = m
    for e, c in terms:
        v[e] = c
    return v


with open("flag.txt", "rb") as f:
    flag = f.read().strip()

rng = SystemRandom()
seed = token_bytes(16)
a = public_polys(seed)
e = [sparse_secret(rng) for _ in range(C)]

vals = np.zeros(SIZE, np.int8)
for ai, (m, terms) in zip(a, e):
    vals = (vals + wht3(ai) * wht3(to_ring(m, terms))) % 3
b = (wht3(vals) * INV % 3).astype(np.int8)

m0, terms0 = e[0]
blob = bytes([m0]) + b"".join(struct.pack("<IB", i, c) for i, c in terms0)
key = hashlib.sha256(blob).digest()
nonce = token_bytes(12)
ct, tag = AES.new(key, AES.MODE_GCM, nonce=nonce).encrypt_and_digest(flag)

print(f"seed = {seed.hex()}")
print(f"b = {pack(b).hex()}")
print(f"nonce = {nonce.hex()}")
print(f"ct = {(ct + tag).hex()}")
