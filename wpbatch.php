<?php

/**
 * Disable time limit
 */
set_time_limit(0);

/**
 * The script is to be executed from CLI only
 */
if (php_sapi_name() !== 'cli') {
    die("Meant to be run from command line");
}

/**
 * Class WPBatch definition on script functions
 */
class WPBatch
{
    /**
     * @property string jsonDefault - defines default package file
     */
    const jsonDefault = 'wordpress.json';

    /**
     * @property array ROUTER - defines default scripts
     */
    const ROUTER = [
        'export' => 'wpExport',
        'default' => 'wpExport',
        'db_dump' => 'wpDbDump',
        'media_dump' => 'archiveMedia',
        'plugins_dump' => 'archivePlugins',
        'themes_dump' => 'archiveTemplates',
        'restore' => 'wpRestore',
    ];

    /**
     * @var WPBatch $wpCli - script class instance
     */
    private static $wpCli = 'wp';

    /**
     * @var WPBatch $inst - script class instance
     */
    private static $inst = null;

    /**
     * @var string|null $script
     */
    private static $script = null;

    /**
     * @var bool $connected - if WP instance was connected to script
     */
    private static $connected = false;

    /**
     * @var array $config - package data
     */
    private static $config = [];

    /**
     * @var array $args - command line arguments
     */
    private static $args = [];

    /**
     * @var array $plugins - plugins installed in connected WP installation
     */
    private static $plugins = [];

    /**
     * @var array $themes - themes installed in connected WP installation
     */
    private static $themes = [];

    /**
     * WPBatch constructor.
     */
    function __construct()
    {
        $argsRaw = $_SERVER['argv'];
        self::$script = array_shift($argsRaw);
        $args = array_map(function ($item) {
            return explode('=', $item);
        }, $argsRaw);
        foreach ($args as $item) {
            self::$args[$item[0]] = $item[1] ?? '';
        }

        define('WP_USE_THEMES', false);
        $this->pathDefine();
        $this->outputDefine();
        $this->inputDefine();

    }

    /**
     * Defines BASE_PATH constant according to environment settings
     * BASE_PATH is WP installation location and used as default value for all
     * other paths
     */
    private function pathDefine()
    {
        $path = self::$args['path'] ?? __DIR__;
        define('BASE_PATH', $path);
    }

    /**
     * Defines OUTPUT_PATH constant according to environment settings
     * OUTPUT_PATH is responsible for data destination location
     */
    private function outputDefine()
    {
        $path = self::$args['output'] ?? (defined('BASE_PATH') ? BASE_PATH : __DIR__);
        define('OUTPUT_PATH', $path);
        if (!file_exists(OUTPUT_PATH)) {
            mkdir(OUTPUT_PATH);
        }
    }

    /**
     * Defines INPUT_PATH constant according to environment settings
     * INPUT_PATH is responsible for package config file
     */
    private function inputDefine()
    {
        $path = self::$args['input'] ?? (defined('BASE_PATH') ? BASE_PATH : __DIR__);
        define('INPUT_PATH', $path);
    }

    /**
     * Defines destination domain
     */
    private function domainDefine()
    {
        $domain = self::$args['domain'] ?? '';
        self::$config['domain'] = $domain;
    }

    /**
     * Return existing or create new WPBatch instance
     * @return WPBatch
     */
    public static function inst()
    {
        if (self::$inst == null) {
            self::$inst = new self;
        }
        return self::$inst;
    }

    /**
     * Saves plugins list to JSON file
     */
    function pluginsDump()
    {
        $plugins = $this->pluginsForDump();
        file_put_contents(OUTPUT_PATH . '/plugins.json', json_encode($plugins));
    }

    /**
     * Prepares plugins list for WP dump
     * @return array
     */
    private function pluginsForDump()
    {
        $output = array_map(function ($item, $key) {

            $src = '';
            $uri = '';

            if (!$item['PluginURI']) {
                $src = $this->archiveCustomPlugin($key);
            } else {
                $uri = $this->getProperUri($item['PluginURI'], explode('/', $key)[0]);
            }

            $args = [
                'name' => $item['Name'],
                'url' => $uri,
                'src' => $src,
                'active' => is_plugin_active($key) ? 'true' : 'false',
                'version' => $item['Version'],
                'wp_version' => $item['RequiresWP'],
                'php_version' => $item['RequiresPHP']
            ];


            if (isset(self::$args['-b'])) {
                unset($args['active']);
            }

            return [
                $args
            ];

        }, self::$plugins, array_keys(self::$plugins));
        return $this->filterDump($output);
    }

