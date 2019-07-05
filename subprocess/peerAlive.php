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

ob_start();

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

//Setting timezone to UTC
date_default_timezone_set("UTC");

if (!isset($argv[1]))
    die("ID not defined");

if ($argv[1] == -1) {

    $chaindata = new DB();

    //Run subprocess peerAlive per peer
    $peers = $chaindata->GetAllPeers();

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

    $response = null;
    if ($peerIP == NODE_BOOTSTRAP) {
        $response = Tools::postContent('https://'.NODE_BOOTSTRAP.'/gossip.php', $infoToSend,60);
    }
    else if ($peerIP == NODE_BOOTSTRAP_TESTNET) {
        $response = Tools::postContent('https://'.NODE_BOOTSTRAP_TESTNET.'/gossip.php', $infoToSend,60);
    }
    else {
        $response = Tools::postContent('http://' . $peerIP . ':' . $peerPORT . '/gossip.php', $infoToSend,60);
    }

    //Check if response as ok
    if ($response->status) {


		Tools::writeLog('SUBPROCESS::ResultLastBlock: ' . $response->result->lastBlock);
		Tools::writeLog('SUBPROCESS::LastBlockHeight: ' . ($lastBlockHeight+1));

        //Check if peer have same height block
        if ($response->result->lastBlock > ($lastBlockHeight+1)) {

            Tools::writeLog('SUBPROCESS::This peer '.$peerIP.':'.$peerPORT.' have more blocks than me');

            //Check if have same GENESIS block from peer
            $peerGenesisBlock = Peer::GetGenesisBlock($peerIP.':'.$peerPORT);
            $localGenesisBlock = $chaindata->GetGenesisBlock();
            if ($localGenesisBlock['block_hash'] == $peerGenesisBlock->block_hash) {

                Tools::writeLog('SUBPROCESS::Selected peer '.$peerIP.':'.$peerPORT.' for sync');

                //Write sync_with_peer
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