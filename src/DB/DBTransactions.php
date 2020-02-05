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

class DBTransactions {

    /**
     * Returns a transaction given a hash
     *
     * @param string $hash
     * @return array
     */
    public function GetTransactionByHash($hash) : array {
        $sql = "SELECT * FROM transactions WHERE txn_hash = '".$hash."';";
        $info_txn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return [];
    }

    /**
     * Returns all the transactions of a wallet
     *
     * @param string $wallet
     * @param int $limit
     * @return array
     */
    public function GetTransactionsByWallet(string $wallet,int $limit=50) : array {
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
     * @param string $blockHash
     * @return int
     */
    public function GetBlockTransactionsCountByHash(string $blockHash) : int {
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
    public function GetBlockTransactionsCountByHeight(int $height) : int {
        $transactionsCount = $this->db->query("SELECT count(txn_hash) as countTransactions FROM transactions WHERE block_hash = (SELECT block_hash FROM blocks WHERE height = ".$height.");")->fetch_assoc();
        if (!empty($transactionsCount)) {
            return $transactionsCount['countTransactions'];
        }
        return 0;
    }

    /**
     * We obtain all transactions that the address_from is different from address_to
     *
     * @return array
     */
    public function GetTxnFromPool(int $limit=511) : array {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM txnpool WHERE wallet_from <> wallet_to ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC LIMIT " . $limit);
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
     * @param array $transactionsByPeer
     * @return bool
     */
    public function addTxnsToPoolByPeer(array $transactionsByPeer) : bool {

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
	 * @param string $txHash
	 * @param array $transaction
	 * @return bool
	 */
	public function addTxnToPoolByPeer(string $txHash,array $transaction) : bool {
		$infoTxnPool = $this->db->query("SELECT txn_hash FROM txnpool WHERE txn_hash = '".$txHash."' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;")->fetch_assoc();
		if (empty($infoTxnPool)) {

			//Start Transactions
			$this->db->begin_transaction();

			$sqlAddTxnToPool = "
			INSERT INTO txnpool (txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, data, gasLimit, gasPrice, timestamp, version)
			VALUES ('".$txHash."','".$transaction['wallet_from_key']."','".$transaction['wallet_from']."','".$transaction['wallet_to']."','".$transaction['amount']."','".$transaction['signature']."','".$transaction['data']."',".$transaction['gasLimit'].",".$transaction['gasPrice'].",'".$transaction['timestamp']."','".$transaction['version']."');";

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
	 * @param string $txHash
	 * @param Transaction $transaction
	 * @return bool
	 */
	public function addTxnToPool(string $txHash,Transaction $transaction) : bool {
		$infoTxnPool = $this->db->query("SELECT txn_hash FROM txnpool WHERE txn_hash = '".$txHash."' ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC;")->fetch_assoc();
		if (empty($infoTxnPool)) {

			$wallet_from_pubkey = "";
			$wallet_from = "";
			if ($transaction->from != null) {
				$wallet_from_pubkey = $transaction->from;
				$wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
			}

			//Start Transactions
			$this->db->begin_transaction();

			$sqlAddTxnToPool = "INSERT INTO txnpool (txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, data, gasLimit, gasPrice, timestamp, version)
					VALUES ('".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->data."',".$transaction->gasLimit.",".$transaction->gasPrice.",'".$transaction->timestamp."','".$transaction->version."');";

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
     * @param string $txHash
     */
    public function removeTxnFromPool(string $txHash) : void {
        $this->db->query("DELETE FROM txnpool WHERE txn_hash='".$txHash."';");
    }

    /**
     * Return array with all pending transactions to send
     *
     * @return array
     */
    public function GetAllTxnFromPool() : array {
        $txs = [];
        $txs_chaindata = $this->db->query("SELECT * FROM txnpool ORDER BY gasPrice DESC, gasLimit DESC, timestamp DESC");
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
	 * @param string $txHash
	 *
     * @return array
     */
    public function GetTxnFromPoolByHash(string $txHash) : array {
        $txnInfo = $this->db->query("SELECT * FROM txnpool WHERE txn_hash = '".$txHash."'")->fetch_assoc();
        if (!empty($txnInfo)) {
            return $txnInfo;
        }
        return [];
    }
}

?>
