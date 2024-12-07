#!/bin/bash

# curl -s https://raw.githubusercontent.com/EduardoKrausME/moodle_friendly_installation/refs/heads/master/install.sh | bash
# apt  -y remove mysql* && apt -y remove php* && apt -y remove apache* && apt -y autoremove

# Update the packages
apt update -y


# Check if the script is being run as root
if [ "$EUID" -ne 0 ]; then
  echo "\033[31m This script must be run as root. Please re-run using sudo or as the root user.  \033[0m"
  exit 1
fi


# Check if MySql is installed
if ! command -v mysql &> /dev/null; then
    echo "\033[31m MySql is not installed. Installing now...  \033[0m"

    apt install -y mysql-server

    # Start Service
    systemctl start mysql.service

    # Confirm the installation
    if command -v mysql &> /dev/null; then
        echo "\033[31m MySql was successfully installed!  \033[0m"
    else
        echo "\033[31m There was an error installing MySql.  \033[0m"
    fi
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "\033[31m PHP is not installed. Installing now...  \033[0m"

    # Install PHP
    apt install -y php php-{cli,curl,gd,xml,mbstring,mysqli,intl,soap,xmlrpc,zip,bcmath} apache2 libapache2-mod-php

    # To allow for HTTPS traffic, allow the "Apache Full" profile:
    ufw allow 'Apache Full'

    # Then delete the redundant “Apache” profile:
    ufw delete allow 'Apache'

    # Confirm the installation
    if command -v php &> /dev/null; then
        echo "\033[31m PHP was successfully installed!  \033[0m"
    else
        echo "\033[31m There was an error installing PHP.  \033[0m"
    fi
fi

# Check if GIT is installed
if ! command -v git &> /dev/null; then
    apt install -y git
fi

# Check if DIALOG is installed
if ! command -v dialog &> /dev/null; then
    apt install -y dialog
fi


# Check if DIALOG is installed
if ! command -v certbot &> /dev/null; then
    apt install -y certbot python3-certbot-apache
fi


#curl https://raw.githubusercontent.com/EduardoKrausME/moodle_friendly_installation/refs/heads/master/install-info.php -o install-info.php
php install-info.php
#rm -f install-info.php


