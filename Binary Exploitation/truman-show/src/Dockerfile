FROM ubuntu:20.04 AS builder

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y \
       build-essential \
       binutils \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build

COPY truman.c /build/

# Compile truman with GLIBC 2.31
RUN gcc \
    -O2 \
    -fstack-protector-strong \
    -fPIE \
    -pie \
    -Wl,-z,relro \
    -Wl,-z,lazy \
    -o truman \
    truman.c \
    && strip --strip-all truman


FROM ubuntu:20.04

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y \
       xinetd \
    && rm -rf /var/lib/apt/lists/*

RUN useradd -m -s /bin/bash truman

COPY --from=builder /build/truman /home/truman/truman
COPY truman.xinetd /etc/xinetd.d/truman
COPY flag.txt /home/truman/flag.txt

RUN chown -R root:truman /home/truman \
    && chmod 750 /home/truman \
    && chmod 750 /home/truman/truman \
    && chmod 440 /home/truman/flag.txt

WORKDIR /home/truman

EXPOSE 6007

CMD ["/usr/sbin/xinetd", "-dontfork", "-stayalive"]