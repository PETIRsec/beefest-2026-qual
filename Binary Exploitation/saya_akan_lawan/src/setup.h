#ifndef SETUP_H
#define SETUP_H

struct State {
    int phones_taken;
    int patience;
};

extern struct State *saya_akan_lawan;

int *setup(void);
void update_state(int *value, int amount);

#endif
