#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>
#include <unistd.h>

#include "bot.h"
#include "setup.h"

#define BUZZER 0x1337

struct Phone {
    char name[32];
    int (*post)(const char *);
};

struct Phone phone;

int buzzer_phone_active(int *hp_buzzer)
{
    unsigned short siuuu;

    if (hp_buzzer == NULL) {
        return 0;
    }

    siuuu = BUZZER ^ ((uintptr_t)hp_buzzer >> 12);
    return *hp_buzzer == siuuu;
}

void show_tank(void)
{
    int bars = saya_akan_lawan->patience / 10;

    printf("\nOwi's patience\n[");

    for (int i = 0; i < 10; i++) {
        if (i < bars) {
            putchar('#');
        } else {
            putchar('-');
        }
    }

    printf("] %d%%\n", saya_akan_lawan->patience);
}

void take_phone(void)
{
    if (saya_akan_lawan->phones_taken >= 2) {
        puts("There are no more phones.");
        return;
    }

    if (saya_akan_lawan->phones_taken == 1 &&
        saya_akan_lawan->patience != 80) {
        puts("Use your current phone first.");
        return;
    }

    if (saya_akan_lawan->phones_taken == 1) {
        puts("You check your phone again.");
    } else {
        puts("");
        puts("You found a normal phone.");
    }

    update_state(&saya_akan_lawan->phones_taken, 1);
    phone.post = puts;

    puts("Give your phone a name:");
    printf("> ");
    read(0, phone.name, 40);
}

void publish_insult(const char *text)
{
    phone.post(text);
    update_state(&saya_akan_lawan->patience, -20);
}

void insult(int *hp_buzzer)
{
    char user_input[16];
    char flag[128];
    FILE *file;
    ssize_t length;

    if (saya_akan_lawan->phones_taken == 0) {
        puts("Take the phone first.");
        return;
    }

    if (!buzzer_phone_active(hp_buzzer) &&
        saya_akan_lawan->patience <= 40) {
        puts("Your phone cannot post any more insults.");
        return;
    }

    puts("");
    puts("Write an insult:");
    printf("> ");

    length = read(
        0,
        user_input,
        sizeof(user_input) - 1
    );

    if (length <= 0) {
        return;
    }

    user_input[length] = '\0';

    puts("");
    puts("Netizen posted:");

    publish_insult(user_input);
    bot_react(&bot);

    usleep(1500000);
    show_tank();

    if (buzzer_phone_active(hp_buzzer)) {
        puts("[BUZZER PHONE ACTIVE]");
        puts("Insult limit: UNLIMITED");
    }

    if (saya_akan_lawan->patience == 80) {
        puts("\"4,5 tahun saya difitnah-fitnah, saya diam.\"");
    }

    if (saya_akan_lawan->patience == 60) {
        puts("\"Dijelek-jelekin, saya juga diam.\"");
    }

    if (saya_akan_lawan->patience == 40) {
        puts("\"Dicela, direndah-rendahkan, saya juga diam.\"");
    }

    if (saya_akan_lawan->patience == 20) {
        puts("\"Dihujat-hujat, dihina-hina, saya juga diam.\"");
    }

    if (saya_akan_lawan->patience == 0 &&
        buzzer_phone_active(hp_buzzer)) {
        puts(
            "\"Tetapi hari ini di Jogja saya sampaikan: "
            "saya akan lawan!\""
        );
        puts("\"Ingat sekali lagi, saya akan lawan!\"");
        puts("");

        file = fopen("flag.txt", "r");

        if (file == NULL) {
            puts("Flag file not found.");
            exit(1);
        }

        if (fgets(flag, sizeof(flag), file) == NULL) {
            puts("Could not read the flag.");
            fclose(file);
            exit(1);
        }

        fclose(file);

        printf("%s", flag);
        exit(0);
    }
}

int main(void)
{
    int choice;
    int *hp_buzzer;

    hp_buzzer = setup();

    puts("====================================");
    puts("          OWI'S PATIENCE TANK");
    puts("====================================");

    while (1) {
        puts("");
        puts("1. Take phone");
        puts("2. Insult Owi");
        puts("3. Leave");
        printf("> ");

        if (scanf("%d%*c", &choice) != 1) {
            exit(0);
        }

        if (choice == 1) {
            take_phone();
        }

        if (choice == 2) {
            insult(hp_buzzer);
        }

        if (choice == 3) {
            exit(0);
        }
    }
}
