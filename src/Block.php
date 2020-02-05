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

class Block {

    public $height;
    public $previous;
    public $nonce;
    public $hash;

    /** @var array Transactions */
    public $transactions;
    public $merkle;
    public $timestamp;
    public $timestamp_end;
    public $difficulty;
    public $info;

    public $startNonce;
    public $incrementNonce;

    /**
     * Block constructor.
	 * @param int $height
     * @param string $previous
     * @param int $difficulty
     * @param array $transactions
     * @param null $lastBlock
     * @param null $genesisBlock
     * @param int $startNonce
     * @param int $incrementNonce
     * @param bool $mined
     * @param null|string $hash
     * @param int $nonce
     * @param null|string $timestamp
     * @param null|string $timestamp_end
     * @param null|string $merkle
     * @param null|array $info
     */
    public function __construct(int $height=-1,string $previous, string $difficulty,array $transactions = array(), array $lastBlock=null, array $genesisBlock=null, int $startNonce=0, int $incrementNonce=1, bool $mined=false, string $hash=null, int $nonce=0, string $timestamp=null, string $timestamp_end=null, string $merkle=null, array $info = null) {

        $this->height = $height;
        $this->transactions = $transactions;
        $this->startNonce = $startNonce;
        $this->incrementNonce = $incrementNonce;

        //If block is mined
        if ($mined) {
			$this->difficulty = $difficulty;
            $this->previous = $previous;
            $this->hash = $hash;
            $this->nonce = $nonce;
            $this->timestamp = $timestamp;
            $this->timestamp_end = $timestamp_end;
            $this->merkle = $merkle;
            $this->info = $info;
        }
        else {
			$this->difficulty = $difficulty;
            $this->previous = (strlen($previous) > 0) ? $previous : '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';

            $lastBlockInfo = @unserialize($lastBlock['info']);
            $genesisBlockInfo = @unserialize($genesisBlock['info']);

            //We establish the information of the blockchain
            $this->info = array(
                'max_difficulty' => '000FFFFFF00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',
            );
        }
    }