    private function archiveCustomPlugin($path)
    {
        $baseUrl = $this->getFormatPath(BASE_PATH);
        $pathArr = explode('/', 'wp-content/plugins/' . $path);
        array_pop($pathArr);
        $path = implode('/', $pathArr);
        $pluginName = array_pop($pathArr);
        if (!file_exists(OUTPUT_PATH . "/custom_plugins/")) {
            mkdir(OUTPUT_PATH . "/custom_plugins/");
        }
        $outputDir = $this->getFormatPath(OUTPUT_PATH);
        $command = "cd {$baseUrl}{$path} && zip -r {$outputDir}custom_plugins/{$pluginName}.zip . && cd -";
        shell_exec($command);
        return "custom_plugins/{$pluginName}.zip";
    }

    private function getFormatPath($path)
    {
        $formatPath = $path;
        if ($formatPath[-1] != '/') {
            $formatPath .= '/';
        }
        return $formatPath;
    }

    /**
     * Gets URL or name for theme or plugin
     * @param $uri - provided URL
     * @param $default - default value
     * @return string
     */
    private function getProperUri($uri, $default)
    {

        $slugs = explode('/', $uri);
        $end = explode('.', array_pop($slugs));
        if (strpos($uri, '//wordpress.org') !== false) {
            $uri = array_pop($slugs);
        } elseif ($uri && isset($end[1]) != 'zip') {
            $uri = $default;
        } else {
            $uri = 'null';
        }
        return $uri;
    }

    /**
     * Filters wrong records from plugins or themes dump array
     * @param $output - array with plugins or themes data
     * @return array
     */
    private function filterDump($output)
    {
        $outputDump = [];
        foreach ($output as $item) {
            $name = $item[0]['name'];
            unset($item[0]['name']);
            $outputDump[$name] = $item[0];
        }
        return $outputDump;
    }

    /**
     * Reads plgins list from JSON file
     * @return mixed
     */
    function readPlugins()
    {
        $plugins = json_decode(file_get_contents(OUTPUT_PATH . '/plugins.json'), true);
        return $plugins;
    }

    /**
     * Execute a script
     */
    function exec()
    {
        foreach (self::ROUTER as $route => $func) {
            if (!isset(self::$args[$route])) {
                continue;
            }
            $this->$func();
            return;
        }
        $func = self::ROUTER['default'];
        $this->$func();
        return;

    }

    /**
     * WordPress restore script. Restores WP installation according to package file
     * and other data (database, media files)
     */
    private function wpRestore()
    {
        echo "Restore procedure launched\r\n";
        $this->jsonRead();
        $this->domainDefine();
        $this->databaseCredentialsDefine();
        $this->adminDefine();
        $this->databaseCreate();
        $this->wpDownload();
        $this->wpConfigCreate();
        if (!isset(self::$config['database']['source'])) {
            $this->wpInstall();
        } else {
            $this->databaseRestore();
        }
        $this->wpConnect();
        $this->domainChange();

        if (!isset(self::$config['themes']['source'])) {
            $this->themesInstall();
        } else {
            $this->themesArchiveExtract();
        }


        if (!isset(self::$config['plugins']['source'])) {
            $this->pluginsInstall();
        } else {
            $this->pluginsArchiveExtract();
        }

        if (!isset(self::$config['database']['source']) && !isset(self::$config['themes']['source']) && !isset(self::$config['plugins']['source'])) {
            $this->themesActivate();
            $this->pluginsActivate();
        }

        if (isset(self::$config['media']['source'])) {
            $this->mediaArchiveExtract();
        }
    }

    /**
     * Reads WP package file
     * @param string $name - JSON file name
     */
    private function jsonRead($name = '')
    {
        $path = self::$args['input'] ?? INPUT_PATH;
        $name = $name ? $name : $path.'/wordpress.json';
        self::$config = json_decode(file_get_contents($name), true);
    }

    /**
     * Defines database credentials according to environment settings
     */
    private function databaseCredentialsDefine()
    {
        if (!self::$config['database']) {
            self::$config['database'] = [];
        }
        self::$config['database']['user'] = self::$args['db_user'] ?? (self::$config['database']['user'] ?? 'root');
        self::$config['database']['password'] = self::$args['db_password'] ?? (self::$config['database']['password'] ?? '');
        self::$config['database']['host'] = self::$args['db_host'] ?? (self::$config['database']['host'] ?? 'localhost');
        self::$config['database']['name'] = self::$args['db_name'] ?? (self::$config['database']['name'] ?? 'wordpress');
        self::$config['database']['source'] = self::$args['db_source'] ?? (self::$config['database']['source'] ?? null);
    }

