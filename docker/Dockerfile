FROM php:8.2-cli-alpine

WORKDIR /app

COPY AquaRelay.phar .
COPY docker/start.sh /start.sh

RUN chmod +x /start.sh

VOLUME ["/app/data"]

EXPOSE 19132/udp

ENTRYPOINT ["/start.sh"]
