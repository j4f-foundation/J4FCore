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
        $limit = 20;
        if ($currentBlock['height'] < 20)
            $limit = $currentBlock['height'] - 1;

        // Get limit check block
        $limitBlock = $chaindata->GetBlockByHeight($currentBlock['height']-$limit);

        // Get diff time (timestamps are in seconds already)
        $diffTime = $currentBlock['timestamp_end_miner'] - $limitBlock['timestamp_end_miner'];
        $avgTime = ceil($diffTime / $limit);

        // Default same difficulty
        $difficulty = $currentBlock['difficulty'];

        // Max 9min - Min 7min
        $minAvg = 420;
        $maxAvg = 540;

		// If testnet Max 30s - Min 15s
        if ($isTestNet) {
            $minAvg = 15;
            $maxAvg = 30;
		}

        // if lower than 1 min, increase by 5%
        if ($avgTime < $minAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "1.05",2);

        // if bigger than 3 min, decrease by 5%
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

        //Testnet will always be 50
        if ($isTestNet)
            return number_format("50", 8, '.', '');

        // init reward Mainnet
        $reward = 50;

        //Get divisible num
        $divisible = floor($currentHeight / 250000);
        if ($divisible > 0) {

            //Can't divide by 0
            if ($divisible <= 0)
                $divisible = 1;

            // Get current reward
            $reward = ($reward / $divisible) / 2;
        }

        //Reward can't be less than
        if ($reward < 1)
            $reward = 0;

        return number_format($reward, 8, '.', '');
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

        if ($blockMinedByPeer == null)
            return "0x00000004";

        //If the previous block received by network refer to the last block of my blockchain
        if ($blockMinedByPeer->previous != $lastBlock['block_hash']) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000003");
            return "0x00000003";
        }

        //Get next block height
        $heightNewBlock = $chaindata->GetNextBlockNum();
        $isTestnet = ($chaindata->GetNetwork() == "testnet") ? true:false;

        //If the block is valid
        if (!$blockMinedByPeer->isValid($heightNewBlock,$isTestnet)) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000002");
            return "0x00000002";
        }

        $isTestnet = false;
        if ($chaindata->GetNetwork() == "testnet")
            $isTestnet = true;

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($heightNewBlock,$isTestnet)) {

            //Check if block is waiting to display
            $blockPendingToDisplay = $chaindata->GetBlockPendingToDisplayByHash($blockMinedByPeer->hash);
            if (empty($blockPendingToDisplay)) {

                //Propagate mined block to network
                //Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

                //Add this block in pending block (DISPLAY)
                $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000000");

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
                    return "Error, can't add block".$heightNewBlock;
                }

            } else {
                return "Block added previously, reject block".$heightNewBlock;
            }
        } else {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000001");
            return "0x00000001";
        }
    }

    /**
     * Calc total fees of pending transactions to add on new block
     *
     * @param $pendingTransactions
     * @return string
     */
    public static function GetFeesOfTransactions($pendingTransactions) {

        $totalFees = "0";
        foreach ($pendingTransactions as $txn) {
            $new_txn = new Transaction($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], null,null, $txn['tx_fee'],$txn['data'],true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);
            if ($new_txn->isValid()) {
                if ($txn['tx_fee'] == 3)
                    $totalFees = bcadd($totalFees,"0.00001400",8);
                else if ($txn['tx_fee'] == 2)
                    $totalFees = bcadd($totalFees,"0.00000900",8);
                else if ($txn['tx_fee'] == 1)
                    $totalFees = bcadd($totalFees,"0.00000250",8);
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

        //Check if node is connected on testnet or mainnet
        $isTestnet = ($chaindata->GetNetwork() == "testnet") ? true:false;

        //Check if new block is valid
        if (!$blockMinedByPeer->isValid($lastBlock['height'],$isTestnet)) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000002");
            return "0x00000002";
        }

        //Default, no accept new block
        $acceptNewBlock = false;

        $numNewBlock = Tools::hex2dec($blockMinedByPeer->hash);
        $numLastBlock = Tools::hex2dec($lastBlock['block_hash']);

        //If new block is smallest than last block accept new block
        if (bccomp($numLastBlock, $numNewBlock) == 1)
            $acceptNewBlock = true;


        if ($acceptNewBlock)
            Tools::writeLog('ACCEPTED NEW BLOC');

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($lastBlock['height'],$isTestnet)) {

            Tools::writeLog('REWARD VALIDATED');

            if ($acceptNewBlock) {

                //Remove last block
                if ($chaindata->RemoveBlock($lastBlock['height'])) {

                    //AddBlock to blockchain
					if ($chaindata->addBlock($lastBlock['height'],$blockMinedByPeer)) {

						//Make SmartContracts on local blockchain
						SmartContract::Make($chaindata,$blockMinedByPeer);

						//Call Functions of SmartContracts on local blockchain
						SmartContract::CallFunction($chaindata,$blockMinedByPeer);

						//Add this block in pending block (DISPLAY)
						$chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000000");

						Tools::writeLog('ADD NEW BLOCK in same height');

						//Propagate mined block to network
						Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

						Tools::writeLog('Propagated new block in same height');
					}

                    return "0x00000000";
                } else
                    return "0x00000001";
            }
        } else {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000001");
            return "0x00000001";
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
