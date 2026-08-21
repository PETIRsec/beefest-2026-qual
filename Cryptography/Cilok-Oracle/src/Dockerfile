FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    socat \
    && pip3 install --no-cache-dir pycryptodome \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -r ctf && useradd -r -g ctf ctf

WORKDIR /home/ctf

COPY chall.py .
COPY flag.txt .

RUN chmod 550 /home/ctf/chall.py \
    && chmod 440 /home/ctf/flag.txt \
    && chown -R root:ctf /home/ctf

RUN printf '#!/bin/bash\ncd /home/ctf && exec python3 chall.py\n' > /home/ctf/run \
    && chmod 750 /home/ctf/run \
    && chown root:ctf /home/ctf/run

EXPOSE 3254

CMD socat TCP-L:3254,fork,reuseaddr EXEC:"/home/ctf/run",stderr
