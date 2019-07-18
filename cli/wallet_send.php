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
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'DB.php');
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

if (isset($argv)) {

    if (!isset($argv[1])) {
        echo "You must specify the ".ColorsCLI::$FG_LIGHT_RED."Sender Wallet".ColorsCLI::$FG_WHITE.PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM NETWORK FEE");
    }

    if (!isset($argv[2])) {
        echo "You must specify the ".ColorsCLI::$FG_LIGHT_RED."Recipient Wallet".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM NETWORK FEE");
    }

    if (!isset($argv[3])) {
        echo "You must specify the amount you want to send".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM NETWORK FEE");
    }

    if (!isset($argv[4])) {
        echo "You must specify the password of the Sender Wallet to sign the transaction".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM NETWORK FEE");
    }

    if (!isset($argv[5])) {
        echo "You must specify the network".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM NETWORK FEE");
    }

    $isTestNet = false;
    if (isset($argv[5])) {
        if (strtoupper($argv[5]) == "TESTNET")
            $isTestNet = true;
    }
    $wallet_from = $argv[1];
    $wallet_to = $argv[2];
	$amount = $argv[3];
	$data = $argv[7];

    if ($argv[4] != "null")
        $wallet_from_password = $argv[4];
    else
        $wallet_from_password = "";

    echo Wallet::SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$data,$isTestNet);
} else {
    Display::ClearScreen();
    echo "Example of use:".PHP_EOL;
    echo "php wallet_send.php WALLET_FROM|coinbase WALLET_TO|coinbase AMOUNT PASSWORD_FROM".PHP_EOL;
    echo "php wallet_send.php coinbase VTx00000000000000000000000000000000 100 PASSWORD_FROM".PHP_EOL.PHP_EOL;
    echo "WALLET_FROM = It must be the sender address or the keyword coinbase".PHP_EOL;
    echo "WALLET_TO = It must be the destination address or the keyword coinbase".PHP_EOL;
    echo "AMOUNT = amount to send".PHP_EOL;
    echo "PASSWORD_FROM = Password from address".PHP_EOL;
    echo "NETWORK = Network to send TX (Values: TESTNET, MAINNET) - Default: MAINNET".PHP_EOL;
}
?>
