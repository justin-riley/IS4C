#/usr/bin/env sh
set -e

mysql -u root -e "DROP DATABASE IF EXISTS unit_test_op;"
mysql -u root -e "DROP DATABASE IF EXISTS unit_test_trans;"
mysql -u root -e "DROP DATABASE IF EXISTS unit_test_archive;"
mysql -u root -e "DROP DATABASE IF EXISTS unit_test_laneop;"
mysql -u root -e "DROP DATABASE IF EXISTS unit_test_lanetrans;"

# create Fannie databases
mysql -u root -e "CREATE DATABASE unit_test_op;"
mysql -u root -e "CREATE DATABASE unit_test_trans;"
mysql -u root -e "CREATE DATABASE unit_test_archive;"
mysql -u root -e "CREATE DATABASE unit_test_laneop;"
mysql -u root -e "CREATE DATABASE unit_test_lanetrans;"

sudo -u postgres psql -c "CREATE ROLE corepos WITH LOGIN CREATEDB PASSWORD 'corepos'"
sudo -u postgres createdb -O corepos corepos
sudo -u postgres psql -c "CREATE SCHEMA IF NOT EXISTS unit_test_op AUTHORIZATION corepos"
sudo -u postgres psql -c "CREATE SCHEMA IF NOT EXISTS unit_test_trans AUTHORIZATION corepos"
sudo -u postgres psql -c "CREATE SCHEMA IF NOT EXISTS unit_test_archive AUTHORIZATION corepos"
sudo -u postgres psql -c "CREATE SCHEMA IF NOT EXISTS unit_test_laneop AUTHORIZATION corepos"
sudo -u postgres psql -c "CREATE SCHEMA IF NOT EXISTS unit_test_lanetrans AUTHORIZATION corepos"

# create default configuration file
cp fannie/config.php.dist fannie/config.php
# add path options
echo "\$FANNIE_ROOT = '"`pwd`"/fannie/';" >> fannie/config.php
echo "\$FANNIE_URL = '/fannie/';" >> fannie/config.php
# add database options
echo "\$FANNIE_SERVER = '127.0.0.1';" >> fannie/config.php
echo "\$FANNIE_SERVER_USER = 'root';" >> fannie/config.php
if [ "$DB_DRIVER" = "postgres9" ]; then
    echo "\$FANNIE_SERVER_PW = 'corepos';" >> fannie/config.php
else
    echo "\$FANNIE_SERVER_PW = '';" >> fannie/config.php
fi
echo "\$FANNIE_OP_DB = 'unit_test_op';" >> fannie/config.php
echo "\$FANNIE_TRANS_DB = 'unit_test_trans';" >> fannie/config.php
echo "\$FANNIE_ARCHIVE_DB = 'unit_test_archive';" >> fannie/config.php
echo "\$FANNIE_ARCHIVE_METHOD = 'partitions';" >> fannie/config.php
echo "\$FANNIE_LANES = array();" >> fannie/config.php
# set database driver based on environment variable
echo "\$FANNIE_SERVER_DBMS = '$DB_DRIVER';" >> fannie/config.php
echo "\$FANNIE_STORE_ID = '1';" >> fannie/config.php
echo "\$FANNIE_LANES = array(array('host'=>'127.0.0.1','type'=>'$DB_DRIVER','user'=>'root','pw'=>'','op'=>'unit_test_laneop','trans'=>'unit_test_lanetrans','offline'=>0));" >> fannie/config.php