    /**
     * Defines database credentials according to environment settings
     */
    private function adminDefine()
    {
        if (empty(self::$config['admin'])) {
            self::$config['admin'] = [];
        }
        self::$config['admin']['login'] = self::$args['admin_login'] ?? (self::$config['admin']['login'] ?? 'webadmin');
        self::$config['admin']['password'] = self::$args['admin_password'] ?? (self::$config['admin']['password'] ?? 'dddddddd');
        self::$config['admin']['email'] = self::$args['admin_email'] ?? (self::$config['admin']['email'] ?? 'webadmin@test.com');
    }

    private function databaseCreate()
    {
        $serverName = self::$config['database']['host'];
        $userName = self::$config['database']['user'];
        $password = self::$config['database']['password'] ?? '';
        $dbName = self::$config['database']['name'];

        $db = new mysqli($serverName, $userName, $password);
        if ($db->connect_error) {
            die("Connection failed: " . $db->connect_error);
        }

        $sql = 'DROP DATABASE ' . $dbName;
        $db->query($sql);

        $sql = 'CREATE DATABASE ' . $dbName;

        if ($db->query($sql) === TRUE) {
            echo "Database created successfully";
        } else {
            echo "Error creating database: " . $db->error;
            die();
        }
    }

    /**
     * Downloads WP core according to environment settings
     */
    function wpDownload()
    {
        $command = self::$wpCli . ' core download';
        if (self::$config['version']) {
            $command .= ' --version=' . self::$config['version'];
        }
        if (self::$config['locale']) {
            $command .= ' --locale=' . self::$config['locale'];
        }
        echo $command;
        shell_exec($command);
    }

    /**
     * Creates wp-config.php with provided settings
     */
    function wpConfigCreate()
    {
        $command = self::$wpCli . " config create --dbname=" . self::$config['database']['name'] . ' --dbuser=' . self::$config['database']['user'];
        if (self::$config['database']['password']) {
            $command .= ' --dbpass=' . self::$config['database']['password'];
        }
        if (self::$config['database']['host']) {
            $command .= ' --dbhost=' . self::$config['database']['host'];
        }
        shell_exec($command);
    }

    /**
     * Performs WP installation (WP core should be downloaded and wp-config.php file created first)
     */
    function wpInstall()
    {
        $command = self::setWpCliParam('core install', [
            'url' => self::$config['domain'],
            'title' => self::$config['name'],
            'admin_user' => self::$config['admin']['login'],
            'admin_password' => self::$config['admin']['password'],
            'admin_email' => self::$config['admin']['email'],

        ]);

        shell_exec($command);
    }

    private function setWpCliParam($command, $params)
    {
        $command = self::$wpCli . " {$command} ";
        foreach ($params as $key => $param) {
            if (empty($param)) {
                continue;
            }
            $command .= "--{$key}=\"{$param}\" ";
        }
        return $command;
    }

    /**
     * Restores database from source sql file
     */
    private function databaseRestore()
    {
        $userName = self::$config['database']['user'];
        $password = self::$config['database']['password'] ?? '';
        $dbName = self::$config['database']['name'];
        $dbSource = self::$config['database']['source'];

        $command = "mysql -u " . $userName . " ";
        if ($password) {
            $command .= "-p" . $password . ' ';
        }
        $command .= $dbName . ' < ' . $dbSource;
        shell_exec($command);
    }

    /**
     * Connects to WP instance
     * @return bool
     */
    private function wpConnect()
    {
        global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
        if (!file_exists(BASE_PATH . "/wp-config.php")) {
            die('Provided path does not contain WP installation');
        }
        require(BASE_PATH . '/wp-load.php');
        self::$connected = true;
        return true;
    }

    private function domainChange()
    {
        if (!self::$connected) {
            die('WP instance should be connected to change website domain');
        }
        update_option('siteurl', self::$config['domain']);
        update_option('home', self::$config['domain']);
    }

    /**
     * Installs WP themes to connected WP installation
     */
    function themesInstall()
    {
        $command = self::$wpCli . " theme install " . implode(' ', array_map(function ($item) {
                return $item['url'];
            }, self::$config['themes']));
        shell_exec($command);

        $command = self::$wpCli . " theme install " . implode(' ', array_map(function ($item) {
                return $item['src'];
            }, self::$config['themes']));
        shell_exec($command);
    }

    private function themesArchiveExtract()
    {
        $path = self::$config['themes']['source'];
        $command = "unzip {$path}";
        shell_exec($command);
    }

