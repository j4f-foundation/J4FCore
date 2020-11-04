<?php
// Copyright 2018 MaTaXeToS
// Copyright 2019-2020 The Just4Fun Authors
// This file is part of the J4FCore library.
//
// The J4FCore library is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// The J4FCore library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with the J4FCore library. If not, see <http://www.gnu.org/licenses/>.


$dbversion = (isset($_CONFIG['dbversion'])) ? intval($_CONFIG['dbversion']):0;
if ($dbversion == 0) {

	$db->db->query("
	CREATE TABLE `blocks` (
		`height` int(200) unsigned NOT NULL,
		`block_previous` varchar(128) DEFAULT NULL,
		`block_hash` varchar(128) NOT NULL,
		`root_merkle` varchar(128) NOT NULL,
		`nonce` varchar(200) NOT NULL,
		`timestamp_start_miner` varchar(12) NOT NULL,
		`timestamp_end_miner` varchar(12) NOT NULL,
		`difficulty` varchar(255) NOT NULL,
		`version` varchar(10) NOT NULL,
		`info` text NOT NULL,
		PRIMARY KEY (`height`,`block_hash`),
		UNIQUE KEY `bHash` (`block_hash`) USING BTREE,
		UNIQUE KEY `bPreviousHash` (`block_previous`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `config` (
		`cfg` varchar(200) NOT NULL,
		`val` varchar(200) NOT NULL,
		PRIMARY KEY (`cfg`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `peers` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`ip` varchar(120) NOT NULL,
		`port` varchar(8) NOT NULL,
		`blacklist` varchar(12) DEFAULT NULL,
		PRIMARY KEY (`id`),
		KEY `ip` (`ip`)
	) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `transactions` (
		`txn_hash` varchar(128) NOT NULL,
		`block_hash` varchar(128) NOT NULL,
		`wallet_from_key` longtext,
		`wallet_from` varchar(64) DEFAULT NULL,
		`wallet_to` varchar(64) NOT NULL,
		`amount` varchar(64) NOT NULL,
		`signature` longtext NOT NULL,
		`timestamp` varchar(12) NOT NULL,
		`version` varchar(15) NOT NULL DEFAULT '0.0.1',
  	    `gasLimit` int(11) NOT NULL DEFAULT 21000,
     	`gasPrice` decimal(65,18) NOT NULL DEFAULT '0',
		PRIMARY KEY (`txn_hash`),
		UNIQUE KEY `txn` (`txn_hash`) USING BTREE,
		KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    $db->db->query("INSERT INTO config SET cfg='dbversion', val='1';");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 1) {
	$db->db->query("
	ALTER TABLE `transactions`
	ADD COLUMN `data` longblob NOT NULL AFTER `signature`;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 2) {

    $db->db->query("
	CREATE TABLE `smart_contracts` (
		`contract_hash` varchar(128) NOT NULL,
		`txn_hash` varchar(128) NOT NULL,
		`code` longblob NOT NULL,
		`data` longblob NOT NULL,
		PRIMARY KEY (`contract_hash`),
		UNIQUE KEY `contractHash` (`contract_hash`) USING BTREE,
		UNIQUE KEY `txnHash` (`txn_hash`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 3) {

	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;
	");

	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 4) {

	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `difficulty`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `timestamp_end_miner`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `nonce`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `root_merkle`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `block_previous`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `height`;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 5) {

    $db->db->query("
	CREATE TABLE `smart_contracts_txn` (
	  `txn_hash` varchar(128) NOT NULL,
	  `contract_hash` varchar(128) NOT NULL,
	  `wallet_from` varchar(128) NOT NULL,
	  `wallet_to` varchar(128) NOT NULL,
	  `amount` varchar(78) NOT NULL,
	  `timestamp` varchar(12) NOT NULL,
	  PRIMARY KEY (`txn_hash`),
	  UNIQUE KEY `txn` (`txn_hash`) USING HASH,
	  KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING HASH,
	  KEY `contract` (`contract_hash`) USING HASH
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 6) {

    $db->db->query("ALTER TABLE `smart_contracts` DROP COLUMN `data`;");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 7) {

	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `amount`  decimal(65,18) NOT NULL AFTER `wallet_to`,
	");

    $db->db->query("
	ALTER TABLE `smart_contracts_txn`
	MODIFY COLUMN `amount`  decimal(65,18) NOT NULL AFTER `wallet_to`;
	");

	$db->db->query("
	CREATE TABLE `accounts` (
	  `hash` varchar(128) NOT NULL,
	  `sended` decimal(65,18) unsigned NOT NULL,
	  `received` decimal(65,18) unsigned NOT NULL,
	  `mined` decimal(65,18) unsigned NOT NULL,
	  `fees` decimal(65,18) unsigned NOT NULL,
	  PRIMARY KEY (`hash`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `accounts_j4frc10` (
	  `hash` varchar(128) NOT NULL,
	  `contract_hash` varchar(128) NOT NULL,
	  `sended` decimal(65,18) unsigned NOT NULL,
	  `received` decimal(65,18) unsigned NOT NULL,
	  PRIMARY KEY (`hash`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;

	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}


if ($dbversion == 8) {

	$db->db->query("
	ALTER TABLE `transactions`
	ADD INDEX `bHash` (`block_hash`) USING HASH;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 9) {

    $db->db->query("
	ALTER TABLE `accounts_j4frc10`
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`hash`, `contract_hash`);
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 10) {

    $db->db->query("
	CREATE TABLE `txnpool` (
	  `txn_hash` varchar(128) NOT NULL,
	  `wallet_from_key` longtext,
	  `wallet_from` varchar(128) DEFAULT NULL,
	  `wallet_to` varchar(128) NOT NULL,
	  `amount` decimal(65,18) NOT NULL,
	  `signature` longtext NOT NULL,
	  `data` longblob NOT NULL,
	  `timestamp` varchar(12) NOT NULL,
	  `version` varchar(15) NOT NULL DEFAULT '0.0.1',
	  `gasLimit` int(11) NOT NULL DEFAULT 21000,
   	  `gasPrice` decimal(65,18) NOT NULL DEFAULT '0',
	  PRIMARY KEY (`txn_hash`),
	  UNIQUE KEY `txn` (`txn_hash`) USING BTREE,
	  KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 11) {

	$db->db->query("
	CREATE TABLE `accounts_j4frc20` (
	  `hash` varchar(128) NOT NULL,
	  `contract_hash` varchar(128) NOT NULL,
	  `tokenId` bigint(11) unsigned NOT NULL,
	  PRIMARY KEY (`hash`,`contract_hash`,`tokenId`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `smart_contracts_txn_token` (
	  `txn_hash` varchar(128) NOT NULL,
	  `contract_hash` varchar(128) NOT NULL,
	  `wallet_from` varchar(128) NOT NULL,
	  `wallet_to` varchar(128) NOT NULL,
	  `tokenId` bigint(11) NOT NULL,
	  `timestamp` varchar(12) NOT NULL,
	  PRIMARY KEY (`txn_hash`),
	  UNIQUE KEY `txn` (`txn_hash`) USING HASH,
	  KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING HASH,
	  KEY `contract` (`contract_hash`) USING HASH
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 12) {

	$db->db->query("
	ALTER TABLE `accounts`
	MODIFY COLUMN `hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	ENGINE=MyISAM;
	");

	$db->db->query("
	ALTER TABLE `accounts_j4frc10`
	MODIFY COLUMN `hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `contract_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `hash`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `accounts_j4frc20`
	MODIFY COLUMN `hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `contract_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `hash`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `block_previous`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `height`,
	MODIFY COLUMN `block_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `block_previous`,
	MODIFY COLUMN `root_merkle`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `block_hash`,
	MODIFY COLUMN `nonce`  varchar(200) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `root_merkle`,
	MODIFY COLUMN `timestamp_start_miner`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `nonce`,
	MODIFY COLUMN `timestamp_end_miner`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `timestamp_start_miner`,
	MODIFY COLUMN `difficulty`  varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `timestamp_end_miner`,
	MODIFY COLUMN `version`  varchar(10) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `difficulty`,
	MODIFY COLUMN `info`  text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `version`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `config`
	MODIFY COLUMN `cfg`  varchar(200) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `val`  varchar(200) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `cfg`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `peers`
	MODIFY COLUMN `ip`  varchar(120) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `id`,
	MODIFY COLUMN `port`  varchar(8) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `ip`,
	MODIFY COLUMN `blacklist`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT '' AFTER `port`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `smart_contracts`
	MODIFY COLUMN `contract_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `txn_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `contract_hash`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `smart_contracts_txn`
	MODIFY COLUMN `txn_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `contract_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `txn_hash`,
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `contract_hash`,
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `wallet_from`,
	MODIFY COLUMN `timestamp`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `amount`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `smart_contracts_txn_token`
	MODIFY COLUMN `txn_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `contract_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `txn_hash`,
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `contract_hash`,
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `wallet_from`,
	MODIFY MyISAM `timestamp`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `tokenId`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `txn_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `block_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `txn_hash`,
	MODIFY COLUMN `wallet_from_key`  longtext CHARACTER SET utf8 COLLATE utf8_bin NULL AFTER `block_hash`,
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT '' AFTER `wallet_from_key`,
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `wallet_from`,
	MODIFY COLUMN `signature`  longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `amount`,
	MODIFY COLUMN `timestamp`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `data`,
	ENGINE=MyISAM;
	");
	$db->db->query("
	ALTER TABLE `txnpool`
	MODIFY COLUMN `txn_hash`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL FIRST ,
	MODIFY COLUMN `wallet_from_key`  longtext CHARACTER SET utf8 COLLATE utf8_bin NULL AFTER `txn_hash`,
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT '' AFTER `wallet_from_key`,
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `wallet_from`,
	MODIFY COLUMN `signature`  longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `amount`,
	MODIFY COLUMN `timestamp`  varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL AFTER `data`,
	ENGINE=MyISAM;
	");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 13) {

	$db->db->query("ALTER TABLE accounts ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE accounts_j4frc10 ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE accounts_j4frc20 ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE blocks ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE config ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE peers ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE smart_contracts ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE smart_contracts_txn ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE smart_contracts_txn_token ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE transactions ENGINE = InnoDB;");
	$db->db->query("ALTER TABLE txnpool ENGINE = InnoDB;");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 14) {

	$db->db->query("ALTER TABLE `accounts` ADD UNIQUE INDEX `account` (`hash`) USING HASH;");
	$db->db->query("ALTER TABLE `accounts_j4frc10` ADD UNIQUE INDEX `account_contract` (`hash`, `contract_hash`) USING HASH;");
	$db->db->query("ALTER TABLE `accounts_j4frc20` ADD UNIQUE INDEX `account_contract_token` (`hash`, `contract_hash`, `tokenId`) USING HASH;");
	$db->db->query("ALTER TABLE `config` ADD UNIQUE INDEX `config` (`cfg`) USING HASH;");
	$db->db->query("ALTER TABLE `peers` DROP INDEX `ip`;");
	$db->db->query("ALTER TABLE `peers` ADD UNIQUE INDEX `ip_port` (`ip`, `blacklist`) USING HASH;");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 15) {

	if (FORCE_USE_ROCKSDB) {
		$db->db->query("
		set rocksdb_bulk_load=1;
		ALTER TABLE `blocks` ADD UNIQUE INDEX `height` (`height`) USING BTREE;
		");
	}
	else {
		$db->db->query("ALTER TABLE `blocks` ADD UNIQUE INDEX `height` (`height`) USING BTREE;");
	}

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 16) {

	if (FORCE_USE_ROCKSDB) {
		$db->db->query("
		set rocksdb_bulk_load=1;
		ALTER TABLE `transactions` ADD INDEX `timestamp` (`timestamp`);
		");
	}
	else {
		$db->db->query("ALTER TABLE `transactions` ADD INDEX `timestamp` (`timestamp`);");
	}

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}


// update dbversion
if ($dbversion != $_CONFIG['dbversion']) {
    $db->SetConfig('dbversion',$dbversion);
}

Display::print("DB Schema updated");

?>