    /**
     * Generates the first block in the network
     *
     * @param string $coinbase
     * @param string $privKey
	 * @param int $amount
     * @param bool $isTestNet
     *
     */
    public static function createGenesisWithSubProcess(string $coinbase, string $privKey, string $amount, bool $isTestNet=false) : void {
		$genesisTXN = Transaction::withGas("",$coinbase,$amount,$privKey,"","If you want different results, do not do the same things",21000,bcadd("0","0",18));

		//Set Genesis transaction into txns array
		$transactions = [$genesisTXN];

        Display::print("Start minning GENESIS block with " . count($transactions) . " txns - SubProcess: " . MINER_MAX_SUBPROCESS);

        //Save transactions for this block
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO,Tools::str2hex(@serialize($transactions)));
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);

        //Get info to pass miner
        $lastBlock_hash = "null";
        $directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;

        $network = "mainnet";
        if ($isTestNet)
            $network = "testnet";

        //Start subprocess miners
        for ($i = 0; $i < MINER_MAX_SUBPROCESS; $i++) {
            $params = array(
                $lastBlock_hash,
                2,
                $i,
                MINER_MAX_SUBPROCESS,
                $network
            );
            Subprocess::newProcess($directoryProcessFile,'miner',$params,$i);
        }
    }

    /**
     * Function that prepares the creation of a block and mine
     * Group all transactions of the block + the previous hash
     * Mine the block
     * If during the course of the mining you obtain that another miner has created the block before checking if that block is valid
     * If it is valid, it will stop mining
     * If it is not valid, it will continue to undermine
     *
     * @param int $idMiner
     * @param bool $isTestnet
	 * @param bool $isMultiThread
     */
    public function mine(int $idMiner,bool $isTestnet,bool $isMultiThread=true) : void {

        //We prepare the transactions that will go in the block
        $data = "";

		//Add all transactions to make hash block
		foreach ($this->transactions as $transaction) {
			if ($transaction->isValid())
				$data .= $transaction->message();
		}

        //We add the hash of the previous block
		$data .= $this->previous;

		//Record init time
		$this->timestamp = Tools::GetGlobalTime();

        //We started mining
        $this->nonce = PoW::findNonce($idMiner,$data,$this->difficulty,$this->startNonce,$this->incrementNonce,$isMultiThread);

		//Record end time
		$this->timestamp_end = Tools::GetGlobalTime();

        if ($this->nonce != "") {
            //Make merkleRoot and hash for this block
			$this->merkle = $this->GetMerkleRoot($this->GetTxnIds());
            $this->hash = PoW::hash($data.$this->timestamp.$this->timestamp_end.$this->nonce.$this->merkle);
        }
        else {
            $this->hash = "";
            $this->merkle = "";
        }
    }

	/**
     * Get transactions hash of this block
	 * @return array
     */
	public function GetTxnIds() : array {
		$txnIds = [];
		foreach ($this->transactions as $transaction) {
			$txnIds[] = $transaction->message();
		}
		return $txnIds;
	}

	/**
	 * Get MerkleRoot of this block
	 *
	 * @param array $txnIds
	 * @return string
	 */
	public function GetMerkleRoot($txnIds) : string {

		$merkleRoot = [];

		//Split TxnIds
		$txnIdsChunks = array_chunk($txnIds, 2);
		foreach ($txnIdsChunks as $chunkTxn) {
			$concat = "";

			if (count($chunkTxn) == 2)
				$concatTxnChunk = $chunkTxn[0] . $chunkTxn[1];
			else
				$concatTxnChunk = $chunkTxn[0] . $chunkTxn[0];

			if (strlen($concatTxnChunk) > 0)
				$merkleRoot[] = PoW::hash($concatTxnChunk);
		}

		if (count($merkleRoot) == 1)
			return $merkleRoot[0];
		else
			return $this->GetMerkleRoot($merkleRoot);
	}

    /**
     * Get miner transaction
     *
     * @return Transaction
     */
    public function GetMinerTransaction() : Transaction {
        foreach ($this->transactions as $transaction) {
            if ($transaction->from == "") {
                if ($transaction->isValid()) {
                    return $transaction;
                }
            }
        }
        return null;
    }

    /**
     * Return total fees of transactions
     *
     * @return string
     */
    public function GetFeesOfTransactions() : string {
        $totalFees = bcadd("0","0",18);

		$chaindata = new DB();

        foreach ($this->transactions as $transaction) {

			$fees = "0";

            if ($transaction->isValid()) {
				$fees = $transaction->GetFee($chaindata);
            }
            else
                return null;

			$totalFees = bcadd($totalFees,$fees,18);
        }

        return $totalFees;
    }

    /**
     * Check if reward miner is valid
     *
     * @param int $heightBlock
     * @param bool $isTestNet
     * @return bool
     */
    public function isValidReward(int $heightBlock, bool $isTestNet=false) : bool {

        //Get miner transaction
        $minerTransaction = $this->GetMinerTransaction();
        if ($minerTransaction == null)
			return false;

        //Get total fees
        $totalFeesTransactionBlock = $this->GetFeesOfTransactions();
        if ($totalFeesTransactionBlock == null)
			return false;

        //Subtract total transaction fees from total mining transaction, result = miner reward
		$minerRewardBlock = bcsub($minerTransaction->amount,$totalFeesTransactionBlock,18);

        //Calc reward by height
		$currentReward = Blockchain::getRewardByHeight($heightBlock,$isTestNet);

        return ($minerRewardBlock == $currentReward);
    }

    /**
     * Check function if a block is valid or not
     * Check if all transactions in the block are valid
     * Check if the nonce corresponds to the content of all transactions + hash of the previous block
     *
     * @param $height
     * @param $isTestnet
     *
     * @return bool
     */
    public function isValid(int $height,bool $isTestnet) : bool {

        //Define data to check
        $data = "";

		//Add all transactions to make hash block
		foreach ($this->transactions as $transaction) {
			/** @var Transaction $transaction */
			if ($transaction->isValid())
				$data .= $transaction->message();
			else
				return false;
		}

        //Add previous block
        $data .= $this->previous;

        return PoW::isValidNonce($data,$this->nonce, $this->difficulty, $this->info['max_difficulty']);
    }
}
?>
