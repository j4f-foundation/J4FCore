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
     * @param $previous
     * @param $difficulty
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
     * @param null|int $height
     */
    public function __construct($height=-1,$previous,$difficulty,$transactions = array(),$lastBlock=null,$genesisBlock=null,$startNonce=0,$incrementNonce=1,$mined=false,$hash=null,$nonce=0,$timestamp=null,$timestamp_end=null,$merkle=null,$info = null) {

        $this->height = $height;
        $this->transactions = $transactions;
        $this->difficulty = $difficulty;
        $this->startNonce = $startNonce;
        $this->incrementNonce = $incrementNonce;

        //If block is mined
        if ($mined) {
            $this->previous = (strlen($previous) > 0) ? $previous : null;
            $this->hash = $hash;
            $this->nonce = $nonce;
            $this->timestamp = $timestamp;
            $this->timestamp_end = $timestamp_end;
            $this->merkle = $merkle;
            $this->info = $info;
        }
        else {
            $this->previous = (strlen($previous) > 0) ? $previous : null;

            $lastBlockInfo = @unserialize($lastBlock['info']);
            $genesisBlockInfo = @unserialize($genesisBlock['info']);

            $currentBlocksDifficulty = $lastBlockInfo['current_blocks_difficulty']+1;
            if ($currentBlocksDifficulty > $genesisBlockInfo['num_blocks_to_change_difficulty'])
                $currentBlocksDifficulty = 1;

            $currentBlocksHalving = $lastBlockInfo['current_blocks_halving']+1;
            if ($currentBlocksDifficulty > $genesisBlockInfo['num_blocks_to_halving'])
                $currentBlocksDifficulty = 1;

            //We establish the information of the blockchain
            $this->info = array(
                'current_blocks_difficulty' => $currentBlocksDifficulty,
                'current_blocks_halving' => $currentBlocksHalving,
                'max_difficulty' => '000FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF',
                'num_blocks_to_change_difficulty' => 2016,
                'num_blocks_to_halving' => 250000,
                'time_expected_to_mine' => 20160
            );
        }
    }

    /**
     * Generates the first block in the network
     *
     * @param $coinbase
     * @param $privKey
     * @param $amount
     * @param $isTestNet
     *
     */
    public static function createGenesis($coinbase, $privKey, $amount, $isTestNet=false) {
        $transactions = array(new Transaction(null,$coinbase,$amount,$privKey,"","","Genesis Txn"));
        //$genesisBlock = new Block("",1, $transactions);

        Display::_printer("Start minning GENESIS block with " . count($transactions) . " txns - SubProcess: " . MINER_MAX_SUBPROCESS);

        //Save transactions for this block
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO,@serialize($transactions));
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
                1,
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
     * @param int $height
     * @param bool $isTestnet
     */
    public function mine($idMiner,$isTestnet) {

        $this->timestamp = Tools::GetGlobalTime();

        //We prepare the transactions that will go in the block
        $data = "";

		//Add all transactions to make hash block
		foreach ($this->transactions as $transaction) {
			if ($transaction->isValid())
				$data .= $transaction->message();
		}

        //We add the hash of the previous block
		$data .= $this->previous;

        //We started mining
        $this->nonce = PoW::findNonce($idMiner,$data,$this->difficulty,$this->startNonce,$this->incrementNonce);
        if ($this->nonce !== false) {
            //Make hash and merkle for this block
            $this->hash = PoW::hash($data.$this->nonce);
            $this->merkle = PoW::hash($data.$this->nonce.$this->hash);
        }
        else {
            $this->hash = "";
            $this->merkle = "";
        }

        $this->timestamp_end = Tools::GetGlobalTime();

    }

    /**
     * Get miner transaction
     *
     * @return bool|mixed
     */
    public function GetMinerTransaction() {
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
    public function GetFeesOfTransactions() {
        $totalFees = "0";
        foreach ($this->transactions as $transaction) {
            if ($transaction->isValid()) {
                if ($transaction->tx_fee == 3)
                    $totalFees = bcadd($totalFees,"0.00001400",8);
                else if ($transaction->tx_fee == 2)
                    $totalFees = bcadd($totalFees,"0.00000900",8);
                else if ($transaction->tx_fee == 1)
                    $totalFees = bcadd($totalFees,"0.00000250",8);
            }
            else
                return null;
        }
        return $totalFees;
    }

    /**
     * Check if reward miner is valid
     *
     * @param $heightBlock
     * @param bool $isTestNet
     * @return bool
     */
    public function isValidReward($heightBlock,$isTestNet=false) {

        //Get miner transaction
        $minerTransaction = $this->GetMinerTransaction();
        if ($minerTransaction == null)
			return false;

        //Get total fees
        $totalFeesTransactionBlock = $this->GetFeesOfTransactions();
        if ($totalFeesTransactionBlock == null)
			return false;

        //Subtract total transaction fees from total mining transaction, result = miner reward
		$minerRewardBlock = $minerTransaction->amount - $totalFeesTransactionBlock;

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
    public function isValid($height,$isTestnet) {

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

        return PoW::isValidNonce($data,$this->nonce,$this->difficulty, $this->info['max_difficulty']);
    }
}
?>