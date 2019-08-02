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

class DBBlocks extends DBContracts {

    /**
     * Get miner of block
     *
     * @param $hash
     * @return mixed
     */
    public function GetMinerOfBlockByHash($hash) {
        $minerTransaction = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$hash."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC LIMIT 1;")->fetch_assoc();
        if (!empty($minerTransaction))
            return $minerTransaction['wallet_to'];
        return null;
    }

    /**
     * Returns a block given a hash
     *
     * @param $hash
     * @param $withTransactions
     * @return array
     */
    public function GetBlockByHash($hash,$withTransactions=false) {
        $sql = "SELECT * FROM blocks WHERE block_hash = '".$hash."'";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block)) {

            $transactions = array();

            //Select only hashes of txns
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

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
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";

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
        return null;
    }

    /**
     * Returns a block given a hash
     *
     * @param $hash
     * @return array
     */
    public function GetBlockHeightByHash($hash) {
        $sql = "SELECT height FROM blocks WHERE block_hash = '".$hash."' LIMIT 1;";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block))
            return $info_block['height'];
        return null;
    }

    /**
     * Returns a block given a height
     *
     * @param $height
     * @param $withTransactions
     * @return mixed
     */
    public function GetBlockByHeight($height,$withTransactions=true) {

        $sql = "SELECT * FROM blocks WHERE height = ".$height.";";
        $info_block = $this->db->query($sql)->fetch_assoc();

        if (!empty($info_block)) {

            $transactions = array();

            //Select only hashes of txns
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

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
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";

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
        return null;
    }

    /**
     * Add a block in the chaindata
     *
     * @param int $blockNum
     * @param Block $blockInfo
     * @return bool
     */
    public function addBlock($blockNum,$blockInfo) {

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

                    $sql_update_transactions = "INSERT INTO transactions (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp)
                    VALUES ('".$blockInfo->hash."','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->data."','".$transaction->timestamp."');";
                    if (!$this->db->query($sql_update_transactions)) {
                        $error = true;
                        break;
                    }

					//Update Account FROM
					if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
						$sql_updateAccountFrom = "
						INSERT INTO accounts (hash,sended,received,mined)
						VALUES ('".$wallet_from."','".$transaction->amount."' + '".$transaction->tx_fee."',0,0)
						ON DUPLICATE KEY UPDATE sended = sended + ".$transaction->amount." + ".$transaction->tx_fee.";
						";
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
							INSERT INTO accounts (hash,sended,received,mined)
							VALUES ('".$transaction->to."',0,0,'".$transaction->amount."')
							ON DUPLICATE KEY UPDATE mined = mined + VALUES(mined);
							";
						}
						else {
							$sql_updateAccountTo = "
							INSERT INTO accounts (hash,sended,received,mined)
							VALUES ('".$transaction->to."',0,'".$transaction->amount."',0)
							ON DUPLICATE KEY UPDATE received = received + VALUES(received);
							";
						}

						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}

                    //We eliminated the pending transaction
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
     * Remove block and transactions
     *
     * @param int $height
     * @return bool
     */
    public function RemoveBlock($height) {

        $error = false;

        $infoBlock = $this->db->query("SELECT block_hash FROM blocks WHERE height = '".$height."';")->fetch_assoc();
        if (!empty($infoBlock)) {

			//Start Transactions
			$this->db->begin_transaction();

			//Get new contracts of this block
			$sql_createContracts = "
			SELECT contract_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveSmartContracts))
				$error = true;

			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCreateContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCreateContracts))
				$error = true;

			//Get transactions call to contracts
			$sql_callContracts = "
			SELECT contract_hash, txn_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			$tmp_callContracts = $this->db->query($sql_callContracts);
			$callContracts = [];
	        if (!empty($tmp_createContracts)) {
	            while ($contractInfo = $tmp_callContracts->fetch_array(MYSQLI_ASSOC)) {
	                $callContracts[] = $contractInfo;
	            }
	        }
			//Reverse state of this contracts
			foreach ($callContracts as $callContract) {
				$stateMachine = SmartContractStateMachine::store($callContract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
				$stateMachine->reverseState();
			}
			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCallContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCallContracts))
				$error = true;

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

					//Noram Txn
					else {

						$sql_updateAccountFrom = "
						UPDATE accounts SET
						sended = sended - ".$txnToReverse['amount'].",
						sended = sended - ".$txnToReverse['tx_fee']."
						WHERE hash = '".$txnToReverse['wallet_from']."';
						";
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}

						$sql_updateAccountTo = "
						UPDATE accounts SET
						received = received - '".$txnToReverse['amount']."'
						WHERE hash = '".$txnToReverse['wallet_to']."';
						";
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
        if ($error)
            $this->db->rollback();
		else {
			$this->db->commit();
			return true;
		}
        return false;
    }

    /**
     * Remove block and transactions
     *
     * @param string $hash
     * @return bool
     */
    public function RemoveBlockByHash($hash) {

        $error = false;

        $infoBlock = $this->db->query("SELECT block_hash FROM blocks WHERE hash = '".$hash."';")->fetch_assoc();
        if (!empty($infoBlock)) {

			//Start Transactions
			$this->db->begin_transaction();

			//Get new contracts of this block
			$sql_createContracts = "
			SELECT contract_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveSmartContracts))
				$error = true;

			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCreateContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCreateContracts))
				$error = true;

			//Get transactions call to contracts
			$sql_callContracts = "
			SELECT contract_hash, txn_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			$tmp_callContracts = $this->db->query($sql_callContracts);
			$callContracts = [];
	        if (!empty($tmp_createContracts)) {
	            while ($contractInfo = $tmp_callContracts->fetch_array(MYSQLI_ASSOC)) {
	                $callContracts[] = $contractInfo;
	            }
	        }
			//Reverse state of this contracts
			foreach ($callContracts as $callContract) {
				$stateMachine = SmartContractStateMachine::store($callContract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
				$stateMachine->reverseState();
			}
			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCallContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);";
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
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCallContracts))
				$error = true;

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

					//Noram Txn
					else {

						$sql_updateAccountFrom = "
						UPDATE accounts SET
						sended = sended - ".$txnToReverse['amount'].",
						sended = sended - ".$txnToReverse['tx_fee']."
						WHERE hash = '".$txnToReverse['wallet_from']."';
						";
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}

						$sql_updateAccountTo = "
						UPDATE accounts SET
						received = received - '".$txnToReverse['amount']."'
						WHERE hash = '".$txnToReverse['wallet_to']."';
						";
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}

				}
			}
            //Remove all transactions of block
            $sqlRemoveTransactions = "DELETE FROM transactions WHERE block_hash = '".$infoBlock['block_hash']."';";
            if ($this->db->query($sqlRemoveTransactions)) {
                //Remove block
                $sqlRemoveBlock = "DELETE FROM blocks WHERE block_hash = '".$infoBlock['block_hash']."';";
                if (!$this->db->query($sqlRemoveBlock)) {
                    $error = true;
                }
            }
            else
                $error = true;
        }

        //Rollback transaction
        if ($error)
            $this->db->rollback();
		else {
			$this->db->commit();
			return true;
		}
        return false;
    }

    /**
     * Remove block and transactions
     *
     * @param int $height
     */
    public function RemoveLastBlocksFrom($height) {

		$error = false;

		$infoBlock = $this->db->query("SELECT block_hash FROM blocks WHERE height = '".$height."';")->fetch_assoc();
		if (!empty($infoBlock)) {

			//Start Transactions
			$this->db->begin_transaction();

			//Get new contracts of this block
			$sql_createContracts = "
			SELECT contract_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);";
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
			DELETE FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);
			";
			if (!$this->db->query($sqlRemoveSmartContracts))
				$error = true;

			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCreateContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);";
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
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCreateContracts))
				$error = true;

			//Get transactions call to contracts
			$sql_callContracts = "
			SELECT contract_hash, txn_hash
			FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);
			";
			$tmp_callContracts = $this->db->query($sql_callContracts);
			$callContracts = [];
			if (!empty($tmp_createContracts)) {
				while ($contractInfo = $tmp_callContracts->fetch_array(MYSQLI_ASSOC)) {
					$callContracts[] = $contractInfo;
				}
			}
			//Reverse account status with this internal transactions of this smart contracts
			$sql_selectRemoveInterntalTransactionsCallContracts = "
			SELECT * FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);";
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
			//Reverse state of this contracts
			foreach ($callContracts as $callContract) {
				$stateMachine = SmartContractStateMachine::store($callContract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
				$stateMachine->reverseState();
			}
			//Remove internal transactions of this smart contracts
			$sqlRemoveInterntalTransactionsCallContracts = "
			DELETE FROM smart_contracts_txn WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to <> 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = IN (
					SELECT block_hash FROM blocks WHERE height > ".$height."
				)
			);
			";
			if (!$this->db->query($sqlRemoveInterntalTransactionsCallContracts))
				$error = true;

			//Reverse account status of this transactions
			$sql_selectTransactionsToReverse = "SELECT * FROM transactions WHERE block_hash IN (
				SELECT block_hash FROM blocks WHERE height > ".$height."
			);";
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

					//Noram Txn
					else {

						$sql_updateAccountFrom = "
						UPDATE accounts SET
						sended = sended - ".$txnToReverse['amount'].",
						sended = sended - ".$txnToReverse['tx_fee']."
						WHERE hash = '".$txnToReverse['wallet_from']."';
						";
						if (!$this->db->query($sql_updateAccountFrom)) {
							$error = true;
							break;
						}

						$sql_updateAccountTo = "
						UPDATE accounts SET
						received = received - '".$txnToReverse['amount']."'
						WHERE hash = '".$txnToReverse['wallet_to']."';
						";
						if (!$this->db->query($sql_updateAccountTo)) {
							$error = true;
							break;
						}
					}

				}
			}

			//Remove transactions
			$sql_removeAllTransactions = "
			DELETE FROM transactions WHERE block_hash IN (
				SELECT block_hash FROM blocks WHERE height > ".$height."
			);
			";
			if (!$this->db->query($sql_removeAllTransactions))
				$error = true;

			//Remove blocks
			if (!$this->db->query("DELETE FROM blocks WHERE height > ".$height))
				$error = true;
		}

		//Rollback transaction
		if ($error)
		$this->db->rollback();
		else {
			$this->db->commit();
			return true;
		}
		return false;
    }

    /**
     * Returns the next block number in the block chain
     * Must be the number entered in the next block
     *
     * @return mixed
     */
    public function GetNextBlockNum() {
        return $this->db->query("SELECT COUNT(height) as NextBlockNum FROM blocks")->fetch_assoc()['NextBlockNum'];
    }

    /**
     * Returns the GENESIS block
     *
     * @return mixed
     */
    public function GetGenesisBlock() {
        $genesis_block = null;
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks WHERE height = 0");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

				//TMP-FIX
				$transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;");
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
     * @param $withTransactions
     *
     * @return mixed
     */
    public function GetLastBlock($withTransactions=true) {
        $lastBlock = null;
        $infoLastBlock = $this->db->query("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($infoLastBlock)) {
            while ($blockInfo = $infoLastBlock->fetch_array(MYSQLI_ASSOC)) {

                $transactions = array();

                //Select only hashes of txns
                $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

                //If want all transaction info, select all
                if ($withTransactions)
                    $sql = "SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from = '' ORDER BY tx_fee DESC, timestamp DESC;";

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
                $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";
                if ($withTransactions)
                    $sql = "SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' AND wallet_from <> '' ORDER BY tx_fee DESC, timestamp DESC;";
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
     * @param $fromBlock
     * @return array
     */
    public function SyncBlocks($fromBlock) {
        $blocksToSync = array();
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks ORDER BY height ASC LIMIT ".$fromBlock.",100");

        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' and wallet_from = '' ORDER by tx_fee DESC, timestamp DESC;");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

				//TMP FIX
				$transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' and wallet_from <> '' ORDER by tx_fee DESC, timestamp DESC;");
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
}

?>