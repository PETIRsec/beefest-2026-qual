from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
from secrets import token_bytes


BLOCK_SIZE = 16
KEY = token_bytes(BLOCK_SIZE)


with open("flag.txt", "rb") as f:
    FLAG = f.read().strip()


def encrypt(user_input):
    cipher = AES.new(KEY, AES.MODE_ECB)
    plaintext = user_input + FLAG
    return cipher.encrypt(pad(plaintext, BLOCK_SIZE)).hex()


def menu():
    print()
    print("=== Warung Cilok Oracle ===")
    print("1. Titip bumbu tambahan")
    print("2. Minta resep rahasia")
    print("3. Pulang")


while True:
    menu()
    try:
        choice = input("> ").strip()
    except EOFError:
        break

    if choice == "1":
        try:
            data = input("bumbu (hex): ").strip()
            user_input = bytes.fromhex(data)
        except ValueError:
            print("Bumbunya harus hex.")
            continue
        except EOFError:
            break

        print(f"cilok: {encrypt(user_input)}", flush=True)
    elif choice == "2":
        print("Resep rahasia cuma bisa dibuka kalau kamu ngerti pola bumbunya.")
    elif choice == "3":
        print("Makasih sudah mampir.")
        break
    else:
        print("Menu itu belum ada.")
