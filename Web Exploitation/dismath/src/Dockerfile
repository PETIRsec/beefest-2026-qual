FROM python:3.11-slim

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    texlive-latex-base \
    texlive-latex-extra \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

RUN echo "BeeCTF{Duh_dismathlagi_dismathLagi_disssnutz_hehehehheeh_galucu_ya:(}" > /flag.txt

EXPOSE 1337

CMD ["python", "app.py"]
