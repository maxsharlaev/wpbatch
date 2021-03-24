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
        'restore' => 'wpRestore',
        'db_dump' => 'databaseDump',
        'db_restore' => 'databaseRestore',
        'default' => 'wpDump'
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
        $this->domainDefine();

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
        self::$config['database']['source'] = self::$args['db_source'] ?? (self::$config['database']['source'] ?? (INPUT_PATH.'/database/'.self::$config['database']['name'].'.sql'));
    }

    /**
     * Defines database credentials according to environment settings
     */
    private function adminDefine()
    {
        if (!self::$config['admin']) {
            self::$config['admin'] = [];
        }
        self::$config['admin']['login'] = self::$args['admin_login'] ?? (self::$config['admin']['login'] ?? 'webadmin');
        self::$config['admin']['password'] = self::$args['admin_password'] ?? (self::$config['admin']['password'] ?? 'dddddddd');
        self::$config['admin']['email'] = self::$args['admin_email'] ?? (self::$config['admin']['email'] ?? 'webadmin@test.com');
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

    /**
     * WordPress restore script. Restores WP installation according to package file
     * and other data (database, media files)
     */
    private function wpRestore()
    {
        echo "Restore procedure launched\r\n";
        $this->jsonRead();
        $this->databaseCredentialsDefine();
        $this->adminDefine();
        $this->databaseCreate();
        $this->wpDownload();
        $this->wpConfigCreate();
        $this->wpInstall();
        $this->domainChange();
        $this->themesInstall();
        $this->pluginsInstall();
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
        $this->jsonWrite();
        $this->databaseDump();

        self::$config['database']['source'] = 'database/database.sql';

        $this->jsonWrite();
    }

    /**
     * WordPress dump script. Creates package file from WP instance
     */
    private function wpDump()
    {
        echo "Dump procedure launched\r\n";
        $this->wpConnect();
        $this->doTheDump();
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
        $this->getPlugins();
        $this->getThemes();
        self::$config['version'] = get_bloginfo('version');
        self::$config['name'] = get_bloginfo('name');
        self::$config['origin'] = get_home_url();
        self::$config['locale'] = determine_locale();
        self::$config['database'] = [
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'name' => DB_NAME,
            'source' => ''
        ];
        self::$config['plugins'] = $this->pluginsForDump();
        self::$config['themes'] = $this->themesForDump();
    }

    /**
     * Reads WP package file
     * @param string $name - JSON file name
     */
    private function jsonRead($name = '')
    {
        $name = $name ? $name : ( self::$args['input'] ?? self::jsonDefault );
        self::$config = json_decode(file_get_contents(INPUT_PATH."/$name"), true);
    }

    /**
     * Writes package file
     * @param string $name - JSON file name
     * @param array $data - WP package data
     */
    private function jsonWrite($name = '', $data = [])
    {
        $name = $name ? $name : ( self::$args['input'] ?? self::jsonDefault );
        $data = $data ?: self::$config;
        file_put_contents(OUTPUT_PATH."/$name", json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Patches JSON file with admin data
     */
    private function jsonPatchAdmin() {
        $this->jsonRead();
        $this->adminDefine();
        $this->jsonWrite();
    }

    /**
     * Patches JSON file with database data
     */
    private function jsonPatchDatabase() {
        $this->jsonRead();
        $this->databaseCredentialsDefine();
        $this->jsonWrite();
    }

    /**
     * Gets plugin list from WP installation
     */
    private function getPlugins()
    {
        if (!function_exists('get_plugins')) {
            require_once BASE_PATH . '/wp-admin/includes/plugin.php';
        }
        self::$plugins = get_plugins();
    }

    /**
     * Gets themes list from WP installation
     */
    private function getThemes()
    {
        self::$themes = wp_get_themes();
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
        } elseif ($uri && $end[1] != 'zip') {
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
        return array_filter($outputDump, function ($item) { return $item['url'] !== 'null'; });
    }

    /**
     * Prepares plugins list for WP dump
     * @return array
     */
    private function pluginsForDump()
    {
        $output = array_map(function ($item, $key) {
            $uri = $this->getProperUri($item['PluginURI'], explode('/', $key)[0]);
            return [
                [
                    'name' => $item['Name'],
                    'url' => $uri,
                    'active' => is_plugin_active($key) ? 'true' : 'false',
                    'version' => $item['Version'],
                    'wp_version' => $item['RequiresWP'],
                    'php_version' => $item['RequiresPHP']
                ]
            ];
        }, self::$plugins, array_keys(self::$plugins));
        return $this->filterDump($output);
    }

    /**
     * Prepares themes list for WP dump
     * @return array
     */
    private function themesForDump()
    {
        $theme = wp_get_theme();
        $output = array_map(function ($item, $key) use ($theme) {
            $uri = $this->getProperUri($item->get('ThemeURI'), $key);
            return [
                [
                    'name' => $item->get('Name'),
                    'url' => $uri,
                    'slug' => $key,
                    'active' => $theme->name == $item->get('Name') ? 'true' : 'false',
                    'version' => $item->get('Version'),
                ]
            ];
        }, self::$themes, array_keys(self::$themes));
        return $this->filterDump($output);
    }

    /**
     * Saves plugins list to JSON file
     */
    function pluginsDump()
    {
        $plugins = $this->pluginsForDump();
        file_put_contents(OUTPUT_PATH .'/plugins.json', json_encode($plugins));
    }

    /**
     * Reads plgins list from JSON file
     * @return mixed
     */
    function readPlugins()
    {
        $plugins = json_decode(file_get_contents(OUTPUT_PATH.'/plugins.json'), true);
        return $plugins;
    }

    /**
     * Downloads WP core according to environment settings
     */
    function wpDownload()
    {
        $command = self::$wpCli . ' core download';
        if (self::$config['version']) {
            $command .= ' --version='.self::$config['version'];
        }
        if (self::$config['locale']) {
            $command .= ' --locale='.self::$config['locale'];
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
        $command = self::$wpCli . " core install";
        if (self::$config['domain']) {
            $command .= ' --url=' . self::$config['domain'];
        }
        if (self::$config['name']) {
            $command .= ' --title=' . self::$config['name'];
        }
        if (self::$config['admin']['login']) {
            $command .= ' --admin_user=' . self::$config['admin']['login'];
        }
        if (self::$config['admin']['password']) {
            $command .= ' --admin_password=' . self::$config['admin']['password'];
        }
        if (self::$config['admin']['email']) {
            $command .= ' --admin_email=' . self::$config['admin']['email'];
        }

        shell_exec($command);
    }

    /**
     * Installs WP plugins to connected WP installation
     */
    function pluginsInstall()
    {
        $active_plugins = array_filter( self::$plugins, function ($item) { return $item['active'] == 'true'; } );
        $inactive_plugins = array_filter( self::$plugins, function ($item) { return $item['active'] == 'false'; } );

        $command = self::$wpCli . " plugin install " . implode(' ', array_map( function ($item) { return $item['url']; }, $inactive_plugins) );
        shell_exec($command);

        $command = self::$wpCli . " plugin install " . implode(' ', array_map( function ($item) { return $item['url']; }, $active_plugins) ) . ' --activate';
        shell_exec($command);
    }

    /**
     * Installs WP themes to connected WP installation
     */
    function themesInstall()
    {
        $themeActive = array_filter( self::$themes, function ($item) { return $item['active'] == 'true'; } )[0];

        $command = self::$wpCli . " plugin install " . implode(' ', array_map( function ($item) { return $item['url']; }, self::$themes) );
        shell_exec($command);

        $command = self::$wpCli . " theme activate " . $themeActive['slug'];
        shell_exec($command);
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

    /**
     * Creates a database
     * @param false $conditional - initiates conditional create (adds IF NO EXISTS to DB create script)
     * @return mixed
     */
    private function databaseCreate($conditional = false)
    {
        $name = self::$config['database']['name'];
        $command = 'CREATE DATABASE ';
        if ($conditional) {
            $command .= 'IF NOT EXISTS ';
        }
        $command .= $name;
        global $wpdb;
        return $wpdb->query($command);
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
            $command .= "-p".self::$config['database']['password'].' ';
        }
        $command .= self::$config['database']['name'] . ' > ' . OUTPUT_PATH . '/database/' . self::$config['database']['name'] . '.sql';
        if (!file_exists(OUTPUT_PATH . '/database/')) {
            mkdir(OUTPUT_PATH . '/database/');
        }
        shell_exec($command);
    }

    /**
     * Restores database from source sql file
     */
    private function databaseRestore()
    {
        $this->jsonRead();
        $this->databaseCredentialsDefine();
        $this->databaseCreate();

        $command = "mysql -u " . self::$config['database']['user'] . " ";
        if (self::$config['database']['password']) {
            $command .= "-p".self::$config['database']['password'].' ';
        }
        $command .= self::$config['database']['name'] . ' < ' . self::$config['database']['source'];
        shell_exec($command);
    }
    private function domainChange() {
        if (!self::$connected) {
            die('WP instance should be connected to change website domain');
        }
        option_update('siteurl', self::$config['domain']);
        option_update('home', self::$config['domain']);
    }

    /**
     * Execute a script
     */
    function exec() {
        foreach (self::ROUTER as $route => $func) {
            if ($route != 'default' && !isset(self::$args[$route])) {
                continue;
            }
            $this->$func();
            break;
        }

    }
}

/**
 * Launch the script
 */
$wpb = WPBatch::inst();
$wpb->exec();
