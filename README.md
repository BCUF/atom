# Changes for Active Directory
The changes list below are all step needed/file changes to do to enable active directory and installing with Docker (without compose).

## Step to install
1. cd path_to_project
2. cd docker
3. chmod +x ./entrypoint.sh
4. (Be care) entrypoint.sh must not have CRLF line-ending
    - (optional) file entrypoint.sh
    - vi entrypoint.sh
    - :set ff=unix
    - :wq

**Note:** Change the password for percona root
```
docker build --tag m_atom:1.0 ..
docker run -d -v "composer_deps:/atom/src/vendor/composer" -v "/$(pwd)/..:/atom/src:rw" --env-file etc/environment --network atom_net --name atom m_atom:1.0
docker run -d -e MYSQL_ROOT_PASSWORD=secretpassword -v "percona_data:/var/lib/mysql:rw" -v "/$(pwd)/etc/mysql/mysqld.cnf:/etc/my.cnf.d/mysqld.cnf:ro" -p 127.0.0.1:63003:3306 --env-file etc/environment --network atom_net --name percona percona:8.0
docker run -d -p 127.0.0.1:63005:4730 --network atom_net --name gearmand artefactual/gearmand
docker run -d -p 127.0.0.1:63004:11211 --network atom_net --name memcached memcached "-p 11211 -m 128 -u memcache"
docker run -d -v "/$(pwd)/..:/atom/src:r"o -v "/$(pwd)/etc/nginx/nginx.conf:/etc/nginx/nginx.conf:ro" -p 63001:80 --network atom_net --name nginx nginx:latest
docker run -d -v "elasticsearch_data:/usr/share/elasticsearch/data" -p 127.0.0.1:63002:9200 -e "discovery.type=single-node" --env-file etc/environment --ulimit "memlock=-1:-1" --network atom_net --name elasticsearch docker.elastic.co/elasticsearch/elasticsearch:5.6.16
docker exec atom php symfony tools:purge --demo
docker exec atom make -C plugins/arDominionPlugin
docker run -d -v "composer_deps:/atom/src/vendor/composer" -v "/$(pwd)/..:/atom/src:rw" --env-file etc/environment --restart on-failure:5 --network atom_net --name atom_worker m_atom:1.0 worker
```

