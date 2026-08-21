#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

#include "bot.h"

void show_tank(void);

struct Bot bot = {
    "@budi reacted 💀 to your post",
    show_tank,
};

void bot_react(struct Bot *bot)
{
    const char *names[] = {"budi", "siti", "agus", "dewi", "eko"};
    const char *reacts[] = {"💀", "😂", "🤣"};
    int name_idx;
    int react_idx;

    name_idx = rand() % 5;
    react_idx = rand() % 3;

    snprintf(
        bot->reaction,
        sizeof(bot->reaction),
        "@%s reacted %s to your post",
        names[name_idx],
        reacts[react_idx]
    );

    usleep(1000000);
    write(1, bot->reaction, sizeof(bot->reaction) + sizeof(bot->view));
    puts("");
}
