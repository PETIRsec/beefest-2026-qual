MASK64 = 0xFFFFFFFFFFFFFFFF


leaked_bytes = [
    72, 10, 84, 92, 15, 83, 104, 104,
    76, 84, 98, 8, 98, 83, 11, 85,
    10, 73, 15, 9, 89, 103, 8, 3,
    3, 9, 61, 55
]



secret_data = []

for i, byte in enumerate(leaked_bytes):
    decrypted_byte = byte ^ (0x37 + ((i + 0xBE) % 7))


    if decrypted_byte == 0:
        break

    secret_data.append(decrypted_byte)

secret = bytes(secret_data)

print(f"Secret : {secret.decode()}")


# Hitung Flux Hash
hash_val = 5381

for i, byte in enumerate(secret):
    hash_val = (hash_val * 33 + byte) & MASK64
    hash_val ^= hash_val >> 13
    hash_val ^= (i * 0x9E3779B97F4A7C15) & MASK64
    hash_val &= MASK64

print(f"Hash   : {hash_val}")