    /**
     * Installs WP plugins to connected WP installation
     */
    function pluginsInstall()
    {
        $command = self::$wpCli . " plugin install " . implode(' ', array_map(function ($item) {
                return $item['url'];
            }, self::$config['plugins']));
        shell_exec($command);

        $command = self::$wpCli . " plugin install " . implode(' ', array_map(function ($item) {
                return $item['src'];
            }, self::$config['plugins']));
        shell_exec($command);
    }

    private function pluginsArchiveExtract()
    {
        $path = self::$config['plugins']['source'];
        $command = "unzip {$path}";
        shell_exec($command);
    }

    function themesActivate()
    {
        $themeActive = array_filter(self::$config['themes'], function ($item) {
            return $item['active'] == "true";
        });

        $command = self::$wpCli . " theme activate " . $themeActive[array_key_first($themeActive)]['slug'];
        shell_exec($command);
    }

    function pluginsActivate()
    {
        $pluginActive = array_filter(self::$config['plugins'], function ($item) {
            return $item['active'] == "true";
        });

        if (isset($pluginActive[array_key_first($pluginActive)])) {
            $command = self::$wpCli . " plugin activate " . $pluginActive[array_key_first($pluginActive)]['slug'];
            shell_exec($command);
        }
    }

    private function mediaArchiveExtract()
    {
        $path = self::$config['media']['source'];
        $command = "unzip {$path}";
        shell_exec($command);
    }

    private function wpDbDump(){
        echo "Export database dump\r\n";
        $this->wpConnect();
        $this->doTheDump();
        $this->databaseDump();
    }

    /**
     * WordPress export script. Creates package file from WP instance
     * Exports database and media files
     */
    private function wpExport()
    {
        echo "Export procedure launched\r\n";
        $this->wpConnect();
        $this->doTheDump();
        $this->doTheDumpPluginsOrThemes();


        if (isset(self::$args['-b']) || isset(self::$args['-m']) || isset(self::$args['-t']) || isset(self::$args['-p'])) {
            $this->databaseDump();
        }

        if (isset(self::$args['-m'])) {
            $this->archiveMedia();
        }

        if (isset(self::$args['-t'])) {
            $this->archiveTemplates();
        }

        if (isset(self::$args['-p'])) {
            $this->archivePlugins();
        }

        $this->jsonWrite();
    }

    /**
     * Fills script config property with data from WP installation
     */
    private function doTheDump()
    {
        if (!self::$connected) {
            die('WP installation is not provided');
        }
        self::$config['version'] = get_bloginfo('version');
        self::$config['name'] = get_bloginfo('name');
        self::$config['origin'] = get_home_url();
        self::$config['locale'] = determine_locale();
        self::$config['database'] = [
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'name' => DB_NAME,
        ];
    }

    private function doTheDumpPluginsOrThemes()
    {
        if (!isset(self::$args['-p'])) {
            $this->getPluginsList();
            self::$config['plugins'] = $this->pluginsForDump();
        }

        if (!isset(self::$args['-t'])) {
            $this->getThemesList();
            self::$config['themes'] = $this->themesForDump();
        }
    }

    /**
     * Gets plugin list from WP installation
     */
    private function getPluginsList()
    {
        if (!function_exists('get_plugins')) {
            require_once BASE_PATH . '/wp-admin/includes/plugin.php';
        }
        self::$plugins = get_plugins();
    }

    /**
     * Gets themes list from WP installation
     */
    private function getThemesList()
    {
        self::$themes = wp_get_themes();
    }

    /**
     * Prepares themes list for WP dump
     * @return array
     */
    private function themesForDump()
    {
        $theme = wp_get_theme();
        $output = array_map(function ($item, $key) use ($theme) {

            $src = '';
            $uri = '';

            if (!$item->get('ThemeURI')) {
                $src = $this->archiveCustomTheme($key);
            } else {
                $uri = $this->getProperUri($item->get('ThemeURI'), $key);
            }

            $args = [
                'name' => $item->get('Name'),
                'url' => $uri,
                'src' => $src,
                'slug' => $key,
                'active' => $theme->name == $item->get('Name') ? 'true' : 'false',
                'version' => $item->get('Version'),

            ];

            if (isset(self::$args['-b'])) {
                unset($args['active']);
            }

            return [
                $args
            ];

        }, self::$themes, array_keys(self::$themes));
        return $this->filterDump($output);
    }

