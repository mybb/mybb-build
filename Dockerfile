FROM debian:stretch-slim

# Clean & update
RUN apt-get -qq autoclean && apt-get -qq update

# Install core packages
RUN apt-get -qq install software-properties-common python3-software-properties git unzip wget 

# Add repositories
RUN apt-get -qq install apt-transport-https lsb-release ca-certificates && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'

# Update & clean
RUN apt-get -qq update && apt-get -qq clean

# Install PHP and extensions
RUN apt-get -qq install php7.1-cli php7.1-curl php7.1-mbstring php7.1-xml php7.1-zip

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

# Add Phing project build file
COPY ./build.xml ./build.xml

# Switch to the added user
USER user
