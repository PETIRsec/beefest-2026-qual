#include <errno.h>
#include <stddef.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/mman.h>
#include <time.h>
#include <unistd.h>
#include <linux/audit.h>
#include <linux/filter.h>
#include <linux/seccomp.h>
#include <linux/unistd.h>
#include <sys/prctl.h>

#include "setup.h"

#define STATE_SIZE 0x1000

struct State *saya_akan_lawan;

static void sandbox(void)
{
    struct sock_filter filter[] = {
        BPF_STMT(BPF_LD | BPF_W | BPF_ABS, offsetof(struct seccomp_data, arch)),
        BPF_JUMP(BPF_JMP | BPF_JEQ | BPF_K, AUDIT_ARCH_X86_64, 1, 0),
        BPF_STMT(BPF_RET | BPF_K, SECCOMP_RET_KILL_PROCESS),

        BPF_STMT(BPF_LD | BPF_W | BPF_ABS, offsetof(struct seccomp_data, nr)),
        BPF_JUMP(BPF_JMP | BPF_JGE | BPF_K, 0x40000000, 0, 1),
        BPF_STMT(BPF_RET | BPF_K, SECCOMP_RET_KILL_PROCESS),

        BPF_JUMP(BPF_JMP | BPF_JEQ | BPF_K, __NR_execve, 0, 1),
        BPF_STMT(BPF_RET | BPF_K, SECCOMP_RET_ERRNO | EPERM),
        BPF_JUMP(BPF_JMP | BPF_JEQ | BPF_K, __NR_execveat, 0, 1),
        BPF_STMT(BPF_RET | BPF_K, SECCOMP_RET_ERRNO | EPERM),

        BPF_STMT(BPF_RET | BPF_K, SECCOMP_RET_ALLOW),
    };
    struct sock_fprog program = {
        .len = sizeof(filter) / sizeof(filter[0]),
        .filter = filter,
    };

    if (prctl(PR_SET_NO_NEW_PRIVS, 1, 0, 0, 0) == -1) {
        exit(1);
    }

    if (prctl(PR_SET_SECCOMP, SECCOMP_MODE_FILTER, &program) == -1) {
        exit(1);
    }
}

void update_state(int *value, int amount)
{
    if (mprotect(saya_akan_lawan, STATE_SIZE,
                 PROT_READ | PROT_WRITE) == -1) {
        exit(1);
    }

    *value += amount;

    if (mprotect(saya_akan_lawan, STATE_SIZE, PROT_READ) == -1) {
        exit(1);
    }
}

int *setup(void)
{
    int *hp_buzzer;

    setvbuf(stdin, NULL, _IONBF, 0);
    setvbuf(stdout, NULL, _IONBF, 0);
    srand(time(NULL) ^ getpid());

    saya_akan_lawan = mmap(
        NULL,
        STATE_SIZE,
        PROT_READ | PROT_WRITE,
        MAP_PRIVATE | MAP_ANONYMOUS,
        -1,
        0
    );
    if (saya_akan_lawan == MAP_FAILED) {
        exit(1);
    }

    saya_akan_lawan->patience = 100;
    if (mprotect(saya_akan_lawan, STATE_SIZE, PROT_READ) == -1) {
        exit(1);
    }

    hp_buzzer = calloc(1, sizeof(*hp_buzzer));
    if (hp_buzzer == NULL) {
        exit(1);
    }

    sandbox();
    return hp_buzzer;
}
