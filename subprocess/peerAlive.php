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

ob_start();

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
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'REGEX.php');
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
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'uint256.php');
require __DIR__ . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use React\Socket\ConnectionInterface;

//Setting timezone to UTC
date_default_timezone_set("UTC");

if (!isset($argv[1]))
    die("ID not defined");

if ($argv[1] == -1) {

    $chaindata = new DB();

    //Run subprocess peerAlive per peer
    $peers = $chaindata->GetAllPeersWithoutBootstrap();

    $lastBlock = $chaindata->GetLastBlock();

    if (count($peers) > 0) {
        $id = 0;
        foreach ($peers as $peer) {
            //Params for subprocess
            $params = array(
                $peer['ip'],
                $peer['port'],
                $lastBlock['block_hash'],
                $lastBlock['height']
            );

            //Run subprocess propagation
            Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'peerAlive',$params,$id);
            $id++;
        }
    }
} else {
    if (!isset($argv[2]))
        die("Peer IP not defined");

    if (!isset($argv[3]))
        die("Peer PORT not defined");

    $chaindata = new DB();

    $id = $argv[1];
    $peerIP = $argv[2];
    $peerPORT = $argv[3];
    $lastBlockHash = $argv[4];
    $lastBlockHeight = $argv[5];

    $infoToSend = array(
        'action' => 'STATUSNODE'
    );

    $response = Socket::sendMessageWithReturn($peerIP,$peerPORT,$infoToSend,2);

    //Check if response as ok
    if ($response['status']) {

        //Check if peer have same height block
        if ($response['result']['lastBlock'] > ($lastBlockHeight+1)) {

            Tools::writeLog('SUBPROCESS::This peer '.$peerIP.':'.$peerPORT.' have more blocks than me');

            //Check if have same GENESIS block from peer
            $peerGenesisBlock = Peer::GetGenesisBlock($peerIP.':'.$peerPORT);
            $localGenesisBlock = $chaindata->GetGenesisBlock();
            if ($localGenesisBlock['block_hash'] == $peerGenesisBlock['block_hash']) {
                Tools::writeLog('SUBPROCESS::Selected peer '.$peerIP.':'.$peerPORT.' for sync');
				Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$peerIP.":".$peerPORT);
            } else {
                Tools::writeLog('SUBPROCESS::This peer '.$peerIP.':'.$peerPORT.' have diferent GENESIS block');
            }
        }
    }

    if ($response == null) {
        $chaindata->addPeerToBlackList($peerIP.':'.$peerPORT);
    }
}
die();
