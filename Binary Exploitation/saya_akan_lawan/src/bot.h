#ifndef BOT_H
#define BOT_H

struct Bot {
    char reaction[40];
    void (*view)(void);
};

extern struct Bot bot;

void bot_react(struct Bot *bot);

#endif
