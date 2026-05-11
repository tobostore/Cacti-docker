FROM php:7.0-apache

# Fix EOL Debian Stretch repository
RUN echo "deb http://archive.debian.org/debian stretch main" > /etc/apt/sources.list \
    && echo "deb http://archive.debian.org/debian-security stretch/updates main" >> /etc/apt/sources.list \
    && echo "Acquire::Check-Valid-Until false;" > /etc/apt/apt.conf.d/99no-check-valid

RUN apt-get update && apt-get install -y --allow-unauthenticated \
    libsnmp-dev \
    libpng-dev \
    libxml2-dev \
    snmpd \
    snmp \
    rrdtool \
    wget \
    ca-certificates \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    snmp \
    gd \
    sockets \
    xml \
    pcntl

# Install ADOdb v5.20.x (kompatibel PHP 7 + Cacti lama)
RUN mkdir -p /usr/share/php/adodb \
    && cd /tmp \
    && wget -q https://github.com/ADOdb/ADOdb/archive/refs/tags/v5.20.19.tar.gz \
    && tar -xzf v5.20.19.tar.gz \
    && cp -r ADOdb-5.20.19/* /usr/share/php/adodb/ \
    && rm -rf v5.20.19.tar.gz ADOdb-5.20.19

RUN mkdir -p /var/log/cacti

# Enable Apache rewrite
RUN a2enmod rewrite