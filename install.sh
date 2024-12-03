#!/bin/bash


# curl -s https://raw.githubusercontent.com/EduardoKrausME/moodle_friendly_installation/refs/heads/master/install.sh | bash


# Check if the script is being run as root
if [ "$EUID" -ne 0 ]; then
  echo "This script must be run as root. Please re-run using sudo or as the root user."
  exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Installing now..."

    # Update the packages
    apt update -y -qq -o=Dpkg::Use-Pty=0

    # Install PHP
    apt install -y -qq -o=Dpkg::Use-Pty=0 php php-{cli,curl,gd,xml,mbstring,mysqli,intl,soap,xmlrpc,zip,bcmath} libapache2-mod-php

    # Confirm the installation
    if command -v php &> /dev/null; then
        echo "PHP was successfully installed!"
    else
        echo "There was an error installing PHP."
    fi
fi

# Check if GIT is installed
if ! command -v git &> /dev/null; then
    apt install -y -qq -o=Dpkg::Use-Pty=0 git
fi

# Check if DIALOG is installed
if ! command -v dialog &> /dev/null; then
    apt install -y -qq -o=Dpkg::Use-Pty=0 dialog
fi


curl -O https://raw.githubusercontent.com/EduardoKrausME/moodle_friendly_installation/refs/heads/master/install-info.php install-info.php
php install-info.php

