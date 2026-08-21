FROM node:24-alpine

WORKDIR /app

# Fixed the flag to --no-cache
RUN apk update && apk add --no-cache \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ca-certificates \
    ttf-freefont

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser
ENV PUPPETEER_SKIP_DOWNLOAD=true

RUN npm install express puppeteer express-rate-limit

ADD bot.js bot.html ./

EXPOSE 1337

CMD ["node", "bot.js"]