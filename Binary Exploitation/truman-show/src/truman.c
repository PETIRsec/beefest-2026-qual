#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#define MAX_SEGMENTS 12
#define TITLE_SIZE 0x18
#define MAX_SCRIPT_SIZE 0x70

typedef struct Segment Segment;

struct Segment {
    char title[TITLE_SIZE];
    size_t script_len;
    char *script;
    void (*cue)(char *script);
    unsigned long episode;
};

static Segment *g_segments[MAX_SEGMENTS];
static unsigned char g_live[MAX_SEGMENTS];
static unsigned long g_next_episode = 1;

static void init_io(void) {
    setvbuf(stdin, NULL, _IONBF, 0);
    setvbuf(stdout, NULL, _IONBF, 0);
    setvbuf(stderr, NULL, _IONBF, 0);
}

static void show_intro(void) {
    puts("             THE TRUMAN SHOW                   ");
    puts("-------------------------------------------------");
    puts("Good morning! And in case I don't see ya:");
    puts("Good afternoon, good evening, and good night!\n");
}

static void die(const char *msg) {
    puts(msg);
    exit(0);
}

static void read_line(char *buf, size_t size) {
    if (!fgets(buf, (int)size, stdin))
        exit(0);

    buf[strcspn(buf, "\n")] = '\0';
}

static void read_exact(void *buf, size_t len) {
    size_t done = 0;

    while (done < len) {
        ssize_t got = read(STDIN_FILENO, (char *)buf + done, len - done);
        if (got <= 0)
            exit(0);
        done += (size_t)got;
    }
}

static unsigned long read_number(const char *prompt, unsigned long min, unsigned long max) {
    char buf[64];

    for (;;) {
        char *end = NULL;
        unsigned long value;

        printf("%s", prompt);
        read_line(buf, sizeof(buf));
        errno = 0;
        value = strtoul(buf, &end, 10);
        if (!errno && end != buf && *end == '\0' && value >= min && value <= max)
            return value;

        puts("The studio rejected that take.");
    }
}

static int read_slot(const char *prompt) {
    return (int)read_number(prompt, 0, MAX_SEGMENTS - 1);
}

static Segment *require_segment(int idx) {
    if (idx < 0 || idx >= MAX_SEGMENTS || g_segments[idx] == NULL)
        die("That segment is off-air.");

    return g_segments[idx];
}

static void default_cue(char *script) {
    puts("\nThe director nods and the scene rolls:");
    puts(script);
}

static void cast_segment(void) {
    int idx;
    Segment *seg;
    char title_buf[0x40];
    unsigned long script_len;

    idx = read_slot("Cast segment slot (0-11): ");
    if (g_segments[idx] != NULL)
        die("That slot already has a segment.");

    printf("Scene title: ");
    read_line(title_buf, sizeof(title_buf));

    script_len = read_number("Dialogue length: ", 1, MAX_SCRIPT_SIZE);

    seg = calloc(1, sizeof(*seg));
    if (!seg)
        die("The set ran out of budget.");

    seg->script = calloc(1, script_len + 1);
    if (!seg->script)
        die("The writers went bankrupt.");

    memset(seg->title, 0, sizeof(seg->title));
    memcpy(seg->title, title_buf, TITLE_SIZE - 1);
    seg->script_len = script_len;
    seg->cue = default_cue;
    seg->episode = g_next_episode++;

    printf("Enter the dialogue for episode %lu:\n", seg->episode);
    read_exact(seg->script, script_len);
    seg->script[script_len] = '\0';

    g_segments[idx] = seg;
    g_live[idx] = 1;

    puts("The segment is ready for broadcast.");
}

static void rewrite_segment(void) {
    int idx;
    Segment *seg;

    idx = read_slot("Rewrite slot (0-11): ");
    seg = require_segment(idx);

    printf("New dialogue (%zu bytes):\n", seg->script_len);
    read_exact(seg->script, seg->script_len);
    seg->script[seg->script_len] = '\0';

    puts("The rewrite is locked in.");
}

static void review_segment(void) {
    int idx;
    Segment *seg;

    idx = read_slot("Review slot (0-11): ");
    seg = require_segment(idx);

    printf("\n[segment %d] %.24s\n", idx, seg->title);
    printf("Episode: %lu\n", seg->episode);
    printf("Dialogue (%zu bytes): ", seg->script_len);
    fwrite(seg->script, 1, seg->script_len, stdout);
    putchar('\n');
}

static void cut_segment(void) {
    int idx;
    Segment *seg;

    idx = read_slot("Cut slot (0-11): ");
    if (idx < 0 || idx >= MAX_SEGMENTS || g_segments[idx] == NULL || !g_live[idx])
        die("That segment is already gone.");

    seg = g_segments[idx];
    free(seg->script);
    free(seg);

    g_live[idx] = 0;
    puts("The segment has been cut from the schedule.");
}

static void play_segment(void) {
    int idx;
    Segment *seg;

    idx = read_slot("Air slot (0-11): ");
    seg = require_segment(idx);

    seg->cue(seg->script);
    exit(0);
}

static void menu(void) {
    puts("What would you like to do today, Truman?\n");
    puts("1. Cast a segment");
    puts("2. Rewrite a segment");
    puts("3. Review a segment");
    puts("4. Cut a segment");
    puts("5. Air a segment");
    puts("6. Leave the show\n");
}

int main(void) {
    char buf[16];
    int choice;

    init_io();

    for (;;) {
        show_intro();
        menu();
        printf("Choice: ");
        read_line(buf, sizeof(buf));
        choice = atoi(buf);

        switch (choice) {
        case 1:
            cast_segment();
            break;
        case 2:
            rewrite_segment();
            break;
        case 3:
            review_segment();
            break;
        case 4:
            cut_segment();
            break;
        case 5:
            play_segment();
            break;
        case 6:
            puts("\n\"That's right, stay comfortable, Truman.\"");
            puts("The show continues...");
            return 0;
        default:
            puts("\n\"That's not in the script, Truman!\"");
            break;
        }

        puts("\nPress Enter to continue...");
        read_line(buf, sizeof(buf));
    }
}