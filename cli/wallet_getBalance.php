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

date_default_timezone_set("UTC");

if (!isset($argv[1])) {
    echo "You must specify the " . ColorsCLI::$FG_LIGHT_RED . "Wallet" . ColorsCLI::$FG_WHITE . PHP_EOL;
    exit("Example: php wallet_getBalance.php WALLET|coinbase NETWORK");
}

$isTestNet = false;
if (isset($argv[2])) {
    if (strtoupper($argv[2]) == "TESTNET")
        $isTestNet = true;
}

$wallet_from = $argv[1];

echo Wallet::GetBalance($wallet_from,$isTestNet);
?>
