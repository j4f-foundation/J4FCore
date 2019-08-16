<?php
// Copyright 2018 MaTaXeToS
// Copyright 2019 The Just4Fun Authors
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
	CREATE TABLE `blocks_announced` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`block_hash` varchar(128) NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `bHash` (`block_hash`)
	) ENGINE=MyISAM AUTO_INCREMENT=323 DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `blocks_pending_to_display` (
		`height` int(200) unsigned NOT NULL AUTO_INCREMENT,
		`status` varchar(10) NOT NULL,
		`block_previous` varchar(128) NOT NULL,
		`block_hash` varchar(128) NOT NULL,
		`root_merkle` varchar(128) NOT NULL,
		`nonce` varchar(200) NOT NULL,
		`timestamp_start_miner` varchar(12) NOT NULL,
		`timestamp_end_miner` varchar(12) NOT NULL,
		`difficulty` varchar(255) NOT NULL,
		`version` varchar(10) NOT NULL,
		`info` text NOT NULL,
		PRIMARY KEY (`height`),
		UNIQUE KEY `bHash` (`block_hash`)
	) ENGINE=MyISAM AUTO_INCREMENT=383 DEFAULT CHARSET=utf8;
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
		`tx_fee` varchar(10) DEFAULT NULL,
		`timestamp` varchar(12) NOT NULL,
		PRIMARY KEY (`txn_hash`),
		UNIQUE KEY `txn` (`txn_hash`) USING BTREE,
		KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("
	CREATE TABLE `transactions_pending` (
		`txn_hash` varchar(128) NOT NULL,
		`block_hash` varchar(128) NOT NULL,
		`wallet_from_key` longtext,
		`wallet_from` varchar(64) DEFAULT NULL,
		`wallet_to` varchar(64) NOT NULL,
		`amount` varchar(64) NOT NULL,
		`signature` longtext NOT NULL,
		`tx_fee` varchar(10) DEFAULT NULL,
		`timestamp` varchar(12) NOT NULL,
		PRIMARY KEY (`txn_hash`),
		UNIQUE KEY `txn` (`txn_hash`) USING BTREE,
		KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

    $db->db->query("
		CREATE TABLE `transactions_pending_to_send` (
			`txn_hash` varchar(128) NOT NULL,
			`block_hash` varchar(128) NOT NULL,
			`wallet_from_key` longtext,
			`wallet_from` varchar(64) DEFAULT NULL,
			`wallet_to` varchar(64) NOT NULL,
			`amount` varchar(64) NOT NULL,
			`signature` longtext NOT NULL,
			`tx_fee` varchar(10) DEFAULT NULL,
			`timestamp` varchar(12) NOT NULL,
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
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending`
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending_to_send`
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;
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
	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;
	");

	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;
	");

    $db->db->query("
	ALTER TABLE `transactions_pending_to_send`
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
	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `difficulty`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `timestamp_end_miner`;
	");

	$db->db->query("
	ALTER TABLE `blocks_pending_to_display`
	MODIFY COLUMN `difficulty`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `timestamp_end_miner`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `nonce`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `root_merkle`;
	");

	$db->db->query("
	ALTER TABLE `blocks_pending_to_display`
	MODIFY COLUMN `nonce`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `root_merkle`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `block_previous`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `height`;
	");

    $db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `blocks_pending_to_display`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `height`;
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
	MODIFY COLUMN `tx_fee`  decimal(65,18) NOT NULL AFTER `signature`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `amount`  decimal(65,18) NOT NULL AFTER `wallet_to`,
	MODIFY COLUMN `tx_fee`  decimal(65,18) NOT NULL AFTER `signature`;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `amount`  decimal(65,18) NOT NULL AFTER `wallet_to`,
	MODIFY COLUMN `tx_fee`  decimal(65,18) NOT NULL AFTER `signature`;
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

	$db->db->query("
	ALTER TABLE `transactions_pending`
	ADD INDEX `bHash` (`block_hash`) USING HASH;
	");

	$db->db->query("
	ALTER TABLE `transactions_pending_to_send`
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
	  `tx_fee` decimal(65,18) NOT NULL,
	  `data` longblob NOT NULL,
	  `timestamp` varchar(12) NOT NULL,
	  PRIMARY KEY (`txn_hash`),
	  UNIQUE KEY `txn` (`txn_hash`) USING BTREE,
	  KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING BTREE
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	");

	$db->db->query("DROP TABLE transactions_pending;");
	$db->db->query("DROP TABLE transactions_pending_to_send;");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 11) {

	$db->db->query("DROP TABLE blocks_announced;");
	$db->db->query("DROP TABLE blocks_pending_to_display;");

    Display::print("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 12) {

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


// update dbversion
if ($dbversion != $_CONFIG['dbversion']) {
    $db->SetConfig('dbversion',$dbversion);
}

Display::print("DB Schema updated");

?>
