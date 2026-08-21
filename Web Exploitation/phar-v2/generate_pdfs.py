#!/usr/bin/env python3
"""
Generator PDF PHAR CORP.
Edit teks di GUIDE_LINES / MANUAL_LINES, lalu: python3 generate_pdfs.py
"""

import os

def escape(s: str) -> str:
    return s.replace('\\', '\\\\').replace('(', '\\(').replace(')', '\\)')

def make_pdf(path: str, lines):
    content = "BT\n/F1 12 Tf\n72 740 Td\n16 TL\n"
    for line in lines:
        content += f"({escape(line)}) Tj\nT*\n"
    content += "ET\n"

    objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        "/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
        f"<< /Length {len(content.encode('latin-1'))} >>\nstream\n{content}endstream",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ]

    out = bytearray(b"%PDF-1.4\n")
    offsets = []
    for i, obj in enumerate(objects, start=1):
        offsets.append(len(out))
        out += f"{i} 0 obj\n".encode('latin-1')
        out += obj.encode('latin-1')
        out += b"\nendobj\n"

    xref_pos = len(out)
    n = len(objects) + 1
    out += f"xref\n0 {n}\n".encode('latin-1')
    out += b"0000000000 65535 f \n"
    for off in offsets:
        out += f"{off:010d} 00000 n \n".encode('latin-1')
    out += f"trailer\n<< /Size {n} /Root 1 0 R >>\nstartxref\n{xref_pos}\n%%EOF".encode('latin-1')

    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'wb') as f:
        f.write(out)
    print(f"[+] {path} ({len(out)} bytes)")


GUIDE_LINES = [
    "PHAR CORP - PANDUAN ONBOARDING SELLER",
    "======================================",
    "",
    "Selamat datang di PHAR CORP Seller Hub!",
    "",
    "Berikut langkah pertama untuk mengaktifkan akun toko Anda:",
    "",
    "1. Buka halaman login di /login.php",
    "2. Masukkan email seller Anda (contoh: nama@phar.com)",
    "3. Gunakan DEFAULT PASSWORD berikut untuk login pertama kali:",
    "",
    "        default password : PHAR190206",
    "",
    "4. WAJIB segera ganti password setelah login pertama demi keamanan.",
    "",
    "Catatan Keamanan:",
    "- Jangan bagikan password Anda kepada siapa pun.",
    "- Seller yang belum mengganti default password berisiko diretas.",
    "- Tim PHAR CORP tidak pernah meminta password via telepon/email.",
    "",
    "Butuh bantuan? Hubungi support@phar.com",
    "(c) 2026 PHAR CORP - Internal Onboarding Document",
]

MANUAL_LINES = [
    "PHAR CORP - PANDUAN RESET PASSWORD MANUAL",
    "=========================================",
    "",
    "Jika layanan email reset otomatis sedang bermasalah,",
    "silakan ikuti prosedur reset manual berikut:",
    "",
    "1. Siapkan data akun: email terdaftar dan ID toko.",
    "2. Kirim permintaan reset ke tim support internal.",
    "3. Tunggu verifikasi identitas 1x24 jam.",
    "4. Password sementara akan diberikan oleh admin.",
    "",
    "-- Halaman ini sengaja dibiarkan kosong --",
    "-- (silakan diedit oleh author challenge) --",
    "",
    "(c) 2026 PHAR CORP - Support Document",
]

if __name__ == "__main__":
    base = os.path.dirname(os.path.abspath(__file__))
    make_pdf(os.path.join(base, "src", "files", "guide.pdf"), GUIDE_LINES)
    make_pdf(os.path.join(base, "src", "files", "manual.pdf"), MANUAL_LINES)
    print("[*] Selesai. PDF ada di src/files/")
