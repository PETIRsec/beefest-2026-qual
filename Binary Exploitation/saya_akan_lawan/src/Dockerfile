FROM ubuntu:24.04@sha256:33ceb71981b602c1a7443a53469e4dba065f7503eab3078a2d7a57a2ab987517 AS builder

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build

COPY chall.c setup.c bot.c setup.h bot.h ./

RUN gcc \
    -O0 \
    -fPIE \
    -pie \
    -fno-omit-frame-pointer \
    -fstack-protector-all \
    -Wl,-z,relro,-z,now \
    -o chall \
    chall.c setup.c bot.c

FROM scratch AS artifact

COPY --from=builder /build/chall /chall

FROM ubuntu:24.04@sha256:33ceb71981b602c1a7443a53469e4dba065f7503eab3078a2d7a57a2ab987517

RUN apt-get update \
    && apt-get install -y --no-install-recommends xinetd \
    && rm -rf /var/lib/apt/lists/*

RUN useradd --create-home --uid 1337 --shell /usr/sbin/nologin ctf

WORKDIR /home/ctf

COPY --from=builder /build/chall ./
COPY flag.txt run.sh ./
COPY xinetd /etc/xinetd.d/saya_akan_lawan

RUN chown -R root:root /home/ctf \
    && chmod 555 /home/ctf \
    && chmod 555 /home/ctf/chall /home/ctf/run.sh \
    && chmod 444 /home/ctf/flag.txt \
    && chmod 644 /etc/xinetd.d/saya_akan_lawan

EXPOSE 5010

CMD ["xinetd", "-dontfork"]
