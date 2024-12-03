# Moodle Friendly Installation

This script aims to simplify the installation of Moodle on Ubuntu servers, setting up the environment in an optimized and user-friendly way.

## Requirements

- Operating system: Ubuntu 18 or higher.
- Root access (or sudo permission) on the server.
- An internet connection to download necessary packages.

## Installation

To quickly and easily install Moodle on your Ubuntu server, simply run the following command in the terminal:

```bash
sudo curl -s https://raw.githubusercontent.com/EduardoKrausME/moodle_friendly_installation/refs/heads/master/install.sh | bash
```

This command downloads and runs the installation script, which will automatically set up the environment required to run Moodle on your server.

After running the command, follow the steps prompted on the screen.

### What the script does

1. **Installs Dependencies**: The script automatically installs all necessary packages and dependencies, such as the Apache web server, PHP, MySQL/MariaDB, and other libraries required by Moodle.
2. **Database Configuration**: It configures MySQL/MariaDB and creates the database for Moodle.
3. **Moodle Configuration**: The script downloads the latest version of Moodle and sets up the system's initial configuration.
4. **Permissions and Optimizations**: It adjusts file permissions and optimizes settings to ensure Moodle runs efficiently.

## Contributions

Feel free to contribute improvements, bug fixes, or new features. If you encounter any issues or have suggestions, open an *issue* or submit a *pull request*.

## License

This repository is licensed under the [MIT License](LICENSE).
