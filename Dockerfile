FROM php:7.1-cli

ENV DEBIAN_FRONTEND noninteractive

RUN set -ex; \
        \
	apt-get update; \
	apt-get install -y -qq --no-install-suggests --no-install-recommends \
		software-properties-common \
		python3-software-properties \
		git \
		unzip \
		wget \
		strip-nondeterminism \
	; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get install -y --no-install-suggests --no-install-recommends \
		zlib1g-dev \
	; \
	\
	docker-php-ext-install zip; \
	\
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	rm -rf /var/lib/apt/lists/*

# Create an unprivileged user & change directory
RUN adduser --disabled-password --gecos "" user
WORKDIR /home/user/

# Install Phing
COPY ./phing-fetch.sh ./phing-fetch.sh
RUN chmod +x phing-fetch.sh
RUN ./phing-fetch.sh
RUN cp phing-latest.phar /usr/local/bin/phing
RUN chmod +x /usr/local/bin/phing

# Set custom PHP configuration directives
COPY ./php-additional.ini /usr/local/etc/php/conf.d/php-additional.ini

# Add build scripts
COPY ./php/ ./php/
RUN chmod +x ./php/*.php

# Add Phing project build file
COPY ./build.xml ./build.xml

# Switch to the added user
USER user

ENTRYPOINT ["phing"]
