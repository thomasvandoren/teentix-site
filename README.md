TeenTix website
===============

The http://teentix.org/ website.

Developer setup
---------------

These instructions are pretty specific to the Mac OS X platform. Similar
prerequisites will be required for other platforms like linux, but the specific
instructions may vary.

### Install software

* Install Xcode from the Mac App Store: https://itunes.apple.com/us/app/xcode/id497799835?mt=12

* When it is done installing, open Xcode and agree to the license.

* Install [Homebrew](http://brew.sh/).

* Install percona-server, which is a high performance mysql server.

```bash
brew install percona-server
```

* Install homebrew services, if it isn't already, and have it configure
  percona-server to run automatically.

```bash
brew tap homebrew/services
brew services start percona-server
```

* Check for the `/var/mysql/mysql.sock` file. If it does not exist, and a
  `/tmp/mysql.sock` file does exist, create this symlink:

```bash
sudo mkdir -p /var/mysql &&
  sudo ln -sv /tmp/mysql.sock /var/mysql/mysql.sock
```

* Check for this line in your `/etc/my.cnf` file:

```
sql_mode=NO_ENGINE_SUBSTITUTION,STRICT_TRANS_TABLES 
```

* If that line is there, remove the `STRICT_TRANS_TABLES` part so it looks like:

```
sql_mode=NO_ENGINE_SUBSTITUTION
```

### Setup workspace

* Create a workspace directory and clone the [teentix/site][repo] repository
  into it.

```bash
mkdir -pv teentix-site
cd teentix-site
git clone git@github.com:teentix/site public_html
```

[repo]: https://github.com/teentix/site

* Download teentix-site-config.zip from the [TeenTix Site Data][drivedir]
  google drive folder. Extract it to the workspace (make sure you are still in
  the teentix-site dir).

```bash
unzip path/to/teentix-site-config.zip
```

* Check that it unzipped the archive and created a config folder with the
  following contents:

```bash
$ ls config/
config.dev.php          config.local.php        config.prod.php
config.env.php          config.master.php       config.stage.php
```

* Download teentix-site-images.zip from the [TeenTix Site Data][drivedir] google drive folder. Extract it to the public_html dir.

```bash
cd public_html
unzip path/to/teentix-site-images.zip
```

* Check that there is now an images folder in public_html with a bunch of other
  directories:

```bash
$ ls images/
ads                     member_photos
avatars                 partners
blog                    pm_attachments
captchas                remote
index.html              signature_attachments
locations               smileys
made                    uploads
```

* Create the cache directory for expressionengine. Stay inside the public_html
  directory to do it.

```bash
mkdir -p ttadmin/expressionengine/cache &&
  chmod 0777 ttadmin/expressionengine/cache
```

[drivedir]: https://drive.google.com/drive/u/0/folders/0BzNmvIuHmoknWkRFRkZZVHQ2NlE

### Mysql database setup

* Download the teentix-db.sql.zip file from the [TeenTix Site Data][drivedir]
  google drive folder. This file contains the table definitions and some data
  that is useful while developing. Decompress the file.

```bash
unzip path/to/teentix-db.sql.zip
```

* Ensure a teentix-db.sql file exists.

```bash
$ file teentix-db.sql
teentix-db.sql: ASCII text, with very long lines
```

* Create a mysql teentix database (login as root user: `mysql -u root`):

```mysql
create database if not exists teentix
character set utf8 collate utf8_general_ci;
```

* Add a teentix user and give it a simple password (use teentix_pass for
  simplicity):

```mysql
grant all privileges on `teentix`.*
to 'teentix_user'@'localhost' identified by 'teentix_pass';
```

* Use it to populate your new teentix database. **NOTE**: this might take a
  minute or two depending on your system.

```mysql
use teentix;
source teentix-db.sql;
```

### Apache webserver setup

These instructions were adapted from [here][apache1] and [here][apache2].

[apache1]: http://coolestguidesontheplanet.com/get-apache-mysql-php-and-phpmyadmin-working-on-osx-10-11-el-capitan/
[apache2]: http://jason.pureconcepts.net/2015/10/install-apache-php-mysql-mac-os-x-el-capitan/

* Ensure apache is started.

```bash
sudo apachectl start
```

* Edit the apache config at `/etc/apache2/httpd.conf` as root. **NOTE**: you
  may want to save a copy in case it accidentally gets corrupted.

```bash
sudo cp /etc/apache2/httpd.conf /etc/apache2/httpd.conf.bak
sudo nano /etc/apache2/httpd.conf
```

* Uncomment the following lines (remove the `#` sign) and save (CTRL+O) and
  exit (CTRL+X):

```
LoadModule rewrite_module libexec/apache2/mod_rewrite.so
LoadModule php5_module libexec/apache2/libphp5.so
```

* Restart apache and make sure everything is still working, by navigating to
  [http://localhost/](http://localhost/) .

```bash
sudo apachectl restart
```

* Download the php.ini file from the [TeenTix Site Data][drivedir] google drive
  folder. Copy it to your public_html directory.

```bash
cp path/to/php.ini public_html/php.ini
```

* Download the teentix.conf file from the [TeenTix Site Data][drivedir] google
  drive folder.

* Update the teentix.conf file with the path to your workspace. There are five
  (5) lines in teentix.conf that need to be changed. For example, if your
  teentix-site directory is at `/Users/johncage/teentix-site`, you would change
  all instances of `/Users/thomas/src/teentix-site` to
  `/Users/johncage/teentix-site`. You can find the full path by running `pwd`
  from inside the teentix-site directory.

* Copy the teentix.conf file to the `/etc/apache2/other/` directory.

```bash
sudo cp teentix.conf /etc/apache2/other/teentix.conf
```

* Restart apache once more:

```bash
sudo apachectl restart
```

* The TeenTix site will be running at: [http://localhost:9000/](http://localhost:9000/)

* You can access the admin console at: [http://localhost:9000/admin.php](http://localhost:9000/admin.php)

### ExpressionEngine setup

* Make sure the webservice add on is installed.

* Create an API User for the webservice add on. Go to Webservice > Overview > Add API User.

* Ask a dev, like Thomas, for the API key.
