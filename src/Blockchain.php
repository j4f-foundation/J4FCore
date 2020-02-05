<?php
// Copyright 2018 MaTaXeToS
// Copyright 2019-2020 The Just4Fun Authors
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
    public static function checkDifficulty(DB &$chaindata, int $height = null, bool $isTestNet=false) : array {

        // Get last block or by height
        $currentBlock = ($height == null) ? $chaindata->GetLastBlock(false):$chaindata->GetBlockByHeight($height,false);

        // Initial difficulty
        if ($currentBlock['height'] == 0)
            return [1,1];

		// Limit of last blocks to check time
        $limit = 5;
        if ($currentBlock['height'] < 5)
            $limit = $currentBlock['height'] - 1;

        // Get diff time (timestamps are in seconds already)
		$avgTime = $chaindata->GetAvgBlockTime($currentBlock['height']-$limit);

        // Default same difficulty
        $difficulty = $currentBlock['difficulty'];

        // Min/Max Avg BlockTime
		//$minAvg = 9; $maxAvg = 11;
		$minAvg = 4; $maxAvg = 6;

        // if lower than min, increase by 2%
        if ($avgTime < $minAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "1.02",2);

        // if bigger than min, decrease by 2%
        elseif ($avgTime > $maxAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "0.98",2);

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
    public static function getRewardByHeight(int $currentHeight,bool $isTestNet=false) : string {
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
     * @return string
     */
    public static function isValidBlockMinedByPeer(DB &$chaindata, array $lastBlock, Block $blockMinedByPeer) : string {

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
     * @param array $pendingTransactions
     * @return string
     */
    public static function GetFeesOfTransactions(DB $chaindata, array $pendingTransactions) : string {

        $totalFees = bcadd("0","0",18);
        foreach ($pendingTransactions as $txn) {

			$fees = "0";

			$new_txn = Transaction::withGas($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], "","", $txn['data'], $txn['gasLimit'], $txn['gasPrice'], true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);

			//If txn is valid..
            if ($new_txn->isValid()) {
				$txnGas = Gas::calculateGasTxn($chaindata,$new_txn->to,$new_txn->data);
				if ($txnGas <= $new_txn->gasLimit)
					$fees = bcmul($txnGas,$new_txn->gasPrice,18);
				else
					$fees = bcmul($new_txn->gasLimit,$new_txn->gasPrice,18);
            }

			$totalFees = bcadd($totalFees,$fees,18);
        }
        return $totalFees;
    }

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param array $lastBlock
     * @param Block $blockMinedByPeer
     * @return string
     */
    public static function isValidBlockMinedByPeerInSameHeight(DB &$chaindata, array $lastBlock, Block $blockMinedByPeer) : string {

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
	 * Transform Block Array Structure into Block Object Structure
	 *
	 * @param array $blockArray
	 *
	 * @return Block
	 */
	public static function BlockArrayToObject(array $blockArray) : Block {
		if (is_array($blockArray) && !empty($blockArray)) {
			$infoBlock = @unserialize($blockArray['info']);

			$transactions = array();
			foreach ($blockArray['transactions'] as $transactionInfo) {
				$transactions[] = Transaction::withGas(
					$transactionInfo['wallet_from_key'],
					$transactionInfo['wallet_to'],
					$transactionInfo['amount'],
					"",
					"",
					$transactionInfo['data'],
					$transactionInfo['gasLimit'],
					$transactionInfo['gasPrice'],
					true,
					$transactionInfo['txn_hash'],
					$transactionInfo['signature'],
					$transactionInfo['timestamp']
				);
			}
			$transactionsSynced = $transactions;

			$blockObject = new Block(
				$blockArray['height'],
				$blockArray['block_previous'],
				$blockArray['difficulty'],
				$transactions,
				null,
				null,
				-1,
				-1,
				true,
				$blockArray['block_hash'],
				$blockArray['nonce'],
				$blockArray['timestamp_start_miner'],
				$blockArray['timestamp_end_miner'],
				$blockArray['root_merkle'],
				$infoBlock
			);

			return $blockObject;
		}
		return null;
	}

	/**
	 * Check integrity of blocks
	 *
	 * @param DB $chaindata
	 * @param int $heightToStart
	 * @param int $blocksToCheck
	 *
	 * @return bool
	 */
	public static function checkIntegrity(DB &$chaindata,int $heightToStart=null,int $blocksToCheck=20) : bool {

		$isTestNet = ($chaindata->GetNetwork() == "testnet") ? true:false;

		if ($heightToStart == null)
			$heightToStart = $chaindata->GetNextBlockNum()-1;

		for ($i = $heightToStart ; $i > ($heightToStart-$blocksToCheck); $i--) {
			$currentBlock = $chaindata->GetBlockByHeight($i);
			$previousBlock = $chaindata->GetBlockByHeight(($i-1));

			if ((is_array($currentBlock) && !empty($currentBlock)) && (is_array($previousBlock) && !empty($previousBlock))) {

				if ($currentBlock['block_previous'] != $previousBlock['block_hash']) {
					self::SanityFromBlockHeight($chaindata,(($heightToStart-$i)-1));
					return false;
				}

				//Transform blockArray into blockObject
				$blockToCheck = BlockChain::BlockArrayToObject($currentBlock);
				if ($blockToCheck != null) {
					//Check if rewarded transaction is valid, prevent hack money
					if (!$blockToCheck->isValidReward($currentBlock['height'],$isTestNet)) {
						self::SanityFromBlockHeight($chaindata,(($heightToStart-$i)-1));
						return false;
					}

					//AddBlock to blockchain
					if (!$blockToCheck->isValid($currentBlock['height'],$isTestNet)) {
						self::SanityFromBlockHeight($chaindata,(($heightToStart-$i)-1));
						return false;
					}
				}
			}
		}

		return true;
	}

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param int $numBlocksToRemove
     * @return bool
     */
    public static function SanityFromBlockHeight(DB &$chaindata, int $numBlocksToRemove=1) : bool {

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
