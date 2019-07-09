<?php
// MIT License
//
// Copyright (c) 2018 MXCCoin
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

class DB {

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

        $this->CheckIfExistTables();
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
        if (!empty($currentConfig))
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

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        if (strlen($ip) > 0 && strlen($port) > 0) {
            $currentInfoPeer = $this->db->query("SELECT id FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();

            //Ban peer 10min
            $blackListTime = time() + 10 * 60;
            if (empty($currentInfoPeer)) {
                $this->db->query("INSERT INTO peers (ip,port,blacklist) VALUES ('".$ip."', '".$port."', '".$blackListTime."');");
            }
            else {
                $this->db->query("UPDATE peers SET blacklist='".$blackListTime."' WHERE ip = '".$ip."' AND port = '".$port."';");
            }
        }
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
        $minerTransaction = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$hash."' ORDER BY tx_fee ASC, timestamp DESC LIMIT 1;")->fetch_assoc();
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
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

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
        $sql = "SELECT * FROM transactions WHERE txn_hash = '".$hash."' ORDER BY tx_fee ASC, timestamp DESC;";
        $info_txn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return null;
    }

    /**
     * Returns a pending transaction given a hash
     *
     * @param $hash
     * @return mixed
     */
    public function GetPendingTransactionByHash($hash) {
        $sql = "SELECT * FROM transactions_pending WHERE txn_hash = '".$hash."' ORDER BY tx_fee ASC, timestamp DESC;";
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

        $totalReceived = "0";
        $totalReceivedReal = "0";
        $totalSpend = "0";

        $totalReceived_tmp = $this->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$wallet."';");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        $totalReceived_tmp = $this->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$wallet."' AND (wallet_from <> '' OR wallet_from IS NULL);");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceivedReal = bcadd($totalReceivedReal, $txnInfo['amount'], 8);
            }
        }

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSpended_tmp = $this->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPending_tmp = $this->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpendedPending_tmp)) {
            while ($txnInfo = $totalSpendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPendingToSend_tmp = $this->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpendedPendingToSend_tmp)) {
            while ($txnInfo = $totalSpendedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $current = bcsub($totalReceived,$totalSpend,8);

        return array(
            'sended' => $totalSpend,
            'received' => $totalReceivedReal,
            'current' => $current
        );
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
            $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$info_block['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

            //If want all transaction info, select all
            if ($withTransactions)
                $sql = "SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

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
     * Add a pending transaction to the chaindata
     *
     * @param Object $transaction
     * @return bool
     */
    public function addPendingTransactionObject($transaction) {
        $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction->hash."';")->fetch_assoc();
        if (empty($into_tx_pending)) {

            //Get current balance of WalletFrom and check if have money to send transaction
            //This prevent hack transactions
            $walletFromBalance = Wallet::GetBalanceWithChaindata($this,$transaction->wallet_from);


            if ($walletFromBalance >= $transaction['amount']) {

                //Start Transactions
                $this->db->begin_transaction();

                $sqlInsertTransaction = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp) 
                    VALUES ('','" . $transaction->txn_hash . "','" . $transaction->wallet_from_key . "','" . $transaction->wallet_from . "','" . $transaction->wallet_to . "','" . $transaction->amount . "','" . $transaction->signature . "','" . $transaction->tx_fee . "','" . $transaction->data . "','" . $transaction->timestamp . "');";

                //Commit transaction
                if ($this->db->query($sqlInsertTransaction)) {
                    $this->db->commit();
                    return true;
                }

                //Rollback transaction
                else
                    $this->db->rollback();
            }
        }
        return false;
    }

    /**
     * Add a pending transaction to the chaindata
     *
     * @param $transaction
     * @return bool
     */
    public function addPendingTransaction($transaction) {
        $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction['txn_hash']."';")->fetch_assoc();
        if (empty($into_tx_pending)) {

            //Get current balance of WalletFrom and check if have money to send transaction
            //This prevent hack transactions
            $walletFromBalance = Wallet::GetBalanceWithChaindata($this,$transaction['wallet_from']);

            if ($walletFromBalance >= $transaction['amount']) {

                //Start Transactions
                $this->db->begin_transaction();

                $sqlInsertTransaction = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp) 
                    VALUES ('','" . $transaction['txn_hash'] . "','" . $transaction['wallet_from_key'] . "','" . $transaction['wallet_from'] . "','" . $transaction['wallet_to'] . "','" . $transaction['amount'] . "','" . $transaction['signature'] . "','" . $transaction['tx_fee'] . "','" . $transaction['data'] . "','" . $transaction['timestamp'] . "');";

                //Commit transaction
                if ($this->db->query($sqlInsertTransaction)) {
                    $this->db->commit();
                    return true;
                }

                //Rollback transaction
                else
                    $this->db->rollback();
            }
        }
        return false;
    }

    /**
     * Add a pending transaction to the chaindata
     *
     * @param $transaction
     * @return bool
     */
    public function addPendingTransactionByBootstrap($transaction) {
        if (isset($transaction->txn_hash) && strlen($transaction->txn_hash) > 0) {
            $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction->txn_hash."';")->fetch_assoc();
            if (empty($into_tx_pending)) {

                //Get current balance of WalletFrom and check if have money to send transaction
                //This prevent hack transactions
                $walletFromBalance = Wallet::GetBalanceWithChaindata($this,$transaction->wallet_from);

                if ($walletFromBalance >= $transaction->amount) {

                    //Start Transactions
                    $this->db->begin_transaction();

                    $sqlInsertTransaction = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp) 
                    VALUES ('','".$transaction->txn_hash."','".$transaction->wallet_from_key."','".$transaction->wallet_from."','".$transaction->wallet_to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->data."','".$transaction->timestamp."');";

                    //Commit transaction
                    if ($this->db->query($sqlInsertTransaction)) {
                        $this->db->commit();
                        return true;
                    }

                    //Rollback transaction
                    else
                        $this->db->rollback();
                }
            }
        }
        return false;
    }

    /**
     * We obtain all pending transactions that the address_from is different from address_to
     *
     * @return array
     */
    public function GetAllPendingTransactions() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending WHERE wallet_from <> wallet_to ORDER BY tx_fee ASC, timestamp DESC LIMIT 512");
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
    public function addPendingTransactionsByPeer($transactionsByPeer) {

        foreach ($transactionsByPeer as $tx) {

            // Date of the transaction can not be longer than the local date
            if ($tx->timestamp > Tools::GetGlobalTime())
                continue;

            //We check not sending money to itself
            if ($tx->wallet_from == $tx->wallet_to)
                continue;

            $this->addPendingTransactionObject($tx);
        }

        return true;
    }

    /**
     * Add a pending transaction to send to the chaindata
     *
     * @param $txHash
     * @param $transaction
     * @return bool
     */
    public function addPendingTransactionToSend($txHash,$transaction) {
        $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending_to_send WHERE txn_hash = '".$txHash."' ORDER BY tx_fee ASC, timestamp DESC;")->fetch_assoc();
        if (empty($into_tx_pending)) {

            $wallet_from_pubkey = "";
            $wallet_from = "";
            if ($transaction->from != null) {
                $wallet_from_pubkey = $transaction->from;
                $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
            }

            //Start Transactions
            $this->db->begin_transaction();

            $sqlInsertPendingTransactionToSend = "INSERT INTO transactions_pending_to_send (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp) 
                    VALUES ('','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->data."','".$transaction->timestamp."');";

            //Commit transaction
            if ($this->db->query($sqlInsertPendingTransactionToSend)) {
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
     * Delete a pending transaction
     *
     * @param $txHash
     */
    public function removePendingTransaction($txHash) {
        $this->db->query("DELETE FROM transactions_pending WHERE txn_hash='".$txHash."';");
    }

    /**
     * Delete a pending transaction to send
     *
     * @param $txHash
     */
    public function removePendingTransactionToSend($txHash) {
        $this->db->query("DELETE FROM transactions_pending_to_send WHERE txn_hash='".$txHash."';");
    }

    /**
     * Return array with all pending transactions to send
     *
     * @return array
     */
    public function GetAllPendingTransactionsToSend() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending_to_send ORDER BY tx_fee ASC, timestamp DESC");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $txs[] = $tx_chaindata;
            }
        }
        return $txs;
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
                    //We eliminated the pending transaction
                    $this->removePendingTransaction($transaction->message());
                    $this->removePendingTransactionToSend($transaction->message());
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
     * Add a block in the chaindata
     *
     * @param int $blockNum
     * @param array $blockInfo
     * @return bool
     */
    public function addBlockFromArray($blockNum,$blockInfo) {

        $error = false;

        $info_block_chaindata = $this->db->query("SELECT block_hash FROM blocks WHERE block_hash = '".$blockInfo['block_hash']."';")->fetch_assoc();
        if (empty($info_block_chaindata)) {

            //Start Transactions
            $this->db->begin_transaction();

            //SQL Insert Block
            $sqlInsertBlock = "INSERT INTO blocks (height,block_previous,block_hash,root_merkle,nonce,timestamp_start_miner,timestamp_end_miner,difficulty,version,info)
            VALUES (".$blockNum.",'".$blockInfo['block_previous']."','".$blockInfo['block_hash']."','".$blockInfo['root_merkle']."','".$blockInfo['nonce']."','".$blockInfo['timestamp_start_miner']."','".$blockInfo['timestamp_end_miner']."','".$blockInfo['difficulty']."','".$blockInfo['version']."','".$blockInfo['info']."');";

            //Add block into blockchain
            if ($this->db->query($sqlInsertBlock)) {

                foreach ($blockInfo['transactions'] as $transaction) {

                    $sqlInsertTransaction = "INSERT INTO transactions (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, data, timestamp) 
                    VALUES ('".$blockInfo['block_hash']."','".$transaction['txn_hash']."','".$transaction['wallet_from_key']."','".$transaction['wallet_from']."','".$transaction['wallet_to']."','".$transaction['amount']."','".$transaction['signature']."','".$transaction['tx_fee']."','".$transaction['data']."','".$transaction['timestamp']."');";
                    if (!$this->db->query($sqlInsertTransaction)) {
                        $error = true;
                        break;
                    }

                    //We eliminated the pending transaction
                    $this->removePendingTransaction($transaction['txn_hash']);
                    $this->removePendingTransactionToSend($transaction['txn_hash']);
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

			//Remove SmartContracts of this block
			$sqlRemoveSmartContracts = "
			DELETE FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			$this->db->query($sqlRemoveSmartContracts);
			
            //Remove transactions of block from blockchain
            $sqlRemoveTransactions = "DELETE FROM transactions WHERE block_hash = '".$infoBlock['block_hash']."';";
            if ($this->db->query($sqlRemoveTransactions)) {

                //Remove block from blockchain
                $sqlRemoveBlock = "DELETE FROM blocks WHERE block_hash = '".$infoBlock['block_hash']."';";
                if ($this->db->query($sqlRemoveBlock)) {
                    $this->db->commit();
                    return true;
                }
                else
                    $error = true;
            }
            else
                $error = true;
        }

        //Rollback transaction
        if ($error)
            $this->db->rollback();
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

        $infoBlock = $this->db->query("SELECT block_hash FROM blocks WHERE block_hash = '".$hash."';")->fetch_assoc();
        if (!empty($infoBlock)) {

            //Start Transactions
            $this->db->begin_transaction();

			//Remove SmartContracts of this block
			$sqlRemoveSmartContracts = "
			DELETE FROM smart_contracts WHERE txn_hash IN (
				SELECT txn_hash
				FROM transactions
				WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
				AND data <> '0x'
				AND block_hash = '".$infoBlock['block_hash']."'
			);
			";
			$this->db->query($sqlRemoveSmartContracts);

			//Remove transactions of block from blockchain
            $sqlRemoveTransactions = "DELETE FROM transactions WHERE block_hash = '".$infoBlock['block_hash']."';";
            if ($this->db->query($sqlRemoveTransactions)) {

                //Remove block from blockchain
                $sqlRemoveBlock = "DELETE FROM blocks WHERE block_hash = '".$infoBlock['block_hash']."';";
                if ($this->db->query($sqlRemoveBlock)) {
                    $this->db->commit();
                    return true;
                }
                else
                    $error = true;
            }
            else
                $error = true;
        }

        //Rollback transaction
        if ($error)
            $this->db->rollback();
        return false;
    }

    /**
     * Remove block and transactions
     *
     * @param int $height
     */
    public function RemoveLastBlocksFrom($height) {
		
		//Remove SmartContracts of this block
		$this->db->query("
		DELETE FROM smart_contracts WHERE txn_hash IN (
			SELECT txn_hash
			FROM transactions
			WHERE wallet_to = 'J4F00000000000000000000000000000000000000000000000000000000'
			AND data <> '0x'
			AND block_hash IN (
				SELECT block_hash FROM blocks WHERE height > ".$height."
			)
		);
		");

		//Remove transactions
        $this->db->query("
        DELETE FROM transactions WHERE block_hash IN (
          SELECT block_hash FROM blocks WHERE height > ".$height."
		);
		");

		//Remove block
        $this->db->query("DELETE FROM blocks WHERE height > ".$height);
    }

    /**
     * Check if announced this block
     *
     * @param $blockHash
     * @return bool
     */
    public function BlockHasBeenAnnounced($blockHash) {
        $infoBlockAnnounced = $this->db->query("SELECT id FROM blocks_announced WHERE block_hash = '".$blockHash."';")->fetch_assoc();
        if (empty($infoBlockAnnounced))
            return false;
        return true;
    }

    /**
     * Add block hash to announced blocks
     *
     * @param $blockHash
     * @return bool
     */
    public function AddBlockAnnounced($blockHash) {
        $infoBlockAnnounced = $this->db->query("SELECT id FROM blocks_announced WHERE block_hash = '".$blockHash."';")->fetch_assoc();
        if (empty($infoBlockAnnounced))
            $this->db->query("INSERT INTO blocks_announced (block_hash) VALUES ('".$blockHash."');");
        return true;
    }

    /**
     * Remove block hash from announced blocks
     *
     * @param $blockHash
     * @return bool
     */
    public function RemoveBlockAnnounced($blockHash) {
        if ($this->db->query("DELETE FROM blocks_announced WHERE block_hash = '".$blockHash."';"))
            return true;
        return false;
    }

    /**
     * Add a block mined by a peer
     * this block will be added from the main process
     *
     * @param Block $minedBlock
     * @param string $status
     * @return bool
     */
    public function AddBlockToDisplay($minedBlock,$status) {

        $error = false;

        $info_block_chaindata = $this->db->query("SELECT block_hash FROM blocks_pending_to_display WHERE block_hash = '".$minedBlock->hash."';")->fetch_assoc();
        if (empty($info_block_chaindata)) {

            //Start Transactions
            $this->db->begin_transaction();

            //SQL Insert Block
            $sql_insert_block = "INSERT INTO blocks_pending_to_display (status,block_previous,block_hash,root_merkle,nonce,timestamp_start_miner,timestamp_end_miner,difficulty,version,info)
            VALUES ('".$status."','".$minedBlock->previous."','".$minedBlock->hash."','".$minedBlock->merkle."','".$minedBlock->nonce."','".$minedBlock->timestamp."','".$minedBlock->timestamp_end."','".$minedBlock->difficulty."','".$this->GetConfig('node_version')."','".$this->db->real_escape_string(@serialize($minedBlock->info))."');";

            if ($this->db->query($sql_insert_block)) {
                $this->db->commit();
                return true;
            }
            else
                $error = true;
        }
        if ($error)
            $this->db->rollback();
        return false;
    }

    /**
     * Return array of mined blocks by peers
     *
     * @return array
     */
    public function GetBlockPendingToDisplay() {
        $sql = "SELECT * FROM blocks_pending_to_display ORDER BY height ASC LIMIT 1";
        $firstBlockInDisplayTable = $this->db->query($sql)->fetch_assoc();
        return $firstBlockInDisplayTable;
    }

    /**
     * Delete block from temp table
     *
     * @param $blockHash
     */
    public function RemoveBlockToDisplay($blockHash) {
        $this->db->query("DELETE FROM blocks_pending_to_display WHERE block_hash='".$blockHash."';");
    }

    /**
     * Return array of mined blocks by peers by hash param
     *
     * @param $hash
     *
     * @return array
     */
    public function GetBlockPendingToDisplayByHash($hash) {
        $sql = "SELECT * FROM blocks_pending_to_display WHERE block_hash = '".$hash."'ORDER BY height ASC LIMIT 1";
        $blockPendingByPeer = $this->db->query($sql)->fetch_assoc();
        return $blockPendingByPeer;
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

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;");
                $transactions = array();
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
                $sql = "SELECT txn_hash FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

                //If want all transaction info, select all
                if ($withTransactions)
                    $sql = "SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' ORDER BY tx_fee ASC, timestamp DESC;";

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

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."' ORDER by tx_fee ASC, timestamp DESC;");
                $transactions = array();
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

            //Start Transactions
            $this->db->begin_transaction();

            //SQL Insert Block
            $sqlInsertContract = "INSERT INTO smart_contracts (contract_hash,txn_hash,code,data)
            VALUES ('".$contractHash."','".$txn_hash."','".$codeHexBytes."','".$dataHexBytes."');";

            //Add block into blockchain
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
            return true;
        }
	}

	/**
     * Update storedData of Contract in the chaindata
     *
     * @param string $contractHash
	 * @param string $dataHexBytes
     * @return bool
     */
    public function updateStoredDataContract($contractHash,$dataHexBytes) {

        $error = false;

		//Start Transactions
		$this->db->begin_transaction();

		//SQL Update storedData of Contract
		if (!$this->db->query("UPDATE smart_contracts SET data = '".$dataHexBytes."' WHERE contract_hash = '".$contractHash."';")) {
			$error = true;
		}

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, contract added
        else {
            $this->db->commit();
            return true;
        }
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
     * Check that the basic tables exist for the blockchain to work
     */
    private function CheckIfExistTables() {
        //We create the tables by default
        $this->db->query("
        CREATE TABLE `blocks` (
          `height` int(200) unsigned NOT NULL,
          `block_previous` varchar(64) DEFAULT NULL,
          `block_hash` varchar(64) NOT NULL,
          `root_merkle` varchar(64) NOT NULL,
          `nonce` bigint(200) NOT NULL,
          `timestamp_start_miner` varchar(12) NOT NULL,
          `timestamp_end_miner` varchar(12) NOT NULL,
          `difficulty` varchar(255) NOT NULL,
          `version` varchar(10) NOT NULL,
          `info` text NOT NULL,
          PRIMARY KEY (`height`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->db->query("
        CREATE TABLE `transactions` (
          `txn_hash` varchar(64) NOT NULL,
          `block_hash` varchar(64) NOT NULL,
          `wallet_from_key` longtext,
          `wallet_from` varchar(64) DEFAULT NULL,
          `wallet_to` varchar(64) NOT NULL,
          `amount` varchar(64) NOT NULL,
          `signature` longtext NOT NULL,
          `tx_fee` varchar(10) DEFAULT NULL,
          `timestamp` varchar(12) NOT NULL,
          PRIMARY KEY (`txn_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->db->query("
        CREATE TABLE `transactions_pending` (
          `txn_hash` varchar(64) NOT NULL,
          `block_hash` varchar(64) NOT NULL,
          `wallet_from_key` longtext,
          `wallet_from` varchar(64) DEFAULT NULL,
          `wallet_to` varchar(64) NOT NULL,
          `amount` varchar(64) NOT NULL,
          `signature` longtext NOT NULL,
          `tx_fee` varchar(10) DEFAULT NULL,
          `timestamp` varchar(12) NOT NULL,
          PRIMARY KEY (`txn_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->db->query("
        CREATE TABLE `transactions_pending_to_send` (
          `txn_hash` varchar(64) NOT NULL,
          `block_hash` varchar(64) NOT NULL,
          `wallet_from_key` longtext,
          `wallet_from` varchar(64) DEFAULT NULL,
          `wallet_to` varchar(64) NOT NULL,
          `amount` varchar(64) NOT NULL,
          `signature` longtext NOT NULL,
          `tx_fee` varchar(10) DEFAULT NULL,
          `timestamp` varchar(12) NOT NULL,
          PRIMARY KEY (`txn_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->db->query("
        CREATE TABLE IF NOT EXISTS `peers` (
          `ip` varchar(120) NOT NULL,
          `port` varchar(8) NOT NULL,
          PRIMARY KEY (`ip`,`port`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

}

?>