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

class BootstrapNode {

    /**
     *
     * We get peers from BootstrapNode
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

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
		if ($infoPOST != null) {
			if ($infoPOST['status'] == 1)
				return $infoPOST['result'];
			else
				return 0;
		}
		else
			return 0;
    }

    /**
     *
     * We get pending transactions from BootstrapNode
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

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
		if ($infoPOST != null) {
			if ($infoPOST['status'] == 1)
				return $infoPOST['result'];
			else
				return 0;
		}
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

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
		if ($infoPOST != null) {
			if (isset($infoPOST['status']) && $infoPOST['status'] == 1)
				return $infoPOST['result'];
			else
				return 0;
		}
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
		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend,60);
		if ($infoPOST != null) {
			if ($infoPOST['status'] == 1)
				return $infoPOST['result'];
			else
				return 0;
		}
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
		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
		if ($infoPOST != null) {
			if ($infoPOST['status'] == 1)
				return $infoPOST['result'];
			else
				return 0;
		}
		else
			return 0;
    }
}
?>
