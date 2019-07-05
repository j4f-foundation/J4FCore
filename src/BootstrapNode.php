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

class BootstrapNode {

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @param $isTestNet
     * @return int|mixed
     */
    public static function GetPeers(&$chaindata,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETPEERS'
        );

        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @param $isTestNet
     * @return int|mixed
     */
    public static function GetPendingTransactions(&$chaindata,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETPENDINGTRANSACTIONS'
        );

        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @param $isTestNet
     * @return int
     */
    public static function GetLastBlockNum(&$chaindata,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'LASTBLOCKNUM'
        );

        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We obtain the GENESIS block from the BootstrapNode
     *
     * @param DB $chaindata
     * @param $isTestNet
     * @return mixed
     */
    public static function GetGenesisBlock(&$chaindata,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETGENESIS'
        );
        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the next 100 blocks given a current height
     *
     * @param DB $chaindata
     * @param int $lastBlockOnLocalBlockChain
     * @param bool $isTestNet
     * @return mixed
     */
    public static function SyncNextBlocksFrom($lastBlockOnLocalBlockChain,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'SYNCBLOCKS',
            'from' => $lastBlockOnLocalBlockChain
        );
        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }
}
?>