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

class DB extends DBBase {

    public $db;

    /**
     * DB constructor.
     */
    public function __construct() {

        //We create or load the database
        $this->db = @new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME, DB_PORT);

        //Check if have error on connect to mysql server
        if (mysqli_connect_errno())
            return null;
    }

    /**
     * @param $table
     * @return bool
     */
    public function truncate($table) {
        if ($this->db->query("TRUNCATE TABLE " . $table.";"))
            return true;
        return false;
    }

    /**
     * Get all config
     *
     * @return array
     */
    public function GetAllConfig() {
        $_CONFIG = array();
        $query = $this->db->query("SELECT cfg, val FROM config");
        if (!empty($query)) {
            while ($cfg = $query->fetch_array(MYSQLI_ASSOC))
                $_CONFIG[$cfg['cfg']] = trim($cfg['val']);
        }
        return $_CONFIG;
    }

    /**
     * Get config
     *
     * @param $key
     * @return string
     */
    public function GetConfig($key) {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (!empty($currentConfig)) {
            return $currentConfig['val'];
        }
        return null;
    }

    /**
     * Save config on database
     *
     * @param $key
     * @param $value
     */
    public function SetConfig($key,$value) {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (empty($currentConfig)) {
            $this->db->query("INSERT INTO config (cfg,val) VALUES ('".$key."', '".$value."');");
        }
        else {
            $this->db->query("UPDATE config SET val='".$value."' WHERE cfg='".$key."'");
        }
    }

    /**
     * Get current network
     *
     * @return string
     */
    public function GetNetwork() {
        $currentNetwork = $this->db->query("SELECT val FROM config WHERE cfg = 'network';")->fetch_assoc();
        if (!empty($currentNetwork))
            return strtolower($currentNetwork['val']);
        return "mainnet";
    }

    /**
     * @return bool|mixed
     */
    public function GetBootstrapNode() {
        $info_mined_blocks_by_peer = $this->db->query("SELECT * FROM peers ORDER BY id ASC LIMIT 1;")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Add a peer to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (empty($info_mined_blocks_by_peer)) {
            $this->db->query("INSERT INTO peers (ip,port) VALUES ('".$ip."', '".$port."');");
            return true;
        }
        return false;
    }

    /**
     * Returns whether or not we have this peer saved in the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function haveThisPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return true;
        }
        return false;
    }

    /**
     * Save config on database
     *
     * @param $ipAndPort
     */
    public function addPeerToBlackList($ipAndPort) {

		/*
        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        if (strlen($ip) > 0 && strlen($port) > 0) {
            $currentInfoPeer = $this->db->query("SELECT id FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();

            //Ban peer 10min
            $blackListTime = time() + 5 * 60;
            if (empty($currentInfoPeer)) {
                $this->db->query("INSERT INTO peers (ip,port,blacklist) VALUES ('".$ip."', '".$port."', '".$blackListTime."');");
            }
            else {
                $this->db->query("UPDATE peers SET blacklist='".$blackListTime."' WHERE ip = '".$ip."' AND port = '".$port."';");
            }
        }
		*/
    }

    /**
     * Remove a peer from the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            if ($this->db->query("DELETE FROM peers WHERE ip = '".$ip."' AND port= '".$port."';"))
                return true;
        }
        return false;
    }

    /**
     * Returns an array with all the peers
     *
     * @return array
     */
    public function GetAllPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." ORDER BY id");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $ip = str_replace("\r","",$peer['ip']);
                $ip = str_replace("\n","",$ip);

                $port = str_replace("\r","",$peer['port']);
                $port = str_replace("\n","",$port);

                $infoPeer = array(
                    'ip' => $ip,
                    'port' => $port
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

	/**
     * Returns an array with all the peers without bootstrap node peer
     *
     * @return array
     */
    public function GetAllPeersWithoutBootstrap() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." ORDER BY id");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
				if ($peer['ip'] == NODE_BOOTSTRAP || $peer['ip'] == NODE_BOOTSTRAP_TESTNET)
					continue;

                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Returns an array with 25 random peers
     *
     * @return array
     */
    public function GetPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." LIMIT 25");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

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
     * Returns a transaction given a hash
     *
     * @param $hash
     * @return mixed
     */
    public function GetTransactionByHash($hash) {
        $sql = "SELECT * FROM transactions WHERE txn_hash = '".$hash."';";
        $info_txn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return null;
    }

    /**
     * Returns the information of a wallet
     *
     * @param $wallet
     * @return array
     */
    public function GetWalletInfo($wallet) {

		$totalSpend = $totalReceivedReal = $current = $totalReceived = 0;

		$walletInfo = $this->db->query("SELECT * FROM accounts WHERE hash = '".$wallet."';")->fetch_assoc();
        if (!empty($walletInfo)) {
			$totalSpend = uint256::parse($walletInfo['sended']);
			$totalReceived = uint256::parse($walletInfo['received']);
			$totalMined = uint256::parse($walletInfo['mined']);
			$totalMinedAndReceived = @bcadd($walletInfo['received'],$walletInfo['mined'],18);
			$current = uint256::parse(@bcsub($totalMinedAndReceived,$walletInfo['sended'],18));
        }

		return array(
            'sended' => $totalSpend,
            'received' => $totalReceived,
			'mined' => $totalReceivedReal,
            'current' => $current
        );

    }

	/**
	 * Returns the tokens of a wallet
	 *
	 * @param $wallet
	 * @return array
	 */
	public function GetWalletTokens($wallet) {

		$tokens = array();

		$tokensAccount = $this->db->query("SELECT * FROM accounts_j4frc10 WHERE hash = '".$wallet."';");
		if (!empty($tokensAccount)) {
			while ($tokenAccountInfo = $tokensAccount->fetch_array(MYSQLI_ASSOC)) {
				$tokenHash = $tokenAccountInfo['contract_hash'];

				$totalSpend = uint256::parse($tokenAccountInfo['sended']);
				$totalReceivedReal = uint256::parse($tokenAccountInfo['received']);
				$current = uint256::parse(bcsub($tokenAccountInfo['received'],$tokenAccountInfo['sended'],18));


				$tokens[$tokenHash]['info'] = array(
		            'sended' => $totalSpend,
		            'received' => $totalReceivedReal,
		            'current' => $current
		        );
			}
		}

		foreach ($tokens as $tokenHash=>$tokenInfo) {

			$contractInfo = $this->GetContractByHash($tokenHash);
			$tokenDefines = J4FVM::getTokenDefine(Tools::hex2str($contractInfo['code']));

			$tokens[$tokenHash]['Token'] = trim($tokenDefines['Token']);
			$tokens[$tokenHash]['Name'] = trim($tokenDefines['Name']);
		}

		return $tokens;
	}

    /**
     * Returns all the transactions of a wallet
     *
     * @param $wallet
     * @param $limit
     * @return array
     */
    public function GetTransactionsByWallet($wallet,$limit=50) {
        $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE wallet_to = '".$wallet."' OR wallet_from = '".$wallet."' ORDER BY timestamp DESC LIMIT ".$limit.";");
        $transactions = array();
        if (!empty($transactions_chaindata)) {
            while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $transactions[] = $transactionInfo;
            }
        }

        return $transactions;
    }

    /**
     * Return count transactions in block
     *
     * @param $blockHash
     * @return int
     */
    public function GetBlockTransactionsCountByHash($blockHash) {
        $transactionsCount = $this->db->query("SELECT count(txn_hash) as countTransactions FROM transactions WHERE block_hash = '".$blockHash."';")->fetch_assoc();
        if (!empty($transactionsCount)) {
            return $transactionsCount['countTransactions'];
        }
        return 0;
    }

    /**
     * Return count transactions in block
     *
     * @param $height
     * @return int
     */
    public function GetBlockTransactionsCountByHeight($height) {
        $transactionsCount = $this->db->query("SELECT count(txn_hash) as countTransactions FROM transactions WHERE block_hash = (SELECT block_hash FROM blocks WHERE height = ".$height.");")->fetch_assoc();
        if (!empty($transactionsCount)) {
            return $transactionsCount['countTransactions'];
        }
        return 0;
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
     * We obtain all transactions that the address_from is different from address_to
     *
     * @return array
     */
    public function GetTxnFromPool($limit=511) {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM txnpool WHERE wallet_from <> wallet_to ORDER BY tx_fee DESC, timestamp DESC LIMIT " . $limit);
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetch_array(MYSQLI_ASSOC)) {
                if ($tx_chaindata['txn_hash'] != null && strlen($tx_chaindata['txn_hash']) > 0)
                    $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

    /**
     * Add pending transactions received by a peer
     *
     * @param $transactionsByPeer
     * @return bool
     */
    public function addTxnsToPoolByPeer($transactionsByPeer) {

        foreach ($transactionsByPeer as $tx) {

            // Date of the transaction can not be longer than the local date
            if ($tx['timestamp']> Tools::GetGlobalTime())
                continue;

            //We check not sending money to itself
            if ($tx['wallet_from'] == $tx['wallet_to'])
                continue;

            $this->addTxnToPoolByPeer($tx['txn_hash'],$tx);
        }

        return true;
    }

	/**
	 * Add a pending transaction received by a peer
	 *
	 * @param $txHash
	 * @param $transaction
	 * @return bool
	 */
	public function addTxnToPoolByPeer($txHash,$transaction) {
		$infoTxnPool = $this->db->query("SELECT txn_hash FROM txnpool WHERE txn_hash = '".$txHash."' ORDER BY tx_fee DESC, timestamp DESC;")->fetch_assoc();
		if (empty($infoTxnPool)) {

			//Start Transactions
			$this->db->begin_transaction();

			$sqlAddTxnToPool = "
			INSERT INTO txnpool (txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp)
			VALUES ('".$txHash."','".$transaction['wallet_from_key']."','".$transaction['wallet_from']."','".$transaction['wallet_to']."','".$transaction['amount']."','".$transaction['signature']."','".$transaction['tx_fee']."','".$transaction['data']."','".$transaction['timestamp']."');";

			//Commit transaction
			if ($this->db->query($sqlAddTxnToPool)) {
				$this->db->commit();
				return true;
			}

			//Rollback transaction
			else
				$this->db->rollback();
		}
		return false;
	}

	/**
	 * Add a pending transaction
	 *
	 * @param $txHash
	 * @param $transaction
	 * @return bool
	 */
	public function addTxnToPool($txHash,$transaction) {
		$infoTxnPool = $this->db->query("SELECT txn_hash FROM txnpool WHERE txn_hash = '".$txHash."' ORDER BY tx_fee DESC, timestamp DESC;")->fetch_assoc();
		if (empty($infoTxnPool)) {

			$wallet_from_pubkey = "";
			$wallet_from = "";
			if ($transaction->from != null) {
				$wallet_from_pubkey = $transaction->from;
				$wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
			}

			//Start Transactions
			$this->db->begin_transaction();

			$sqlAddTxnToPool = "INSERT INTO txnpool (txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp)
					VALUES ('".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->data."','".$transaction->timestamp."');";

			//Commit transaction
			if ($this->db->query($sqlAddTxnToPool)) {
				$this->db->commit();
				return true;
			}

			//Rollback transaction
			else
				$this->db->rollback();
		}
		return false;
	}

    /**
     * Delete a transaction from pool
     *
     * @param $txHash
     */
    public function removeTxnFromPool($txHash) {
        $this->db->query("DELETE FROM txnpool WHERE txn_hash='".$txHash."';");
    }

    /**
     * Return array with all pending transactions to send
     *
     * @return array
     */
    public function GetAllTxnFromPool() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM txnpool ORDER BY tx_fee DESC, timestamp DESC");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

	/**
     * Return array with all pending transactions to send
     *
	 * @param $txHash
	 *
     * @return mixed
     */
    public function GetTxnFromPoolByHash($txHash) {
        $txnInfo = $this->db->query("SELECT * FROM txnpool WHERE txn_hash = '".$txHash."'")->fetch_assoc();
        if (!empty($txnInfo)) {
            return $txnInfo;
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

	/**
     * Add a Smart Contract in the chaindata
     *
     * @param string $contractHash
     * @param string $txn_hash
	 * @param string $codeHexBytes
	 * @param string $dataHexBytes
     * @return bool
     */
    public function addSmartContract($contractHash,$txn_hash,$codeHexBytes,$dataHexBytes) {

        $error = false;

        $info_contract_chaindata = $this->db->query("SELECT contract_hash FROM smart_contracts WHERE contract_hash = '".$contractHash."';")->fetch_assoc();
        if (empty($info_contract_chaindata)) {

            //Start MySQL Transaction
            $this->db->begin_transaction();

            //SQL Insert Contract
            $sqlInsertContract = "INSERT INTO smart_contracts (contract_hash,txn_hash,code)
            VALUES ('".$contractHash."','".$txn_hash."','".$codeHexBytes."');";

            //Add contract into blockchain
            if (!$this->db->query($sqlInsertContract)) {
                $error = true;
            }
        }

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, contract added
        else {
            $this->db->commit();

			//Save Contract State
			$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
			$stateMachine->insert($txn_hash,["state" => $dataHexBytes]);
            return true;
        }
	}

	/**
     * Remove a Smart Contract from chaindata
     *
     * @param string $contractHash
     * @return bool
     */
	public function removeSmartContract($contractHash) {
		$error = false;

        $info_contract_chaindata = $this->db->query("SELECT contract_hash FROM smart_contracts WHERE contract_hash = '".$contractHash."';")->fetch_assoc();
        if (!empty($info_contract_chaindata)) {

            //Start MySQL Transaction
            $this->db->begin_transaction();

            //SQL Remove Contract
            $sqlRemoveContract = "DELETE FROM smart_contracts WHERE contract_hash = '".$contractHash."';";

            //Remove contract from blockchain
            if (!$this->db->query($sqlRemoveContract)) {
                $error = true;
            }
        }

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, contract added
        else {
            $this->db->commit();

			//Remove Contract States
			$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
			$stateMachine->deleteStates();
            return true;
        }
	}

	/**
     * Update storedData of Contract in StateMachine
     *
     * @param string $contractHash
	 * @param string $txnHash
	 * @param string $dataHexBytes
     * @return bool
     */
    public function updateStoredDataContract($contractHash,$txnHash,$dataHexBytes) {
		//NoSQL Update storedData of Contract
		$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
		$stateMachine->insert($txnHash,["state" => $dataHexBytes]);
		return true;
	}

    /**
     * Returns a contract given a transaction hash
     *
     * @param $txn_hash
     * @return mixed
     */
    public function GetContractByTxn($txn_hash) {

        $sql = "SELECT * FROM smart_contracts WHERE txn_hash = '".$txn_hash."';";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract;
        }
        return null;
	}

    /**
     * Returns a contract given a hash
     *
     * @param $txn_hash
     * @return mixed
     */
    public function GetContractByHash($contract_hash) {

        $sql = "SELECT * FROM smart_contracts WHERE contract_hash = '".$contract_hash."';";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract;
        }
        return null;
	}

	/**
     * Returns a owner of contract given a hash
     *
     * @param $txn_hash
     * @return mixed
     */
    public function GetOwnerContractByHash($contract_hash) {

        $sql = "
		SELECT t.wallet_from
		FROM smart_contracts as sC
		LEFT JOIN transactions AS t ON t.txn_hash = sC.txn_hash
		WHERE contract_hash = '".$contract_hash."';
		";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract['wallet_from'];
        }
        return null;
	}

    /**
     * Save Internal TXN of SmartContract in Blockchain
     *
     * @param string $txn_hash
	 * @param string $contract_hash
	 * @param string $wallet_from
	 * @param string $wallet_to
	 * @param int $amount
     * @return bool
     */
	public function addInternalTransaction($txn_hash,$contract_hash,$wallet_from,$wallet_to,$amount) {

		$error = false;

		//Start Internal Transaction
		$this->db->begin_transaction();

		$timestamp = Tools::GetGlobalTime();
		$sqlInternalTxn = "INSERT INTO smart_contracts_txn (txn_hash, contract_hash, wallet_from, wallet_to, amount, timestamp)
			VALUES ('" . $txn_hash . "','" . $contract_hash . "','" . $wallet_from . "','" . $wallet_to . "','".$amount."','".$timestamp."');";

		//Commit Internal Transaction
		if (!$this->db->query($sqlInternalTxn)) {
			$error = true;
		}

		//Update Account FROM
		if (!$error) {

			//Token TXN
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (preg_match($REGEX_Address,$wallet_from)) {
				if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
					$sql_updateAccountFrom = "
					INSERT INTO accounts_j4frc10 (hash,contract_hash,sended,received)
					VALUES ('".$wallet_from."','".$contract_hash."','".$amount."',0)
					ON DUPLICATE KEY UPDATE sended = sended + VALUES(sended);
					";
					if (!$this->db->query($sql_updateAccountFrom)) {
						$error = true;
					}
				}
				//Update Account TO
				if (strlen($wallet_to) > 0) {
					$sql_updateAccountTo = "
					INSERT INTO accounts_j4frc10 (hash,contract_hash,sended,received)
					VALUES ('".$wallet_to."','".$contract_hash."',0,'".$amount."')
					ON DUPLICATE KEY UPDATE received = received + VALUES(received);
					";

					if (!$this->db->query($sql_updateAccountTo)) {
						$error = true;
					}
				}
			}
			//J4F Txn (Withdraw from contract)
			else {
				//Update Account FROM
				if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
					$sql_updateAccountFrom = "
					INSERT INTO accounts (hash,sended,received,mined)
					VALUES ('".$wallet_from."','".$amount."',0,0)
					ON DUPLICATE KEY UPDATE sended = sended + ".$amount.";
					";
					if (!$this->db->query($sql_updateAccountFrom)) {
						$error = true;
					}
				}
				//Update Account TO
				if (strlen($wallet_to) > 0) {
					$sql_updateAccountTo = "
					INSERT INTO accounts (hash,sended,received,mined)
					VALUES ('".$wallet_to."',0,'".$amount."',0)
					ON DUPLICATE KEY UPDATE received = received + VALUES(received);
					";

					if (!$this->db->query($sql_updateAccountTo)) {
						$error = true;
					}
				}
			}
		}


		//Commit Internal Transaction
		if (!$error) {
			$this->db->commit();
			return true;
		}

		//Rollback Internal Transaction
		else
			$this->db->rollback();

        return false;
	}

	/**
     * Returns a internal transaction given a txn hash and contract hash
     *
	 * @param string $contract_hash
     * @param string $txn_hash
     * @return mixed
     */
    public function GetInternalTransactionByTxnHash($contract_hash,$txn_hash) {

        $sql = "SELECT * FROM smart_contracts_txn WHERE txn_hash = '".$txn_hash."' AND contract_hash = '".$contract_hash."';";
        $info_internalTxn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_internalTxn)) {
            return $info_internalTxn;
        }
        return null;
	}
}

?>
