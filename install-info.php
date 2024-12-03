<?php

test__max_input_vars();

// Check the connection with MySQL
$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Welcome to the Moodle installer and configurator. Press Enter to start:";
fgets(STDIN);

$command = "dialog --menu 'Choose the Moodle version' 15 50 5 \
                            MOODLE_405_STABLE 'Moodle 4.5' \
                            MOODLE_404_STABLE 'Moodle 4.4' \
                            MOODLE_403_STABLE 'Moodle 4.3' \
                            MOODLE_402_STABLE 'Moodle 4.2' \
                            main              'Moodle 5.0 Beta' 3>&1 1>&2 2>&3";
$selectedVersion = shell_exec($command);

do {
    $command = "dialog --title 'Please enter the URL of your Moodle now' \
                       --inputbox 'It must not end with \"/\"\nExample: http://yourmoodle.mysite.com\nExample: http://yourmoodle.mysite.com/moodle2' 15 50  3>&1 1>&2 2>&3";
    $selectedDomain = shell_exec($command);

    $regex = '/^(https?:\/\/[a-zA-Z0-9-]+\.[a-zA-Z0-9-]+(?:\.[a-zA-Z]{2,})?)(\/[^\s]*)?$/';

    echo "\033[31m\n\n\n";
    $status = true;
    $urlparse = parse_url($selectedDomain);

    if (!isset($urlparse["scheme"]) || !in_array($urlparse["scheme"], ["http", "https"])) {
        echo "URL must start with HTTP or HTTPS\n";
        $status = false;
    }

    if (!isset($urlparse["host"])) {
        echo "Invalid URL\n";
        $status = false;
    }

    if (isset($urlparse["path"])) {
        $path = $urlparse["path"];
        if (substr($path, -1) === '/') {
            echo "URL cannot end with /\n";
            $status = false;
        }
    }

    echo "\033[0m\n";
    if ($status) {
        break;
    } else {
        echo "URL is not valid. Press enter to type again!";
        fgets(STDIN);
    }
} while (true);


$host = parse_url($selectedDomain, PHP_URL_HOST);
$path = parse_url($selectedDomain, PHP_URL_PATH);


$vhostFile = "/etc/apache2/sites-available/{$host}.conf";
if (!file_exists($vhostFile)) {
    $vhostContent = "
<VirtualHost *:80>
    ServerAdmin  webmaster@{$host}
    ServerName   {$host}
    DocumentRoot /var/www/html/{$host}

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
if (file_exists("/var/www/html/{$host}{$path}")) {
    echo "\033[31m The folder /var/www/html/{$host}{$path} already exists, and the installation has been aborted. \033[0m\n\n\n\n";
    exit;
}

echo "Now I will download the Moodle code. Stay tuned, I'll be back with more options for you shortly:\n\n";

shell_exec("git clone --depth 1 https://github.com/moodle/moodle/ -b {$selectedVersion} /var/www/html/{$host}{$path}");
shell_exec("mkdir -p /var/www/moodledata/{$host}{$path}");
shell_exec("chmod 755 /var/www/moodledata/{$host}{$path}");


echo "The codes are in the folder /var/www/html/{$host}{$path} and now let's move to the database:\n\n";

$dbName = preg_replace('/[^a-z0-9]/i', '', $host); // Sanitize the host to create a database name
if (isset($path[1])) {
    $dbName = "{$dbName}" . preg_replace('/[^a-z0-9]/i', '', $path); // Add sanitized path to the database name if it exists
}
$mysql_info = createMySqlPassword($dbName); // Create a MySQL user and password
if ($mysql_info === false) {
    echo "\033[31m Unable to create a user in the database, aborting \033[0m\n\n\n\n";
    exit; // Abort execution if user creation fails
}


file_put_contents("/var/www/html/{$host}{$path}/config.php",
    "<?php // Moodle configuration file

unset( \$CFG );
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '{$mysql_info['dbtype']}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = 'localhost';
\$CFG->dbname    = '{$dbName}';
\$CFG->dbuser    = '{$dbName}';
\$CFG->dbpass    = '{$mysql_info['senha']}';
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

require_once( __DIR__ . '/lib/setup.php' );");


echo "Now I will install Moodle\n\n\n\n";
shell_exec("php /var/www/html/{$host}{$path}/admin/cli/install_database.php --adminuser=admin --adminpass=Password@123# --fullname=Moodle --shortname=Moodle --agree-license");

echo "\n\n\n\nInstallation completed. Now access it through the browser:\n
    Host:  {$selectedDomain}
    Login: admin
    Password: Password@123#\n\n";

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
        return false;
    }

    $sql_criar_usuario = "CREATE USER '{$dbName}'@'localhost' IDENTIFIED BY '{$passwordAleatoria}'";
    if (!($conn->query($sql_criar_usuario) === TRUE)) {
        return false;
    }

    $sql_grant_usage = "GRANT USAGE ON *.* TO '{$dbName}'@'localhost'";
    if (!($conn->query($sql_grant_usage) === TRUE)) {
        return false;
    }

    $sql_conceder_permissao = "ALTER USER '{$dbName}'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0";
    if (!$conn->query($sql_conceder_permissao) === TRUE) {
        return false;
    }

    $sql_create_database = "CREATE DATABASE `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if (!$conn->query($sql_create_database) === TRUE) {
        return false;
    }

    $sql_grant_privileges = "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbName}'@'localhost'";
    if (!$conn->query($sql_grant_privileges) === TRUE) {
        return false;
    }

    echo "\n\n\n" . $conn->server_info . "\n\n\n";

    $serverInfo = $conn->server_info;

    if (stripos($serverInfo, 'MariaDB') !== false) {
        $dbtype = "mariadb";
    } else {
        $dbtype = "mysqli";
    }

    // Close connection
    $conn->close();

    return [
        "dbtype" => $dbtype,
        "password" => $passwordAleatoria,
    ];
}

function test__max_input_vars() {
    // Verifica o valor atual de max_input_vars
    $currentValue = ini_get('max_input_vars');

    if ($currentValue !== false && $currentValue < 5000) {
        echo "\n\nO valor atual de max_input_vars é $currentValue. Tentando atualizar...\n\n\n";

        // Localiza o arquivo php.ini
        $phpIniFile = php_ini_loaded_file();
        if ($phpIniFile === false) {
            die("Arquivo php.ini não encontrado. Altere e tente novamente.\n");
        }

        // Grava as alterações no arquivo
        file_put_contents($phpIniFile, "\n\nmax_input_vars = 5000\n", FILE_APPEND);
    }
}
