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

include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'CONFIG.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB'.DIRECTORY_SEPARATOR.'DBTransactions.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB'.DIRECTORY_SEPARATOR.'DBContracts.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB'.DIRECTORY_SEPARATOR.'DBBlocks.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB'.DIRECTORY_SEPARATOR.'DBBase.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB'.DIRECTORY_SEPARATOR.'DB.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'ColorsCLI.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Display.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Subprocess.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'BootstrapNode.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'ArgvParser.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Tools.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Wallet.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Block.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Blockchain.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Gossip.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Key.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Pki.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'PoW.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Transaction.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'GenesisBlock.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Peer.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Socket.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'SmartContract.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'SmartContractStateMachine.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'J4FVM'.DIRECTORY_SEPARATOR.'J4FVMTools.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'uint256.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'funity'.DIRECTORY_SEPARATOR.'js.php');
require __DIR__ . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use React\Socket\ConnectionInterface;

//Setting timezone to UTC
date_default_timezone_set("UTC");
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 10);

if (!isset($argv[1]))
    die("TypeCall not defined");

if (!isset($argv[2]))
    die("Contract Hash not defined");

if (!isset($argv[3]))
    die("Version not defined");

if (!isset($argv[4]))
    die("input data not defined");

if (!isset($argv[5]))
    die("TXN Hash not defined");

if (!isset($argv[6]))
    die("TXN From not defined");

if (!isset($argv[7]))
    die("TXN Amount not defined");

$typeCall = strtoupper($argv[1]);

$contractHash = $argv[2];
$version = $argv[3];

$txnData = $argv[4];
$txnHash = $argv[5];
$txnFrom = Tools::hex2str($argv[6]);
$txnAmount = $argv[7];

//Start Chaindata pointer
$chaindata = new DB();

//If call its diferent from MAKE, We check that contract exists
if ($typeCall != 'MAKE') {
	//Get contract by hash
	$contract = $chaindata->GetContractByHash($contractHash);
	if ($contract == null)
		die("Contract with that hash not defined");
}

//Load Class J4FVMBase with selected version
//If not exist this version, use latest version
$J4FVMBaseDir = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'J4FVM'.DIRECTORY_SEPARATOR;
$J4FVMBaseFile = $J4FVMBaseDir.$version.DIRECTORY_SEPARATOR.'J4FVMBase.php';
if (file_exists($J4FVMBaseFile)) {
	require($J4FVMBaseFile);
}
else {
	require($J4FVMBaseDir.'0.0.1'.DIRECTORY_SEPARATOR.'J4FVMBase.php');
}

//Load Class J4FVM
include($J4FVMBaseDir.'J4FVM.php');

//Run contract
$output = '';
if ($typeCall == 'MAKE')
	$output = SmartContract::_Make($chaindata,$contractHash,$txnHash,$txnFrom,$txnAmount,$txnData);
else if ($typeCall == 'WRITE')
	$output = SmartContract::_CallFunction($chaindata,$contract,$txnHash,$txnFrom,$txnAmount,$txnData);
else if ($typeCall == 'READ')
	$output = SmartContract::CallReadFunction($chaindata,$contract,$txnData);

echo $output;
die();
