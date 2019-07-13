<?php
// MIT License
//
// Copyright (c) 2019 Just4Fun
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//


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

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 1) {

    $db->db->query("
	ALTER TABLE `transactions`
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;

	ALTER TABLE `transactions_pending`
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;

	ALTER TABLE `transactions_pending_to_send`
	ADD COLUMN `data`  longblob NOT NULL AFTER `tx_fee`;
	");

    Display::_printer("Updating DB Schema #".$dbversion);

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

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 3) {

    $db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;

	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;

	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `wallet_to`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_from`;
	");


	$db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;

	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;

	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `wallet_from`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `wallet_from_key`;
	");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 4) {

    $db->db->query("
	ALTER TABLE `transactions`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;

	ALTER TABLE `transactions_pending`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;

	ALTER TABLE `transactions_pending_to_send`
	MODIFY COLUMN `amount` varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wallet_to`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `difficulty`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `timestamp_end_miner`;

	ALTER TABLE `blocks_pending_to_display`
	MODIFY COLUMN `difficulty`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `timestamp_end_miner`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `nonce`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `root_merkle`;

	ALTER TABLE `blocks_pending_to_display`
	MODIFY COLUMN `nonce`  varchar(78) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `root_merkle`;
	");

	$db->db->query("
	ALTER TABLE `blocks`
	MODIFY COLUMN `block_previous`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `height`;

	ALTER TABLE `blocks`
	MODIFY COLUMN `blocks_pending_to_display`  varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `height`;
	");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}



// update dbversion
if ($dbversion != $_CONFIG['dbversion']) {
    $db->SetConfig('dbversion',$dbversion);
}

Display::_printer("DB Schema updated");

?>