### Dump db (From 2.4 to 2.6)
[Upgrading](https://www.accesstomemory.org/en/docs/2.6/admin-manual/installation/upgrading/)

[Intallation requirements](https://www.accesstomemory.org/en/docs/2.6/admin-manual/installation/requirements/#installation-requirements)

Change the dump from 2.4 version to be compatible wth 2.6 version: 
```
Remove NO_AUTO_CREATE_USER in sql dump --> powershell -Command "(Get-Content old_sql_file.sql) -replace 'NO_AUTO_CREATE_USER', '' | Out-File -encoding utf8 new_sql_file.sql"
```

Go into percona container with command line tool and prepare the database:
```
docker exec -it percona bash
mysql -u root -p -e "SET GLOBAL log_bin_trust_function_creators = 1;"
mysql -u root -p -e "DROP DATABASE IF EXISTS atom;"
mysql -u root -p -e "CREATE DATABASE atom CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
```

Dump the data from outside the container (ctrl+p -> ctrl+q to leave bash's container)

**Note:** Change the password for percona root
```
gunzip < /home/user/dev/myFile.sql.gz | sudo docker exec -i percona mysql -uroot -psecretpassword atom
```

Go into atom container with command line tool and upgrade the database:

**Note:** Can take a lot of time depending on the data
```
sudo docker exec -it atom bash
cd /atom/src
php -d memory_limit=-1 symfony tools:upgrade-sql
php -d memory_limit=-1 symfony search:populate
```

## Files changed
### Changed
- .gitignore
- Dockerfile
- README.md
- apps/qubit/modules/settings/actions/menuComponent.class.php
- apps/qubit/modules/user/actions/loginAction.class.php
- composer.json
- config/factories.yml

### Created
- apps/qubit/modules/settings/actions/adAction.class.php
- apps/qubit/modules/settings/actions/updateAdUserAction.class.php
- apps/qubit/modules/settings/templates/adSuccess.php
- apps/qubit/modules/settings/templates/updateAdUserSuccess.php
- lib/adUser.class.php
- config/bcu_config.ini --> File to add with username= and password=

**Note:** If you didn't install directly this version of atom, instead, you only modified the files above, you must restart service and clean the cache.
```
php symfony cc
sudo systemctl restart php7.2-fpm
sudo systemctl restart memcached
```

## How does it work
There are two parts of this add-on. One is at **login** and the second in the **settings**.

### Login
Once the ADUser activated, you know can log with both the credential from Atom and Active Directory.
1. The system checks if the user exist in the LDAP and provide the good password
2. The system checks if the user is in one of the two groups specified in the settings
3. The user is created/logged when in one group.
4. If not logged after the LDAP check, the system fallthrough the original log in. (mail/password)

### Settings
In the settings, there are 9 options :
- Protocol: ldap/ldaps
- Hostname: example.com
- Port: 389/636
- Base DN: dc=example,dc=com
- Atom read only group (Select)
- AD read only group: CN=MY_GRP_R,OU=XXX,DC=example,DC=COM
- Atom read/write group (Select)
- AD read/write group: CN=MY_GRP_W,OU=XXX,DC=example,DC=COM
- Archived group for users with notice (Select)

You can update the users with these settings with the buttons **Update LDAP users**.
1. Get the lists of users in both Atom groups (only if atom and ldap group set)
2. Get the lists of users in both LDAP groups (only if atom and ldap group set)
3. Compare both lists
   - Users in Atom and LDAP lists are updated/kept
   - Users only in LDAP lists are created
   - Users only in Atom lists are:
     - put in the archived group (if set) if they have at least one notes and disable the user
     - updated if administrator
     - deleted


## [Access to Memory](https://www.accesstomemory.org)

Developed and maintained by [Artefactual Systems](https://www.artefactual.com/)

AtoM (short for Access to Memory) is a web-based, open source application for
standards-based archival description and access. The application is
multilingual and multi-repository. First commissioned by the International
Council on Archives ([ICA](https://www.ica.org)) to make it easier for
archival institutions worldwide to put their holdings online using the ICA’s
descriptive standards, the project has since grown into an internationally
used community-driven project. Learn more at:

* https://www.accesstomemory.org

You are free to copy, modify, and distribute AtoM with attribution under the
terms of the AGPLv3 license. See the [LICENSE](LICENSE) file for details.


## Change 1: Have a hierarchical structure with original name
### Files changed
- plugins/qtSwordPlugin/lib/qtPackageExtractorMETSArchivematicaDIP.class.php
- lib/QubitMetsParser.class.php

## Installation

**Production installation**

AtoM is intended to be installed using a Linux-based operating system. We use
Ubuntu LTS releases in development and testing, but users have successfully
installed on other distributions as well.

* [Linux installation guides](https://www.accesstomemory.org/docs/latest/admin-manual/installation/linux/linux/)

For other O/S installs, we recommend virtualization.

**Development environments**

If you want to install a local copy of AtoM for testing and/or development, we
maintain two development environments:

* [Docker](https://www.accesstomemory.org/docs/latest/dev-manual/env/compose/)
* [Vagrant](https://www.accesstomemory.org/docs/latest/dev-manual/env/vagrant/)

## Other resources

* [Website](https://www.accesstomemory.org) - the home of the AtoM project!
* [Documentation](https://www.accesstomemory.org/docs/latest/) - where you'll
  find our User, Administrator, and Developer manuals. We version our manuals
  for each major release.
* [Wiki](https://wiki.accesstomemory.org/) - community and project resources,
  development documentation, release notes, and more.
* [User Forum](https://groups.google.com/forum/#!forum/ica-atom-users) - Forum
  and mailling list for user questions (both technical and end-user),
  discussion, and more.
* [SlideShare](https://www.slideshare.net/accesstomemory) - where we upload
  all the slide decks from our conference presentations and training camps!
* [Paid support](https://www.artefactual.com/services/): Paid support,
  hosting, training, theming, data migrations, consulting, and software
  development contracts from Artefactual.

## Contributing

Thank you for your interest in contributing to the AtoM project! 

Please see our [contributing guidelines](CONTRIBUTING.md) file for more information. 
