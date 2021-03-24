[Return to index](../readme.md)

##Batch file options
JSON file, ``wordpress.json`` by default, is a core file storing WP installation status. It stores website name,
WP version, WP locale, database file link, installed plugins and themes.

The list for available options is available below.

**"version"** `string` - WP version. Added on WP data export and used on WP restore

**"locale"** `string` - WP locale. Added on WP data export and used for WP install script

**"database"** `object` - database data. Added on WP export if ``remote`` option is enabled or can be added on
WP restore script

**"plugins"** `array` - installed plugins

**"themes"** `array`- installed themes

**"scripts"** `array` - different behavior scenarios which can be implemented on wpbatch call

**"options"** `array` - options for restore flow control 
