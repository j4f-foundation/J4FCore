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

class DBTransactions {

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
}

?>
