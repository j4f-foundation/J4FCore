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

include(__DIR__.'/../CONFIG.php');
include(__DIR__.'/DB.php');
include(__DIR__.'/ColorsCLI.php');
include(__DIR__.'/Display.php');
include(__DIR__.'/Subprocess.php');
include(__DIR__.'/Tools.php');
include(__DIR__.'/BootstrapNode.php');
include(__DIR__.'/ArgvParser.php');
include(__DIR__.'/Wallet.php');
include(__DIR__.'/Block.php');
include(__DIR__.'/Blockchain.php');
include(__DIR__.'/Gossip.php');
include(__DIR__.'/Key.php');
include(__DIR__.'/Pki.php');
include(__DIR__.'/PoW.php');
include(__DIR__.'/Transaction.php');
include(__DIR__.'/Miner.php');
include(__DIR__.'/GenesisBlock.php');
include(__DIR__.'/Peer.php');
include(__DIR__.'/SmartContract.php');
include(__DIR__.'/SmartContractStateMachine.php');;
include(__DIR__.'/J4FVMBase.php');
include(__DIR__.'/J4FVM.php');
include(__DIR__.'/uint256.php');
include(__DIR__.'/../funity/js.php');

//J4F Version
define('VERSION','0.1.0');

//Start capturing flush
ob_start();

//Clear screen
Display::ClearScreen();

//Init Display message
Display::_printer("Welcome to the %G%J4F node - Version: " . VERSION);

//Setting timezone to UTC
date_default_timezone_set("UTC");

error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(0);
//ini_set('display_errors', "off");

if (DB_PASS == "DEFINE_YOUR_PASSWORD") {
    Display::_printer("%LR%ERROR%W%    Database password not defined");
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

// checks for php extensions
if (!extension_loaded("openssl") && !defined("OPENSSL_KEYTYPE_RSA")) {
    Display::_error("You must install the extension %LG%openssl%W% with %LG%OPENSSL_KEYTYPE_RSA");
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

if (!extension_loaded("mysqli")) {
    Display::_error("You must install the extension %LG%mysqli");

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

if (!extension_loaded("bcmath")) {
    Display::_error("You must install the extension %LG%bcmath");
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

// check php version
if (floatval(phpversion()) < 7.1) {
    Display::_error("The minimum php version required is %LG%7.1");
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

// check DB connection
$db = new DB();
if ($db == null) {
    Display::_error("Could not connect to the database");
    Display::_error("Check CONFIG.php and setup correct mysql data");

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

//Get Config
$_CONFIG = $db->GetAllConfig();

// check need update database schema
if (@file_exists("tmp".DIRECTORY_SEPARATOR."db.update")) {
    $res = @unlink("tmp".DIRECTORY_SEPARATOR."db.update");
    if ($res) {
        Display::_printer("Updating DB Schema");
        require_once __DIR__.'/schema.inc.php';
        exit();
    }
    Display::_error("Could not access the tmp/db.update file");
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        readline("Press any Enter to close close window");
    exit();
}

?>
