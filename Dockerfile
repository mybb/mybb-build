FROM php:7.2-cli

ENV DEBIAN_FRONTEND noninteractive

RUN set -e; \
	\
	savedAptMark="$(apt-mark showmanual)" \
		> /dev/null; \
	\
	apt-get -qq update; \
	\
	# Install required packages
	apt-get install -y -qq --no-install-suggests --no-install-recommends \
		software-properties-common \
		python3-software-properties \
		git \
		libzip-dev \
		unzip \
		wget \
		strip-nondeterminism \
		> /dev/null; \
	\
	docker-php-ext-configure zip --with-libzip \
		> /dev/null; \
	docker-php-ext-install zip \
		> /dev/null; \
	\
	# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' \
		> /dev/null; \
	apt-mark manual $savedAptMark \
		> /dev/null; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual \
		> /dev/null; \
	\
	rm -rf /var/lib/apt/lists/*

# Create an unprivileged user & change directory
RUN adduser --disabled-password --gecos "" user
WORKDIR /home/user/

# Install Phing
COPY ./phing-fetch.sh ./phing-fetch.sh
RUN chmod +x phing-fetch.sh; \
	./phing-fetch.sh; \
	rm -f ./phing-fetch.sh; \
	cp phing-latest.phar /usr/local/bin/phing; \
	chmod +x /usr/local/bin/phing;

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
