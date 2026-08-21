#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <sys/ptrace.h>
#include <unistd.h>
 

void check_anti_debug() {
    if (ptrace(PTRACE_TRACEME, 0, 1, 0) < 0) {
        exit(1);
    }
}

void xor_secret(const char *memory_buffer, size_t len){
    for(int i=0; i<len; i++){
        uint64_t xor = memory_buffer[i];
        uint64_t obfuscated;
        if(i >= 0xBE && i<=0xD9){
            obfuscated = xor ^ (0x37 + (i%7));
        }
        else{
            obfuscated = xor;
        }
        printf("%llu, ",obfuscated);
    }
    printf("\n");
}


// Obfuscated secret string: "p3ng3n_PunY4_d3l0r34n_1985" (di-XOR 0x6A)
const uint8_t secret_key[] = {
    0x1A, 0x59, 0x04, 0x0D, 0x59, 0x04, 0x35, 0x3A,
    0x1F, 0x04, 0x33, 0x5E, 0x35, 0x0E, 0x59, 0x06,
    0x5A, 0x18, 0x59, 0x5E, 0x04, 0x35, 0x5B, 0x53,
    0x52, 0x5F, 0x00,
};


const uint8_t flag[] = {
    0xB3, 0xA0, 0x6F, 0x67, 0x40, 0x25, 0x41, 0x05,
    0x9D, 0x90, 0x72, 0x7B, 0x77, 0x22, 0x4A, 0x22,
    0x92, 0xF4, 0x7E, 0x14, 0x66, 0x3C, 0x5E, 0x50,
    0x92, 0xB7, 0x53, 0x54, 0x60, 0x50, 0x5E, 0x3C,
    0x82, 0x90, 0x69, 0x47, 0x27, 0x10, 0x49, 0x3C,
    0x95, 0xAF, 0x68, 0x16, 0x25, 0x10, 0x09, 0x57,
    0x82, 0xBC, 0x77, 0x00,
};

// Fungsi Hash djb2 (64-bit)
__attribute__((noinline))
uint64_t calculate_flux_hash(const uint8_t *data) {
    uint64_t hash = 5381; // 0x1505 Initial Seed
    int c;
    size_t i = 0;
    while ((c = *data++)) {
        hash = ((hash << 5) + hash) + c;
        hash ^= (hash >> 13);
        hash ^= ((uint64_t)i * (uint64_t)0x9E3779B97F4A7C15);
        i++;
    }
    return hash;
}

int main() {
    check_anti_debug();
    
    setvbuf(stdout, NULL, _IONBF, 0);

    // Alokasi memori buffer
    char *memory_buffer = (char *)calloc(0xFA, 1);
    if (!memory_buffer) return 1;

    // Decrypt secret string ke offset memori 0xBE (190 desimal)
    for (size_t i = 0; secret_key[i] != 0; i++) {
        memory_buffer[0xBE + i] = secret_key[i] ^ 0x6A;
    }

    puts("[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]");
    puts("[][]                                                    [][]");
    puts("[][]  FLUX CAPACITOR TEMPORAL CALIBRATION SYSTEM v1.21  [][]");
    puts("[][]                                                    [][]");
    puts("[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]");
    puts("");

    puts("Enter your calibration note:");
    char input_note[64];
    if (fgets(input_note, sizeof(input_note), stdin) == NULL) return 1;

    if (input_note[0] == '\n'){
        puts("Critical Error");
        return 0;
    }

    strncpy(memory_buffer, input_note, 0x30);

    puts("How many bytes of memory state do you want to inspect?");
    char len_buf[16];
    if (fgets(len_buf, sizeof(len_buf), stdin) == NULL) return 1;

    if (len_buf[0] == '\n'){
        puts("Critical Error");
        return 0;
    }

    int inspect_len = atoi(len_buf);
    printf("Inspecting %d bytes from Flux Memory:\n", inspect_len);


    xor_secret(memory_buffer, inspect_len);

    puts("\nEnter the 64-bit Flux Hash to initiate Time Travel:");
    char hash_input[32];
    if (fgets(hash_input, sizeof(hash_input), stdin) == NULL) return 1;

    if (hash_input[0] == '\n'){
        puts("Critical Error");
        return 0;
    }

    uint64_t user_hash = strtoull(hash_input, NULL, 10);

    uint64_t target_hash = calculate_flux_hash((uint8_t *)(memory_buffer + 0xBE));

    
    if (user_hash == target_hash && user_hash != 0) {
        puts("\n1.21 GIGAWATTS ACHIEVED!");
        puts("Temporal Displacement Initialized! Great Scott!");

        // DECRYPT FLAG MENGGUNAKAN USER_HASH SEBAGAI KUNCI
        for (size_t i = 0; flag[i] != 0; i++) {
            // Ambil byte kunci dari user_hash (berputar per 8-byte)
            uint8_t key_byte = (user_hash >> ((i % 8) * 8)) & 0xFF;
            putchar(flag[i] ^ key_byte);
        }
        putchar('\n');
    } else {
        puts("\nTemporal Hash Mismatch! You are stuck in 1955!");
    }

    free(memory_buffer);
    return 0;
}