    private function archiveCustomTheme($themeName)
    {
        $baseUrl = $this->getFormatPath(BASE_PATH);
        $path = 'wp-content/themes/' . $themeName;
        if (!file_exists(OUTPUT_PATH . "/custom_themes/")) {
            mkdir(OUTPUT_PATH . "/custom_themes/");
        }
        $outputDir = $this->getFormatPath(OUTPUT_PATH);
        $command = "cd {$baseUrl}wp-content/themes/ && zip -r {$outputDir}custom_themes/{$themeName}.zip {$themeName} && cd -";
        shell_exec($command);
        return "custom_themes/{$themeName}.zip";
    }

    /**
     * Dumps the database for connected WP instance
     */
    private function databaseDump()
    {
        if (!self::$connected) {
            die('WP installation is not provided');
        }
        if (!$this->databaseIfExists()) {
            die('Specified database does not exist');
        }

        $command = "mysqldump -u " . self::$config['database']['user'] . " ";
        if (self::$config['database']['password']) {
            $command .= "-p" . self::$config['database']['password'] . ' ';
        }
        $command .= self::$config['database']['name'] . ' > ' . OUTPUT_PATH . '/database/' . self::$config['database']['name'] . '.sql';
        if (!file_exists(OUTPUT_PATH . '/database/')) {
            mkdir(OUTPUT_PATH . '/database/');
        }
        shell_exec($command);
        self::$config['database']['source'] = 'database/' . self::$config['database']['name'] . '.sql';

    }

    /**
     * Checks if database exists
     * @param string $name - database name
     * @return bool
     */
    private function databaseIfExists($name = '')
    {
        $name = $name ?: self::$config['database']['name'];
        if (!$name) {
            die('No database name provided');
        }
        global $wpdb;
        $res = $wpdb->get_row("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . self::$config['database']['name'] . "'");
        return $res ? true : false;
    }

    private function archiveMedia()
    {
        $args = [
            'path_to_archive_folder' => 'wp-content/uploads',
            'name_archive_folder' => 'media',
            'archive_name' => 'media.zip',
            'result_section' => "media"
        ];
        $this->createArchive($args);
    }

    private function createArchive($args)
    {
        $defaultDir = $this->getFormatPath(BASE_PATH);

        $pathToArchiveFolder = $args['path_to_archive_folder'];

        if (!is_dir($defaultDir . $pathToArchiveFolder)) {
            echo "Directory {$args['path_to_archive_folder']} not found";
            return;
        }

        if (!file_exists(OUTPUT_PATH . "/{$args['name_archive_folder']}/")) {
            mkdir(OUTPUT_PATH . "/{$args['name_archive_folder']}/");
        }
        $outputDir = $this->getFormatPath(OUTPUT_PATH);

        $command = "cd {$defaultDir} && zip -r {$outputDir}{$args['name_archive_folder']}/{$args['archive_name']} {$pathToArchiveFolder} && cd -";
        shell_exec($command);
        self::$config[$args['result_section']]['source'] = "{$args['name_archive_folder']}/{$args['archive_name']}";
    }

    private function archiveTemplates()
    {
        $args = [
            'path_to_archive_folder' => 'wp-content/themes',
            'name_archive_folder' => 'themes',
            'archive_name' => 'themes.zip',
            'result_section' => "themes"

        ];
        $this->createArchive($args);
    }

    private function archivePlugins()
    {
        $args = [
            'path_to_archive_folder' => 'wp-content/plugins',
            'name_archive_folder' => 'plugins',
            'archive_name' => 'plugins.zip',
            'result_section' => "plugins"
        ];
        $this->createArchive($args);
    }

    /**
     * Writes package file
     * @param string $name - JSON file name
     * @param array $data - WP package data
     */
    private function jsonWrite($name = '', $data = [])
    {
        $name = $name ? $name : (self::$args['input'] ?? self::jsonDefault);
        $data = $data ?: self::$config;
        file_put_contents(OUTPUT_PATH . "/$name", json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * deprecated
     * WordPress dump script. Creates package file from WP instance
     */
    private function wpDump()
    {
        echo "Dump procedure launched\r\n";
        $this->wpConnect();
        $this->doTheDump();
        $this->doTheDumpPluginsOrThemes();
        $this->jsonWrite();
    }

    /**
     * Patches JSON file with admin data
     */
    private function jsonPatchAdmin()
    {
        $this->jsonRead();
        $this->adminDefine();
        $this->jsonWrite();
    }

    /**
     * Patches JSON file with database data
     */
    private function jsonPatchDatabase()
    {
        $this->jsonRead();
        $this->databaseCredentialsDefine();
        $this->jsonWrite();
    }
}

/**
 * Launch the script
 */
$wpb = WPBatch::inst();
$wpb->exec();
