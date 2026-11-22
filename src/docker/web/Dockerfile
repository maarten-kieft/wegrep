# PHP + Apache for local development: adds the MySQL driver and a sendmail that
# hands every mail() to the Mailpit container, so no mail ever leaves your machine.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends msmtp \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql

COPY msmtprc /etc/msmtprc
RUN echo 'sendmail_path = "/usr/bin/msmtp -t"' > /usr/local/etc/php/conf.d/mail.ini
