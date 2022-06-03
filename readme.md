#WordPress batch utility
The utility performs export and restore operations for WordPress installations.

The result of export is JSON file (batch) and a set of files which can be used for
easy WP deploy via command line.

Import works with a previously exported batch and can replicate WP installation
with all data and plugins you had.

**Key features**
- Export current installation status to JSON file
- Automatic themes and plugins install
- WordPress install
- Database import and export
- Media import and export `planned`
- WP backup `planned`
- Export backups to different data providers (FTP, Google Drive, S3) `planned`

**Warnings and limitations**

- This utility requires `shell_exec` to be permitted on the host for WP data export or WP restore and install
- `mysqldump` should be installed for data export
- `wp-cli` should be installed for WP, themes and plugins install

**Usage example**

```
php wpbatch.php mode [parameters]
```

**More docs:**

- [Command line parameters](docs/command-line-options.md)
- [JSON file structure](docs/json-parameters.md)



##Database params

##Plugins

##Themes

##Scripts

Scripts are used for different batch scenarios. Mandatory scripts are:

- `default` - wpbatch flow if launched with no parameters
- `export` - export all WP installation data
- `restore` - WP restore flow

###Sample:
```json
{
    "default": [
        "batch restore"
    ],
    "export": [
        "batch export",
        "mysqldump"
    ],
    "restore": [
        "batch restore",
        "mysqldump restore"
    ]
}
```


##Phar compile

```
./box build -v
```
