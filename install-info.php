<?php

test__max_input_vars();

// Check the connection with MySQL
$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echoColor("Welcome to the Moodle installer and configurator. Press Enter to start:", "blue");
fgets(STDIN);


// Domain
do {
    $selectedDomain = dialogGetData(
        'Please enter the URL of your Moodle now',
        'It must not end with "/"\nExample: http://yourmoodle.mysite.com\nExample: http://yourmoodle.mysite.com/moodle2');

    $regex = '/^(https?:\/\/[a-zA-Z0-9-]+\.[a-zA-Z0-9-]+(?:\.[a-zA-Z]{2,})?)(\/[^\s]*)?$/';

    $status = true;
    $urlparse = parse_url($selectedDomain);

    if (!isset($urlparse["scheme"]) || !in_array($urlparse["scheme"], ["http", "https"])) {
        echoColor("URL must start with HTTP or HTTPS", "red");
        $status = false;
    }

    if (!isset($urlparse["host"])) {
        echoColor("Invalid URL", "red");
        $status = false;
    }

    if (isset($urlparse["path"])) {
        $path = $urlparse["path"];
        if (substr($path, -1) === '/') {
            echoColor("URL cannot end with /", "red");
            $status = false;
        }
    }

    if ($status) {
        break;
    } else {
        echoColor("URL is not valid. Press enter to try again!", "red");
        fgets(STDIN);
    }
} while (true);

$host = parse_url($selectedDomain, PHP_URL_HOST);
$path = parse_url($selectedDomain, PHP_URL_PATH);


// E-mail
$selectedEmail = dialogGetData("What email should be used for the Moodle registration?");

$local = apacheConfiguration();


// Moodle Version
$command = "dialog --menu 'Choose the Moodle version' 15 50 5 \
                           MOODLE_405_STABLE 'Moodle 4.5' \
                           MOODLE_404_STABLE 'Moodle 4.4' \
                           MOODLE_403_STABLE 'Moodle 4.3' \
                           MOODLE_402_STABLE 'Moodle 4.2' \
                           main              'Moodle 5.0 Beta' 3>&1 1>&2 2>&3";
$selectedVersion = shell_exec($command);

echoColor("Now I will download the Moodle code. Stay tuned, I'll be back with more options for you shortly:", "green");

shell_exec("git clone --depth 1 https://github.com/moodle/moodle/ -b {$selectedVersion} {$local}");
shell_exec("mkdir -p /var/www/moodledata/{$host}{$path}");
shell_exec("mkdir -R www-data:www-data /var/www/moodledata/{$host}{$path}");
shell_exec("chmod 755 /var/www/moodledata/{$host}{$path}");

echoColor("The codes are in the folder {$local} and now let's move to the database:", "blue");

// Sanitize the host to create a database name
$dbName = preg_replace('/[^a-z0-9]/i', '', $host);
if (isset($path[1])) {
    $dbName = "{$dbName}" . preg_replace('/[^a-z0-9]/i', '', $path); // Add sanitized path to the database name if it exists
}

// Create a MySQL user and password
$mysql_info = createMySqlPassword("moodle_{$dbName}");
if ($mysql_info === false) {
    echoColor("Unable to create a user in the database, aborting", "red");
    exit; // Abort execution if user creation fails
}


file_put_contents("{$local}/config.php", getConfigPhp());


echoColor("Now I will install Moodle", "green");
system("php {$local}/admin/cli/install_database.php --adminuser=admin --adminpass=Password@123# --adminemail={$selectedEmail} --fullname=Moodle --shortname=Moodle --agree-license");


