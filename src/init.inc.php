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
include(__DIR__.'/J4FVM.php');
include(__DIR__.'/uint256.php');
include(__DIR__.'/../funity/js.php');

//MXC Version
define('VERSION','0.1.0');

//Start capturing flush
ob_start();

//Clear screen
Display::ClearScreen();

//Init Display message
Display::_printer("Welcome to the %G%MXC node - Version: " . VERSION);

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
