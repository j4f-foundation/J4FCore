<?php
// MIT License
//
// Copyright (c) 2018 MXCCoin
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

    $tx_fee = 2;
    if (isset($argv[6])) {
        switch (strtoupper($argv[6])) {
            case "HIGH":
                $tx_fee = 3;
            break;
            case "MEDIUM":
                $tx_fee = 2;
            break;
            case "LOW":
                $tx_fee = 1;
            break;
            default:
                $tx_fee = 2;
            break;
        }
    }

    $wallet_from = $argv[1];
    $wallet_to = $argv[2];
    $amount = $argv[3];

    if ($argv[4] != "null")
        $wallet_from_password = $argv[4];
    else
        $wallet_from_password = "";

    echo Wallet::SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$tx_fee,$isTestNet);
} else {
    Display::ClearScreen();
    echo "Example of use:".PHP_EOL;
    echo "php wallet_send.php WALLET_FROM|coinbase WALLET_TO|coinbase AMOUNT PASSWORD_FROM FEE".PHP_EOL;
    echo "php wallet_send.php coinbase VTx00000000000000000000000000000000 100 PASSWORD_FROM high".PHP_EOL.PHP_EOL;
    echo "WALLET_FROM = It must be the sender address or the keyword coinbase".PHP_EOL;
    echo "WALLET_TO = It must be the destination address or the keyword coinbase".PHP_EOL;
    echo "AMOUNT = amount to send".PHP_EOL;
    echo "PASSWORD_FROM = Password from address".PHP_EOL;
    echo "NETWORK = Network to send TX (Values: TESTNET, MAINNET) - Default: MAINNET".PHP_EOL;
    echo "FEE = Fee of transaction (Values: high, medium low) - Default: medium".PHP_EOL;
}
?>