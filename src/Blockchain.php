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

class Blockchain {
    /**
     * Function that checks the difficulty of the network given the current block
     *
     * @param DB $chaindata
     * @param $height
     * @param $isTestNet
     *
     * @return array
     */
    public static function checkDifficulty(&$chaindata,$height = null,$isTestNet=false) {

        // Get last block or by height
        $currentBlock = ($height == null) ? $chaindata->GetLastBlock(false):$chaindata->GetBlockByHeight($height,false);

        // Initial difficulty
        if ($currentBlock['height'] == 0)
            return [1,1];

        // for first 5 blocks use difficulty 1
        if ($currentBlock['height'] < 5)
            return [1,1];

        // Limit of last blocks to check time
        $limit = ($isTestNet) ? 2880:5760;
        if ($currentBlock['height'] < 2880)
            $limit = $currentBlock['height'] - 1;

        // Get limit check block
        $limitBlock = $chaindata->GetBlockByHeight($currentBlock['height']-$limit);

        // Get diff time (timestamps are in seconds already)
        $diffTime = $currentBlock['timestamp_end_miner'] - $limitBlock['timestamp_end_miner'];
        $avgTime = ceil($diffTime / $limit);

        // Default same difficulty
        $difficulty = $currentBlock['difficulty'];

        // Max 16s - Min 14s
        $minAvg = 14;
        $maxAvg = 16;

		// If testnet Max 35s - Min 25s
        if ($isTestNet) {
            //$minAvg = 29;
			$minAvg = 9;
			//$maxAvg = 31;
            $maxAvg = 11;
		}

        // if lower than min, increase by 5%
        if ($avgTime < $minAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "1.05",2);

        // if bigger than min, decrease by 5%
        elseif ($avgTime > $maxAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "0.95",2);

		// Min difficulty is 1
       if ($difficulty < 1)
           $difficulty = 1;

        return [$difficulty,$avgTime];
    }

    /**
     * Calc reward by block height
     *
     * @param $currentHeight
     * @param bool $isTestNet
     * @return string
     */
    public static function getRewardByHeight($currentHeight,$isTestNet=false) {
        // static reward
        return bcadd("2","0",18);
    }

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function isValidBlockMinedByPeer(&$chaindata,$lastBlock, $blockMinedByPeer) {

		//If dont have new block
        if ($blockMinedByPeer == null)
            return "0x00000004";

        //If the previous block received by network refer to the last block of my blockchain
        if ($blockMinedByPeer->previous != $lastBlock['block_hash'])
            return "0x00000003";

        //Get next block height
        $heightNewBlock = $chaindata->GetNextBlockNum();
        $isTestnet = ($chaindata->GetNetwork() == "testnet") ? true:false;

        //If the block is valid
        if (!$blockMinedByPeer->isValid($heightNewBlock,$isTestnet)) {
            return "0x00000001";
        }

        $isTestnet = false;
        if ($chaindata->GetNetwork() == "testnet")
            $isTestnet = true;

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($heightNewBlock,$isTestnet)) {
            //Add Block to blockchain
            if ($chaindata->addBlock($heightNewBlock,$blockMinedByPeer)) {

				//Make SmartContracts on local blockchain
				SmartContract::Make($chaindata,$blockMinedByPeer);

				//Call Functions of SmartContracts on local blockchain
				SmartContract::CallFunction($chaindata,$blockMinedByPeer);

                if ($chaindata->GetConfig('isBootstrap') == 'on')
                    Tools::SendMessageToDiscord($heightNewBlock,$blockMinedByPeer);

                return "0x00000000";

            } else {
                return "Error, can't add block ".$heightNewBlock;
            }
        } else {
            return "0x00000002";
        }
    }

    /**
     * Calc total fees of pending transactions to add on new block
     *
     * @param $pendingTransactions
     * @return string
     */
    public static function GetFeesOfTransactions($pendingTransactions) {

        $totalFees = bcadd("0","0",18);
        foreach ($pendingTransactions as $txn) {
            $new_txn = new Transaction($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], null,null, $txn['tx_fee'],$txn['data'],true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);
            if ($new_txn->isValid()) {
				$totalFees = bcadd($totalFees,$new_txn->tx_fee,18);
            }
        }
        return $totalFees;
    }

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function isValidBlockMinedByPeerInSameHeight(&$chaindata,$lastBlock, $blockMinedByPeer) {

        //If dont have new block
        if ($blockMinedByPeer == null)
            return "0x00000004";

		//If the previous block received by network refer to the last block of my blockchain
        if ($blockMinedByPeer->previous != $lastBlock['block_previous'])
            return "0x00000003";

        //Check if node is connected on testnet or mainnet
        $isTestnet = ($chaindata->GetNetwork() == "testnet") ? true:false;

        //Check if new block is valid
        if (!$blockMinedByPeer->isValid($lastBlock['height'],$isTestnet))
            return "0x00000001";

        //Default, no accept new block
        $acceptNewBlock = false;

        $numNewBlock = Tools::hex2dec($blockMinedByPeer->hash);
        $numLastBlock = Tools::hex2dec($lastBlock['block_hash']);

        //If new block is smallest than last block accept new block
        if (bccomp($numLastBlock, $numNewBlock) == 1)
            $acceptNewBlock = true;

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($lastBlock['height'],$isTestnet)) {

            if ($acceptNewBlock) {

                //Remove last block
                if ($chaindata->RemoveBlock($lastBlock['height'])) {

                    //AddBlock to blockchain
					if ($chaindata->addBlock($lastBlock['height'],$blockMinedByPeer)) {

						//Make SmartContracts on local blockchain
						SmartContract::Make($chaindata,$blockMinedByPeer);

						//Call Functions of SmartContracts on local blockchain
						SmartContract::CallFunction($chaindata,$blockMinedByPeer);

						//Propagate mined block to network
						Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

						return "0x00000000";
					}
                } else
                    return "0x00000006";
            }
			else {
				return "0x00000005";
			}
        } else {
            return "0x00000002";
        }
	}

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param int $numBlocksToRemove
     * @return bool
     */
    public static function SanityFromBlockHeight(&$chaindata,$numBlocksToRemove=1) {

		//Get Last block without transactions
		$lastBlock = $chaindata->GetLastBlock(false);

		//Save current height
		$currentHeightToRemove = $lastBlock['height'];

		//Remove all blocks to new height
		for ($i = 1; $i <= $numBlocksToRemove; $i++) {
			$chaindata->RemoveBlock($currentHeightToRemove);
			$currentHeightToRemove--;
		}

		return true;
    }
}
?>
