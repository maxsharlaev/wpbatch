[Return to index](../readme.md)

#Command line parameters

###Common usage
```
php wpbatch.php [mode] [options]
```

##Usage examples

Export WP installation from a current directory:
```
php wpbatch export
```

Restore WP installation from a current directory:
```
php wpbatch restore
```

##Command line parameters

`mode` - batch scenario. Predefined in batch utility or in `scripts` section of [bach](json-parameters.md). If two or more modes
specified only the one with the highest priority will be executed

**Default modes are:**
- export
- install
- restore


`path` - source path. Required to be WordPress installation directory. Used in export or backup operations. Equals
  to current by default

`output` - destination path. Equals to `path` or current dir in no `path` specified

`input` - batch file location. Equals to `path` or current dir in no `path` specified

`mysql_path` - mysql and mysqldump path. Empty be default and needed when these commands can't be executed without path
  reference

`wp_cli` - wp-cli path and command. Equals to `wp` by default

`db_user`, `db_name`, `db_password`, `db_host` - a set of parameters for DB connection. Used in restore or install commands.

`admin_login`, `admin_password`, `admin_email` - a set of parameters used for WordPress install
