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

class Miner {

    /**
     * We mine the next block
     *
     * @param Gossip $gossip
     * @return bool
     */
    public static function MineNewBlock(Gossip &$gossip) : bool {

        //Clear stop file of miners
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK);

        //Clear subprocess files
        for($i=0;$i<MINER_MAX_SUBPROCESS;$i++) {
            @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i);
            @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i."_hashrate");
        }

        //Get Last block
        $lastBlock = $gossip->chaindata->GetLastBlock();
		$nextBlockHeight = $lastBlock["height"] + 1;

        //Get Pending transactions
        $transactions_pending = $gossip->chaindata->GetTxnFromPool();

        $total_amount_to_miner = "0";

        //We calculate the commissions of the pending transactions
		$totalFees = Blockchain::GetFeesOfTransactions($gossip->chaindata,$transactions_pending);

        if ($totalFees == null) {
            Display::_error("Can't get total fees of transactions. Cancelling mining");
            return false;
        }

        //Add fees
        $total_amount_to_miner = bcadd($total_amount_to_miner,strval($totalFees),18);

        //Calc reward by height
        $currentReward = Blockchain::getRewardByHeight($nextBlockHeight);

        //we add the reward with transaction fees
		$total_amount_to_miner = bcadd($total_amount_to_miner,strval($currentReward),18);

        //We created the mining reward txn + fees txns
		$tx = Transaction::withGas("",$gossip->coinbase, $total_amount_to_miner, $gossip->key->privKey,"","",21000,"0");

        //We take all pending transactions
        $transactions = array($tx);

        //We add pending transactions
        foreach ($transactions_pending as $txn) {
			$new_txn = Transaction::withGas($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], "", "", $txn['data'], $txn['gasLimit'], $txn['gasPrice'], true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);

            if ($new_txn->isValid())
				$transactions[] = $new_txn;
        }

		if (count($transactions) == 0) {
            Display::_error("Can't start mining block, no transactions found?");
            return false;
        }

		if (SHOW_INFO_SUBPROCESS)
        	Display::print("Start mining block                      %G%txns%W%=" . count($transactions) . "             %G%threads%W%=" . MINER_MAX_SUBPROCESS."    %G%difficulty%W%=".$gossip->difficulty);

        //Save transactions for this block
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO,Tools::str2hex(@serialize($transactions)));
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);

        //Get info to pass miner
        $directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;
        $network = "mainnet";
        if ($gossip->isTestNet)
            $network = "testnet";

        //Start subprocess miners
        for ($i = 0; $i < MINER_MAX_SUBPROCESS; $i++) {

            $logMinerProcess = "false";
            if ($i == (MINER_MAX_SUBPROCESS-1))
                $logMinerProcess = "true";

            $params = array(
                $lastBlock['block_hash'],
                $gossip->difficulty,
                $i,
                MINER_MAX_SUBPROCESS,
                $network
            );
            Subprocess::newProcess($directoryProcessFile,'miner',$params,$i);
        }

		return true;
    }

}

?>
