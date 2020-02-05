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

class DBBlocks extends DBContracts {

    /**
     * Get miner of block
     *
     * @param string $hash
     * @return object
     */
    public function GetMinerOfBlockByHash(string $hash) : string {
        $minerTransaction = $this->db->query("SELECT wallet_to FROM transactions WHERE block_hash = '".$hash."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC LIMIT 1;")->fetch_assoc();
        if (!empty($minerTransaction))
            return $minerTransaction['wallet_to'];
        return "";
    }

    /**
     * Returns a block given a hash
     *
     * @param string $hash
     * @param bool $withTransactions
     * @return array
     */
    public function GetBlockByHash(string $hash,bool $withTransactions=false) : array {
        $sql = "SELECT * FROM blocks WHERE block_hash = '".$hash."'";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block)) {

            $transactions = array();

            //Select only hashes of txns
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //Get transactions
            $transactions_chaindata = $this->db->query($sql);
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                    if ($withTransactions)
                        $transactions[] = $transactionInfo;
                    else
                        $transactions[] = $transactionInfo['txn_hash'];
                }
            }

			//TMP FIX
			//Select only hashes of txns
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //Get transactions
            $transactions_chaindata = $this->db->query($sql);
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                    if ($withTransactions)
                        $transactions[] = $transactionInfo;
                    else
                        $transactions[] = $transactionInfo['txn_hash'];
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return [];
    }

    /**
     * Returns a block given a hash
     *
     * @param string $hash
     * @return int
     */
    public function GetBlockHeightByHash($hash) : int {
        $sql = "SELECT height FROM blocks WHERE block_hash = '".$hash."' LIMIT 1;";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block))
            return intval($info_block['height']);
        return -1;
    }

    /**
     * Returns a block given a height
     *
     * @param string $height
     * @param bool $withTransactions
     * @return array
     */
    public function GetBlockByHeight(int $height,bool $withTransactions=true) : array {

        $sql = "SELECT * FROM blocks WHERE height = ".$height.";";
        $info_block = $this->db->query($sql)->fetch_assoc();

        if (!empty($info_block)) {

            $transactions = array();

            //Select only hashes of txns
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //Get transactions
            $transactions_chaindata = $this->db->query($sql);
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                    if ($withTransactions)
                        $transactions[] = $transactionInfo;
                    else
                        $transactions[] = $transactionInfo['txn_hash'];
                }
            }

			//TMP FIX
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

            //Get transactions
            $transactions_chaindata = $this->db->query($sql);
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                    if ($withTransactions)
                        $transactions[] = $transactionInfo;
                    else
                        $transactions[] = $transactionInfo['txn_hash'];
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return [];
    }

    /**
     * Add a block in the chaindata
     *
     * @param int $blockNum
     * @param Block $blockInfo
     * @return bool
     */
    public function addBlock(int $blockNum,Block $blockInfo) : bool {

        $error = false;

        $info_block_chaindata = $this->db->query("SELECT block_hash FROM blocks WHERE block_hash = '".$blockInfo->hash."';")->fetch_assoc();
        if (empty($info_block_chaindata)) {

            //Check if exist previous
            $block_previous = "";
            if ($blockInfo->previous != null)
                $block_previous = $blockInfo->previous;

            //Start Transactions
            $this->db->begin_transaction();

            //SQL Insert Block
            $sql_insert_block = "INSERT INTO blocks (height,block_previous,block_hash,root_merkle,nonce,timestamp_start_miner,timestamp_end_miner,difficulty,version,info)
            VALUES (".$blockNum.",'".$block_previous."','".$blockInfo->hash."','".$blockInfo->merkle."','".$blockInfo->nonce."','".$blockInfo->timestamp."','".$blockInfo->timestamp_end."','".$blockInfo->difficulty."','".$this->GetConfig('node_version')."','".$this->db->real_escape_string(@serialize($blockInfo->info))."');";

            //Add block into blockchain
            if ($this->db->query($sql_insert_block)) {

                foreach ($blockInfo->transactions as $transaction) {

                    $wallet_from_pubkey = "";
                    $wallet_from = "";
                    if ($transaction->from != null) {
                        $wallet_from_pubkey = $transaction->from;
                        $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
                    }

                    $sql_insert_transaction = "INSERT INTO transactions (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, data, gasLimit, gasPrice, timestamp, version)
                    VALUES ('".$blockInfo->hash."','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->data."',".$transaction->gasLimit.",".$transaction->gasPrice.",'".$transaction->timestamp."','".$transaction->version."');";
					if (!$this->db->query($sql_insert_transaction)) {
                        $error = true;
                        break;
                    }

					$outOfGas = false;
					$totalGasTxn = Gas::calculateGasTxn($this,$transaction->to,$transaction->data);
					if ($totalGasTxn < $transaction->gasLimit) {
						$outOfGas = true;
					}

					//Update Account FROM
					if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
						//Calculate fee of txn (with max gasLimit/gasUsed * gasPrice)
						$feeTXN = $transaction->GetFee($this);

						//If there is not enough gas we will only charge fees
						if ($outOfGas) {
							$sql_updateAccountFrom = "
							INSERT INTO accounts (hash,sended,received,mined,fees)
							VALUES ('".$wallet_from."',0,0,0,'".$feeTXN."')
							ON DUPLICATE KEY UPDATE fees = fees + VALUES(fees);
							";
						}
						else {
							$sql_updateAccountFrom = "
							INSERT INTO accounts (hash,sended,received,mined,fees)
							VALUES ('".$wallet_from."','".$transaction->amount."',0,0,'".$feeTXN."')
							ON DUPLICATE KEY UPDATE sended = sended + VALUES(sended), fees = fees + VALUES(fees);
							";
						}
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}
					}
					//Update Account TO
					if (strlen($transaction->to) > 0) {
						// MINED BLOCK
						if (strlen($wallet_from) == 0) {
							$sql_updateAccountTo = "
							INSERT INTO accounts (hash,sended,received,mined,fees)
							VALUES ('".$transaction->to."',0,0,'".$transaction->amount."',0)
							ON DUPLICATE KEY UPDATE mined = mined + VALUES(mined);
							";
						}
						else {
							//If there is not enough gas you will not receive anything
							if ($outOfGas) {
								$sql_updateAccountTo = "
								INSERT INTO accounts (hash,sended,received,mined,fees)
								VALUES ('".$transaction->to."',0,0,0,0)
								ON DUPLICATE KEY UPDATE received = received + VALUES(received);
								";
							}
							else {
								$sql_updateAccountTo = "
								INSERT INTO accounts (hash,sended,received,mined,fees)
								VALUES ('".$transaction->to."',0,'".$transaction->amount."',0,0)
								ON DUPLICATE KEY UPDATE received = received + VALUES(received);
								";
							}
						}
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}

                    //Remove transaction from pool
                    $this->removeTxnFromPool($transaction->message());
                }
            }
            else {
                $error = true;
            }
        }

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, block added
        else {
            $this->db->commit();
            return true;
        }
    }

    /**
     * Remove block, transactions, smart contract, internal transactions
     *
     * @param int $height
     * @return bool
     */
    public function RemoveBlock(int $height) : bool {

        $error = false;

        $infoBlock = $this->db->query("SELECT block_hash FROM blocks WHERE height = '".$height."';")->fetch_assoc();
        if (!empty($infoBlock)) {

			//Start Transactions
			$this->db->begin_transaction();

			//Get Hash of TXN to NewContract
			$sql_createContracts = "
			SELECT txn_hash
			FROM transactions
			WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
			AND data <> '0x'
			AND block_hash = '".$infoBlock['block_hash']."';";
			$tmp_createContracts = $this->db->query($sql_createContracts);
			$txnHashs = [];
	        if (!empty($tmp_createContracts))
	            while ($contractInfo = $tmp_createContracts->fetch_array(MYSQLI_ASSOC))
	                $txnHashs[] = $contractInfo['txn_hash'];
			$txn_in_newContract = '';
			foreach ($txnHashs as $txn) {
				if (strlen($txn_in_newContract) > 0) $txn_in_newContract .= ',';
				$txn_in_newContract .= "'".$txn."'";
			}

			//Get Hash of TXN to Call Contract
			$sql_getCallContracts = "
			SELECT txn_hash
			FROM transactions
			WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
			AND data <> '0x'
			AND block_hash = '".$infoBlock['block_hash']."';";
			$tmp_callContracts = $this->db->query($sql_getCallContracts);
			$txnHashs = [];
	        if (!empty($tmp_callContracts))
	            while ($contractInfo = $tmp_callContracts->fetch_array(MYSQLI_ASSOC))
	                $txnHashs[] = $contractInfo['txn_hash'];

			$txn_in_callContract = '';
			foreach ($txnHashs as $txn) {
				if (strlen($txn_in_callContract) > 0) $txn_in_callContract .= ',';
				$txn_in_callContract .= "'".$txn."'";
			}

			//Reverse New Contracts
			if (strlen($txn_in_newContract) > 0) {
				//Get new contracts of this block
				$sql_createContracts = "
				SELECT contract_hash FROM smart_contracts WHERE txn_hash IN (".$txn_in_newContract.");
				";
				$tmp_createContracts = $this->db->query($sql_createContracts);
				$newContracts = [];
				if (!empty($tmp_createContracts)) {
					while ($contractInfo = $tmp_createContracts->fetch_array(MYSQLI_ASSOC)) {
						$newContracts[] = $contractInfo;
					}
				}
				//Remove stateMachine of this contracts
				foreach ($newContracts as $contract) {
					$stateMachine = SmartContractStateMachine::store($contract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
					$stateMachine->deleteStates();
				}
				//Remove new SmartContracts of this block
				$sqlRemoveSmartContracts = "
				DELETE FROM smart_contracts WHERE txn_hash IN (".$txn_in_newContract.");
				";
				if (!$this->db->query($sqlRemoveSmartContracts))
					$error = true;

				//Reverse account status with this internal transactions of this smart contracts
				$sql_selectRemoveInterntalTransactionsCreateContracts = "
				SELECT * FROM smart_contracts_txn WHERE txn_hash IN (".$txn_in_newContract.");
				";
				$tmp_InternalTxns = $this->db->query($sql_selectRemoveInterntalTransactionsCreateContracts);
				if (!empty($tmp_InternalTxns)) {
					while ($internalTxn = $tmp_InternalTxns->fetch_array(MYSQLI_ASSOC)) {

						$sql_updateAccountTo = "
						UPDATE accounts_j4frc10 SET
						received = received - '".$internalTxn['amount']."'
						WHERE contract_hash = '".$internalTxn['contract_hash']."'
						AND hash = '".$internalTxn['wallet_to']."';
						";
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}
				}
				//Remove internal transactions of this smart contracts
				$sqlRemoveInterntalTransactionsCreateContracts = "
				DELETE FROM smart_contracts_txn WHERE txn_hash IN (".$txn_in_newContract.");
				";
				if (!$this->db->query($sqlRemoveInterntalTransactionsCreateContracts))
					$error = true;


				//Reverse account status with this internal transactions token of this smart contracts
				$sql_selectRemoveInterntalTransactionsCreateContracts = "
				SELECT * FROM smart_contracts_txn_token WHERE txn_hash IN (".$txn_in_newContract.");
				";
				$tmp_InternalTxns = $this->db->query($sql_selectRemoveInterntalTransactionsCreateContracts);
				if (!empty($tmp_InternalTxns)) {
					while ($internalTxn = $tmp_InternalTxns->fetch_array(MYSQLI_ASSOC)) {

						$sql_updateAccountTo = "
						DELETE FROM accounts_j4frc20 WHERE tokenId = '".$internalTxn['tokenId']."' AND contract_hash = '".$internalTxn['contract_hash']."';
						";
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}
				}
				//Remove internal transactions tokens of this smart contracts
				$sqlRemoveInterntalTransactionsTokenCreateContracts = "
				DELETE FROM smart_contracts_txn_token WHERE txn_hash IN (".$txn_in_newContract.");
				";
				if (!$this->db->query($sqlRemoveInterntalTransactionsTokenCreateContracts))
					$error = true;
			}

			//Reverse Call Contracts
			if (strlen($txn_in_callContract) > 0) {
				//Get transactions call to contracts
				$sql_callContracts = "
				SELECT wallet_to as contract_hash
				FROM transactions WHERE txn_hash IN (".$txn_in_callContract.");
				";
				$tmp_callContracts = $this->db->query($sql_callContracts);
				$callContracts = [];
				if (!empty($tmp_createContracts))
					while ($contractInfo = $tmp_callContracts->fetch_array(MYSQLI_ASSOC))
						$callContracts[] = $contractInfo;

				//Reverse state of this contracts
				foreach ($callContracts as $callContract) {
					$stateMachine = SmartContractStateMachine::store($callContract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
					$stateMachine->reverseState();
				}

				// J4FRC10
				//Reverse account status with this internal transactions of this smart contracts
				$sql_selectRemoveInterntalTransactionsCallContracts = "
				SELECT * FROM smart_contracts_txn WHERE txn_hash IN (".$txn_in_callContract.");";
				$tmp_InternalTxnsCalls = $this->db->query($sql_selectRemoveInterntalTransactionsCallContracts);
				if (!empty($tmp_InternalTxnsCalls)) {
					while ($internalTxnCall = $tmp_InternalTxnsCalls->fetch_array(MYSQLI_ASSOC)) {

						$sql_updateAccountFrom = "
						UPDATE accounts_j4frc10 SET
						sended = sended - '".$internalTxnCall['amount']."'
						WHERE contract_hash = '".$internalTxnCall['contract_hash']."'
						AND hash = '".$internalTxnCall['wallet_from']."';
						";
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}

						$sql_updateAccountTo = "
						UPDATE accounts_j4frc10 SET
						received = received - '".$internalTxnCall['amount']."'
						WHERE contract_hash = '".$internalTxnCall['contract_hash']."'
						AND hash = '".$internalTxnCall['wallet_to']."';
						";
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}

					}
				}
				//Remove internal transactions of this smart contracts
				$sqlRemoveInterntalTransactionsCallContracts = "
				DELETE FROM smart_contracts_txn WHERE txn_hash IN (".$txn_in_callContract.");
				";
				if (!$this->db->query($sqlRemoveInterntalTransactionsCallContracts))
					$error = true;


				// J4FRC20
				//Reverse account status with this internal transactions token of this smart contracts
				$sql_selectRemoveInterntalTransactionsCallContracts = "
				SELECT * FROM smart_contracts_txn_token WHERE txn_hash IN (".$txn_in_callContract.");";
				$tmp_InternalTxnsCalls = $this->db->query($sql_selectRemoveInterntalTransactionsCallContracts);
				if (!empty($tmp_InternalTxnsCalls)) {
					while ($internalTxnCall = $tmp_InternalTxnsCalls->fetch_array(MYSQLI_ASSOC)) {
						$sql_removeTokenId = "
						DELETE FROM accounts_j4frc20 WHERE tokenId = '".$internalTxnCall['tokenId']."' AND contract_hash = '".$internalTxnCall['contract_hash']."';
						";
						if (!$this->db->query($sql_removeTokenId)) {
							$error = true;
							break;
						}
					}
				}
				//Remove internal transactions of this smart contracts
				$sqlRemoveInterntalTransactionsTokenCallContracts = "
				DELETE FROM smart_contracts_txn_token WHERE txn_hash IN (".$txn_in_callContract.");
				";
				if (!$this->db->query($sqlRemoveInterntalTransactionsTokenCallContracts))
					$error = true;
			}

			//Reverse account status of this transactions
			$sql_selectTransactionsToReverse = "SELECT * FROM transactions WHERE block_hash = '".$infoBlock['block_hash']."';";
			$tmp_TxnsToReverse = $this->db->query($sql_selectTransactionsToReverse);
			if (!empty($tmp_TxnsToReverse)) {
				while ($txnToReverse = $tmp_TxnsToReverse->fetch_array(MYSQLI_ASSOC)) {

					//Mine txn
					if ($txnToReverse['wallet_from'] == '') {
						$sql_updateAccountFrom = "
						UPDATE accounts SET
						mined = mined - '".$txnToReverse['amount']."'
						WHERE hash = '".$txnToReverse['wallet_to']."';
						";
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}
					}

					//Normal Txn
					else {
						$tmpTxn = Transaction::withGas($txnToReverse['wallet_from'],$txnToReverse['wallet_to'],$txnToReverse['amount'],"","",$txnToReverse['data'],$txnToReverse['gasLimit'],$txnToReverse['gasPrice'], true, $txnToReverse['hash'],$txnToReverse['signature'],$txnToReverse['timestamp']);
						$feeTXN = $tmpTxn->GetFee($this);

						$outOfGas = false;
						$totalGasTxn = Gas::calculateGasTxn($this,$tmpTxn->to,$tmpTxn->data);
						if ($totalGasTxn < $tmpTxn->gasLimit) {
							$outOfGas = true;
						}

						//Reverse From Account
						if ($outOfGas) {
							$sql_updateAccountFrom = "
							UPDATE accounts SET
							fees = fees - '".$feeTXN."'
							WHERE hash = '".$txnToReverse['wallet_from']."';
							";
						}
						else {
							$sql_updateAccountFrom = "
							UPDATE accounts SET
							sended = sended - '".$txnToReverse['amount']."',
							fees = fees - '".$feeTXN."'
							WHERE hash = '".$txnToReverse['wallet_from']."';
							";
						}
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}

						//Reverse To Account
						if ($outOfGas) {
							$sql_updateAccountTo = "
							UPDATE accounts SET
							received = received
							WHERE hash = '".$txnToReverse['wallet_to']."';
							";
						}
						else {
							$sql_updateAccountTo = "
							UPDATE accounts SET
							received = received - '".($txnToReverse['amount'])."'
							WHERE hash = '".$txnToReverse['wallet_to']."';
							";
						}
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}
				}
			}

            //Remove all transactions of block
            $sqlRemoveTransactions = "DELETE FROM transactions WHERE block_hash = '".$infoBlock['block_hash']."';";
            if (!$this->db->query($sqlRemoveTransactions))
                $error = true;

			//Remove block
            $sqlRemoveBlock = "DELETE FROM blocks WHERE block_hash = '".$infoBlock['block_hash']."';";
            if (!$this->db->query($sqlRemoveBlock))
                $error = true;
        }

        //Rollback transaction
        if ($error) {
			$this->db->rollback();
		}
		else {
			//$this->db->commit();
			return true;
		}
        return false;
    }

    /**
     * Remove block, transactions, smart contract, internal transactions
     *
     * @param string $hash
     * @return bool
     */
    public function RemoveBlockByHash(string $hash) : bool {

        $error = false;

        $infoBlock = $this->db->query("SELECT height FROM blocks WHERE hash = '".$hash."';")->fetch_assoc();
        if (!empty($infoBlock)) {
			return $this->RemoveBlock($infoBlock['height']);
		}
		return false;
    }

    /**
     * Remove block, transactions, smart contract, internal transactions given a height
     *
     * @param int $height
     */
    public function RemoveLastBlocksFrom(int $height) : bool {
		$lastBlock = $this->GetLastBlock();
		if (@is_array($lastBlock) && !@empty($lastBlock)) {
			if ($height > 0) {
				for ($i = $lastBlock['height']; $i > $height; $i--) {
					$this->RemoveBlock($i);
				}
				return true;
			}
		}
		return false;
    }

    /**
     * Returns the next block number in the block chain
     * Must be the number entered in the next block
     *
     * @return int
     */
    public function GetNextBlockNum() : int {
        return intval($this->db->query("SELECT COUNT(height) as NextBlockNum FROM blocks")->fetch_assoc()['NextBlockNum']);
    }

    /**
     * Returns the GENESIS block
     *
     * @return array
     */
    public function GetGenesisBlock() : array {
        $genesis_block = [];
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks WHERE height = 0");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

				//TMP-FIX
				$transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;");
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $genesis_block = $blockInfo;
            }
        }
        return $genesis_block;

    }

    /**
     * Returns last block
     *
     * @param bool $withTransactions
     *
     * @return array
     */
    public function GetLastBlock(bool $withTransactions=true) : array {
        $lastBlock = [];
        $infoLastBlock = $this->db->query("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($infoLastBlock)) {
            while ($blockInfo = $infoLastBlock->fetch_array(MYSQLI_ASSOC)) {

                $transactions = array();

                //Select only hashes of txns
                $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

                //If want all transaction info, select all
                if ($withTransactions)
                    $sql = "SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";

                //Get transactions
                $transactions_chaindata = $this->db->query($sql);
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        if ($withTransactions)
                            $transactions[] = $transactionInfo;
                        else
                            $transactions[] = $transactionInfo['txn_hash'];
                    }
                }

				//TMP FIX
                $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";
                if ($withTransactions)
                    $sql = "SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;";
                $transactions_chaindata = $this->db->query($sql);
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        if ($withTransactions)
                            $transactions[] = $transactionInfo;
                        else
                            $transactions[] = $transactionInfo['txn_hash'];
                    }
                }

                $blockInfo["transactions"] = $transactions;
                $lastBlock = $blockInfo;
            }
        }
        return $lastBlock;

    }

    /**
     * Returns the blocks to be synchronized from the block passed by parameter
     *
     * @param int $fromBlock
     * @return array
     */
    public function SyncBlocks(int $fromBlock) : array {
        $blocksToSync = array();
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks ORDER BY height ASC LIMIT ".$fromBlock.",100");

        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' and wallet_from = '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

				//TMP FIX
				$transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' and wallet_from <> '' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;");
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $blocksToSync[] = $blockInfo;
            }
        }
        return $blocksToSync;
    }

	/**
     * Get Avg time block from height
     *
     * @param $height
     * @return float
     */
	public function GetAvgBlockTime(int $height) : float {

		$totalTimeMined = 0;
		$numBlocks = 0;

		$sql = "SELECT timestamp_start_miner,timestamp_end_miner FROM blocks WHERE height >= '".$height."' ORDER BY height ASC";
		$blocks = $this->db->query($sql);
		if (!empty($blocks)) {
            while ($blockInfo = $blocks->fetch_array(MYSQLI_ASSOC)) {
				$numBlocks++;
				$totalTimeMined += $blockInfo['timestamp_end_miner'] - $blockInfo['timestamp_start_miner'];
			}
		}

		return ceil($totalTimeMined / $numBlocks);
	}
}

?>
