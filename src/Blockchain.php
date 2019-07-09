<?php
// MIT License
//
// Copyright (c) 2019 Just4Fun
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

        //Get last block or by height
        $currentBlock = ($height == null) ? $chaindata->GetLastBlock(false):$chaindata->GetBlockByHeight($height,false);

        //Initial difficulty
        if ($currentBlock['height'] == 0)
            return [1,1];

        // for first 5 blocks use difficulty 1
        if ($currentBlock['height'] < 5)
            return [1,1];

        // Limit of last blocks to check time
        $limit = 20;
        if ($currentBlock['height'] < 20)
            $limit = $currentBlock['height'] - 1;

        //Get limit check block
        $limitBlock = $chaindata->GetBlockByHeight($currentBlock['height']-$limit);

        //Get diff time (timestamps are in seconds already)
        $diffTime = $currentBlock['timestamp_end_miner'] - $limitBlock['timestamp_end_miner'];
        $avgTime = ceil($diffTime / $limit);

        //Default same difficulty
        $difficulty = $currentBlock['difficulty'];

        //Max 9 - Min 7
        $minAvg = 420;
        $maxAvg = 540;

		//If testnet Max 3 - Min 1
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

        //MIn difficulty is 1
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
					Blockchain::MakeSmartContracts($chaindata,$blockMinedByPeer);

					//Call Functions of SmartContracts on local blockchain
					Blockchain::CallFunctionSmartContract($chaindata,$blockMinedByPeer);

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
     * Make Smart Contracts of this block
     * All nodes create smart contracts on blockchain (local)
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function MakeSmartContracts(&$chaindata,$block) {

		//Obteemos todas las transacciones del bloque
		//Si alguna transaccion va dirigida a 00000

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Check if transaction is for make new contract
				if ($transaction->to == 'J4F00000000000000000000000000000000000000000000000000000000' && $transaction->data != "0x") {

					//Parse txn::data (contract code) to string
					$contract_code = $transaction->data;
					$code = Tools::bytesHex2str($contract_code);

					//Merge code into MXDity::MakeContract
					$code_parsed = J4FVM::_init($code);

					//Define sender Object
					js::define("msg",
						array(),
						array(
							"sender"=> Wallet::GetWalletAddressFromPubKey($transaction->from),
						)
					);
				
					//Define contract Object
					js::define("contract",
						array(
							"get" => "js_get", 
							"set" => "js_set",
							"table" => "js_table",
							"table_set" => "js_table_set",
							"table_get" => "js_table_get",
							"table_get_sub" => "js_table_get_sub",
						),
						array()
					);

					//Get Contract Hash
					$contractHash = PoW::hash($contract_code.$transaction->from.$transaction->timestamp.$transaction->signature);

					#Contract status - Default not created
					$run_status = 0;

					try {

						//Run code
						js::run($code_parsed,$contractHash);

						//Contract status - OK
						$run_status = 1;

					} catch (Exception $e) {

						//Contract status - Error
						$run_status = -1;
					}

					// If status contract its OK, save this contract
					if ($run_status == 1) {

						//Get data contract
						$contractData = Tools::str2bytesHex(json_encode(J4FVM::$data));

						//Add SmartContract on blockchain (local)
						$chaindata->addSmartContract($contractHash,$transaction->hash,$contract_code,$contractData);
					}
				}
			}
		}
	}
	
	/**
     * Call a function of Contracts
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function CallFunctionSmartContract(&$chaindata,$block) {

		//Obteemos todas las transacciones del bloque
		//Si alguna transaccion va dirigida a 00000

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Txn to Contract
				if ((strlen($transaction->to) > 64) && $transaction->data != "0x") {
					$contract = $chaindata->GetContractByHash($transaction->to);
					if ($contract != null) {
						//Display::_printer('CONTRACT HASH: ' . $contract->contract_hash);

						//Parse txn::data (call code) to string
						$call_code_hex = $transaction->data;
						$call_code = Tools::bytesHex2str($call_code_hex);

						//Parse CALL Code
						$code_call_info = J4FVM::_parseCall($call_code);

						//Parse contract code to string
						$code_contract = Tools::bytesHex2str($contract['code']);

						//Parse storageData of contract
						$data_contract = @json_decode(Tools::bytesHex2str($contract['data']),true);

						//Parse code MXDity::Call_Contract
						$code_parsed = J4FVM::call($code_contract,$code_call_info['func'],$code_call_info['func_params']);

						//Define sender Object
						js::define("msg",
							array(),
							array(
								"sender"=> Wallet::GetWalletAddressFromPubKey($transaction->from),
							)
						);
					
						//Define contract Object
						js::define("contract",
							array(
								"get" => "js_get", 
								"set" => "js_set",
								"table" => "js_table",
								"table_set" => "js_table_set",
								"table_get" => "js_table_get",
								"table_get_sub" => "js_table_get_sub",
							),
							array()
						);
	
						//Contract status - Default not created
						$run_status = 0;

						try {
							//Set data of contract
							J4FVM::$data = @json_decode(Tools::bytesHex2str($contract['data']),true);

							//Display::_printer($code_parsed);

							//Run code
							js::run($code_parsed);

							//Contract status - OK
							$run_status = 1;

						} catch (Exception $e) {

							//Contract status - Error
							$run_status = -1;
						}
	
						// If status contract its OK, save this contract
						if ($run_status == 1) {
	
							//Get data contract
							$contractData = Tools::str2bytesHex(json_encode(J4FVM::$data));
	
							//Update StoredData of Smart Contract on blockchain (local)
							$chaindata->updateStoredDataContract($contract['contract_hash'],$contractData);
						}
						
					}
				}
			}
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
						Blockchain::MakeSmartContracts($chaindata,$blockMinedByPeer);

						//Call Functions of SmartContracts on local blockchain
						Blockchain::CallFunctionSmartContract($chaindata,$blockMinedByPeer);

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