echoColor("Installation completed. Now access it through the browser:
    Host:  {$selectedDomain}
    Login: admin
    Password: Password@123#", "blue");


/**
 * Function test__max_input_vars
 *
 */
function test__max_input_vars() {
    // Verifica o valor atual de max_input_vars
    $currentValue = ini_get('max_input_vars');

    if ($currentValue !== false && $currentValue < 5000) {
        echoColor("O valor atual de max_input_vars é {$currentValue}. Tentando atualizar...", "red");

        // Localiza o arquivo php.ini
        $phpIniFile = php_ini_loaded_file();
        if ($phpIniFile === false) {
            echoColor("Arquivo php.ini não encontrado. Altere e tente novamente.", "red");
            exit;
        }

        // Grava as alterações no arquivo
        file_put_contents($phpIniFile, "\n\nmax_input_vars = 5000\n", FILE_APPEND);
    }
}

function apacheConfiguration() {
    global $host, $path, $selectedDomain, $selectedEmail;

    $vhostFile = "/etc/apache2/sites-available/{$host}.conf";
    $local = false;
    if (file_exists($vhostFile)) {
        echoColor("The domain is already configured.", "blue");
        $vhostContent = file_get_contents($vhostFile);

        preg_match('/DocumentRoot(.*)\n/', $vhostContent, $conf);
        $local = trim($conf[1]);

        echoColor("The domain's root folder is: {$local}", "green");
    }

    if (!$local) {
        echoColor(
            "Next, you will be asked whether you want to install Moodle in `/var/www/html` or in `/var/www/html/{$host}`.\n\n" .
            "The option `/var/www/html/{$host}` is recommended if you plan to host multiple Moodle sites on different domains. " .
            "On the other hand, the `/var/www/html/` option is ideal if you only want a single domain or a single Moodle installation on this server.",
            "green");
        echoColor("Press enter to continue!", "black");
        fgets(STDIN);

        $command = "dialog --menu 'How do you want the installation?' 15 50 2 \
                           root   '/var/www/html{$path}' \
                           domain '/var/www/html/{$host}{$path}' \
                           3>&1 1>&2 2>&3";
        if (shell_exec($command) == "root") {
            $local = "/var/www/html{$path}";
            $documentRoot = "/var/www/html";
        } else {
            $local = "/var/www/html/{$host}{$path}";
            $documentRoot = "/var/www/html/{$host}";
        }

        $vhostContent = "
<VirtualHost *:80>
    ServerAdmin  {$selectedEmail}
    ServerName   {$host}
    DocumentRoot {$documentRoot}

    ErrorLog  /var/www/{$host}-error.log
    CustomLog /var/www/{$host}-access.log combined

    <Directory /var/www/html/{$host}>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>";

        file_put_contents($vhostFile, $vhostContent);
        shell_exec("ln -s {$vhostFile} /etc/apache2/sites-enabled/");

        // Reinicia o apache
        shell_exec("service apache2 restart");
    }

    if (file_exists("{$local}")) {
        echoColor("The folder {$local} already exists, and the installation has been aborted.", "red");
        exit;
    }


    // SSL
    if (strpos($selectedDomain, "https") === 0) {
        $enableSsl = true;
    } else {
        $command = "dialog --menu 'How do you enable HTTPS?' 15 50 2 \
                              YES YES \
                              NO  NO  \
                              3>&1 1>&2 2>&3";
        if (shell_exec($command) == "YES") {
            $enableSsl = true;
            $selectedDomain = "https" . substr($selectedDomain, 4);
        } else {
            $enableSsl = false;
        }
    }
    if ($enableSsl) {
        shell_exec("certbot --apache -m {$selectedEmail} -d {$host} --agree-tos --no-eff-email");
        shell_exec("(crontab -l; echo \"0 0 1 */2 * /usr/bin/certbot renew\") | crontab -");
    }

    return $local;
}


/**
 * Function createMySqlPassword
 *
 * @param $dbName
 *
 * @return array|bool
 */
function createMySqlPassword($dbName) {

    // Generate a random password for the user
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $passwordAleatoria = '';
    for ($i = 0; $i < 9; $i++) {
        $passwordAleatoria .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    $passwordAleatoria .= "#$&";

    $conn = new mysqli('localhost', 'root', "");

    if ($conn->connect_error) {
        echoColor("Error trying to connect to the MySQL database. If the problem persists, please consult the system administrator to ensure that the MySQL server is active and accessible.", "red");
        fgets(STDIN);

        $dbName = dialogGetData("Please enter new MySql DB name:");
        $dbUser = dialogGetData("Please enter new MySql USER name:");
        $password = dialogGetData("Please enter new MySql PASSWORD:");

        $command = "dialog --menu 'Choose the database server type:' 15 50 2 \
                          'mariadb' 'MariaDB' \
                          'mysqli'  'MySql' \
                          3>&1 1>&2 2>&3";
        $dbType = shell_exec($command);

        return [
            "dbtype" => $dbType,
            "dbname" => $dbName,
            "dbuser" => $dbUser,
            "password" => $password,
        ];
    }

    $dbUser = $dbName;
    do {
        $dbUser = dialogGetData("Please enter new MySql USER name:", "", $dbUser);
        try {
            $sql_criar_usuario = "CREATE USER '{$dbUser}'@'localhost' IDENTIFIED BY '{$passwordAleatoria}'";
            $conn->query($sql_criar_usuario);

            $sql_grant_usage = "GRANT USAGE ON *.* TO '{$dbUser}'@'localhost'";
            $conn->query($sql_grant_usage);

            $sql_conceder_permissao = "ALTER USER '{$dbUser}'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0";
            $conn->query($sql_conceder_permissao);
        } catch (Exception $e) {
            echoColor($e->getMessage(), "red");

            echoColor("Press enter to try again!", "red");
            fgets(STDIN);
            continue;
        }

        break;
    } while (true);


    do {
        $dbName = dialogGetData("Please enter new MySql DB name:", "", $dbName);

        try {
            $sql_create_database = "CREATE DATABASE `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
            if (!$conn->query($sql_create_database) === TRUE) {
                return false;
            }

            $sql_grant_privileges = "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'";
            if (!$conn->query($sql_grant_privileges) === TRUE) {
                return false;
            }
        } catch (Exception $e) {
            echoColor($e->getMessage(), "red");

            echoColor("Press enter to try again!", "red");
            fgets(STDIN);
            continue;
        }

        break;
    } while (true);

    $serverInfo = $conn->server_info;

    if (stripos($serverInfo, 'MariaDB') !== false) {
        $dbType = "mariadb";
    } else {
        $dbType = "mysqli";
    }

    // Close connection
    $conn->close();

    return [
        "dbtype" => $dbType,
        "dbname" => $dbName,
        "dbuser" => $dbUser,
        "password" => $passwordAleatoria,
    ];
}

/**
 * Function echoColor
 *   30: Black
 *   31: Red
 *   32: Green
 *   33: Yellow
 *   34: Blue
 *   35: Magenta
 *   36: Cyan
 *   37: White
 *
 * @param $text
 * @param $color
 *
 * @return string
 */
function echoColor($text, $color) {
    switch (strtolower($color)) {
        case 'black':
            echo "\033[30m";
            break;
        case 'red':
            echo "\033[31m";
            break;
        case 'green':
            echo "\033[32m";
            break;
        case 'yellow':
            echo "\033[33m";
            break;
        case 'blue':
            echo "\033[34m";
            break;
        case 'magenta':
            echo "\033[35m";
            break;
        case 'cyan':
            echo "\033[36m";
            break;
        case 'white':
            echo "\033[37m";
            break;
        default:
            return "\033[0m"; // Reset color
    }

    echo "\n\n\n";
    echo $text;
    echo "\n";

    echo "\033[0m"; // Reset color
}

/**
 * Function dialogGetData
 *
 * @param string $title
 * @param string $subtitle
 * @param string $default
 *
 * @return string
 */
function dialogGetData($title, $subtitle = "", $default = "") {
    $command = "dialog --title '{$title}' --inputbox '{$subtitle}' 11 50 '{$default}' 3>&1 1>&2 2>&3";
    $data = shell_exec($command);
    return $data;
}

/**
 * Function getConfigPhp
 *
 * @return string
 */
function getConfigPhp() {
    global $mysql_info, $selectedDomain, $host, $path;

    $config = "<?php // Moodle configuration file

unset( \$CFG );
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '{$mysql_info['dbtype']}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = 'localhost';
\$CFG->dbname    = '{$mysql_info['dbname']}';
\$CFG->dbuser    = '{$mysql_info['dbuser']}';
\$CFG->dbpass    = '{$mysql_info['password']}';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array(
    'dbpersist'   => 0,
    'dbport'      => '',
    'dbsocket'    => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '{$selectedDomain}';
\$CFG->dataroot  = '/var/www/moodledata/{$host}{$path}';
\$CFG->admin     = 'admin';
// \$CFG->sslproxy = true;

\$CFG->directorypermissions = 0777;

\$CFG->showcampaigncontent           = false;
\$CFG->showservicesandsupportcontent = false;
\$CFG->enableuserfeedback            = true;
// \$CFG->registrationpending           = false;
// \$CFG->site_is_public                = false;
// \$CFG->disableupdatenotifications    = true;

require_once( __DIR__ . '/lib/setup.php' );";

    return $config;
}
