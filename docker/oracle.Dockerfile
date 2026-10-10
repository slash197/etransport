# Reference Schematron processor (Saxon + SchXslt), used only to record what
# the tests expect: make golden. The package itself needs none of this.
ARG PHP_VERSION=8.2

FROM composer:2 AS composer

FROM php:${PHP_VERSION}-cli-alpine

ARG SAXON_VERSION=10.9
ARG SCHXSLT_VERSION=1.10.1

RUN apk add --no-cache git unzip curl openjdk17-jre-headless

RUN curl -fsSL -o /opt/saxon.jar "https://repo1.maven.org/maven2/net/sf/saxon/Saxon-HE/${SAXON_VERSION}/Saxon-HE-${SAXON_VERSION}.jar" \
    && curl -fsSL -o /tmp/schxslt.jar "https://repo1.maven.org/maven2/name/dmaus/schxslt/schxslt/${SCHXSLT_VERSION}/schxslt-${SCHXSLT_VERSION}.jar" \
    && unzip -q /tmp/schxslt.jar 'xslt/2.0/*' -d /opt/schxslt \
    && rm /tmp/schxslt.jar

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app
