# RocksDB
MyRocks is a storage engine that adds the RocksDB database to MariaDB. RocksDB is an LSM database with a great compression ratio that is optimized for flash storage.

The storage engine must be installed before it can be used.

The MyRocks storage engine can also be installed via a package manager on Linux. In order to do so, your system needs to be configured to install from one of the MariaDB repositories.

Installing with yum/dnf **On RHEL, CentOS, Fedora**, and other similar Linux distributions

```sudo yum install MariaDB-rocksdb-engine```

Installing with apt-get **On Debian, Ubuntu**, and other similar Linux distributions

```sudo apt-get install mariadb-plugin-rocksdb```

Installing with zypper **On SLES, OpenSUSE**, and other similar Linux distributions

```sudo zypper install MariaDB-rocksdb-engine```

# Install Plugin
Once the shared library is in place, the plugin is not actually installed by MariaDB by default. You can install the plugin dynamically by executing **INSTALL SONAME or INSTALL PLUGIN**. For example:

```INSTALL SONAME 'ha_rocksdb';```

Now you can configure your tables with RockdDB Engine

# Uninstalling the Plugin

You can uninstall the plugin dynamically by executing **UNINSTALL SONAME or UNINSTALL PLUGIN**. For example:

```UNINSTALL SONAME 'ha_rocksdb';```

# Migrate from InnoDB to RocksDB

1. Enable bulk load

```set rocksdb_bulk_load=1;```

2. Change table engine

```ALTER TABLE database_name.table_name ENGINE = ROCKSDB;```

3. Disable bulk load

```set rocksdb_bulk_load=0;```
