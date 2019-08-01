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

class GenesisBlock {

    /**
     * Mine a GENESIS BLOCK
     *
     * @param DB $chaindata
     * @param string $coinbase
     * @param string $privKey
     * @param bool $isTestNet
     * @param int $amount
     */
    public static function make(&$chaindata,$coinbase,$privKey,$isTestNet,$amount=50) {

        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO);

        //We check that there is no block GENESIS
        $GENESIS_block_chaindata = $chaindata->db->query("SELECT height, block_hash FROM blocks WHERE height = 0")->fetch_assoc();
        if (empty($GENESIS_block_chaindata)) {
            //we show the message that we generated the GENESIS block
            Display::_printer("Generating %G%GENESIS%W% - Block %G%#0");
            Display::_printer("Minning Block %G%#0");

			//We created the GENESIS block by MainThread
			if (MINER_MAX_SUBPROCESS <= 1)
				self::makeMainThread($chaindata,$coinbase, $privKey,$amount,$isTestNet);

			//We created the GENESIS block by miner subprocess
			else
				self::makeWithSubprocess($chaindata, $coinbase, $privKey,$amount,$isTestNet);

        } else {
            //we show the message that there is already a block genesis
            Display::_printer("%LR%ERROR");
            Display::_printer("There is alrady exist a %G%GENESIS%W% Block");
            Display::_printer("Block #0 -> Hash: %LG%".$GENESIS_block_chaindata['block_hash']);
            if (IS_WIN)
                readline("Press any key to close close window");
            exit();
        }
    }

	/**
     * Mine a GENESIS BLOCK on MainThread
     *
     * @param DB $chaindata
     * @param string $coinbase
     * @param string $privKey
     * @param int $amount
     * @param bool $isTestNet
     */
	public static function makeMainThread(&$chaindata,$coinbase, $privKey,$amount,$isTestNet) {
		//We created the GENESIS block on mainthread
		$transactions = array(new Transaction(null,$coinbase,$amount,$privKey,"","","If you want different results, do not do the same things"));
		$genesisBlock = $chaindata->GetGenesisBlock();
		$lastBlock = $chaindata->GetLastBlock();

		//Define block
		$genesisBlock = new Block(0,null,1,$transactions,$lastBlock,$genesisBlock,0,1);

		//Mine block
		$genesisBlock->mine(0,$isTestnet,false);

		if (!$genesisBlock->isValid(0,$isTestNet)) {
			Display::_error("%LR%GENESIS%W% no valid");
			Display::_error("%LR%HASH%W% " . $genesisBlock->hash);
			Display::_error("%LR%PREVIOUS%W% " . $genesisBlock->previous);
			Display::_error("%LR%DIFFICULTY%W% " . $genesisBlock->difficulty);
			Display::_error("%LR%NONCE%W% " . $genesisBlock->nonce);
			//Display::_error("%LR%HASH%W% " . $genesisBlock->info);
			if (IS_WIN)
				readline("Press any Enter to close close window");
			exit();
		}

		//Save genesis block into blockchain
		$chaindata->addBlock(0,$genesisBlock);

		//Display message
		Display::NewBlockMined(0,$genesisBlock);

		//We show the information of the mined block
		Display::_printer("New Block mined with hash: %G%".$genesisBlock->hash);
		Display::_printer("Nonce of Block: %G%".$genesisBlock->nonce);
		Display::_printer("Transactions in Block: %LG%".count($genesisBlock->transactions));

		Display::_printer("%G%GENESIS%W% Block was successfully generated");
		Display::_br();
		if (IS_WIN)
			readline("Press any Enter to close close window");
		exit();
	}

	/**
     * Mine a GENESIS BLOCK on Subprocess
     *
     * @param DB $chaindata
     * @param string $coinbase
     * @param string $privKey
     * @param int $amount
     * @param bool $isTestNet
     */
	public static function makeWithSubprocess(&$chaindata,$coinbase, $privKey,$amount,$isTestNet) {
		Block::createGenesis($coinbase, $privKey,$amount,$isTestNet);
		while(true) {

			//Update MainThread time for subprocess
			Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

			//If found new block
			if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
				$genesisBlock = Tools::objectToObject(@unserialize(Tools::hex2str(file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK))),'Block');

				if (!$genesisBlock->isValid(0,$isTestNet)) {
					Display::_error("%LR%GENESIS%W% no valid");
					Display::_error("%LR%HASH%W% " . $genesisBlock->hash);
					Display::_error("%LR%PREVIOUS%W% " . $genesisBlock->previous);
					Display::_error("%LR%DIFFICULTY%W% " . $genesisBlock->difficulty);
					Display::_error("%LR%NONCE%W% " . $genesisBlock->nonce);
					//Display::_error("%LR%HASH%W% " . $genesisBlock->info);
					if (IS_WIN)
						readline("Press any Enter to close close window");
					exit();
				}

				//Save genesis block into blockchain
				$chaindata->addBlock(0,$genesisBlock);

				//Display message
				Display::NewBlockMined(0,$genesisBlock);

				//Stop minning subprocess
				Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
				@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
				//@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.'NEW_BLOCK');

				//We show the information of the mined block
				Display::_printer("New Block mined with hash: %G%".$genesisBlock->hash);
				Display::_printer("Nonce of Block: %G%".$genesisBlock->nonce);
				Display::_printer("Transactions in Block: %LG%".count($genesisBlock->transactions));

				Display::_printer("%G%GENESIS%W% Block was successfully generated");
				Display::_br();
				if (IS_WIN)
					readline("Press any Enter to close close window");
				exit();
			}
			usleep(1000000);
		}
	}

    /**
     * @param $genesis_block_bootstrap
     * @param DB $chaindata
     * @return bool
     */
    public static function makeFromPeer($genesis_block_bootstrap,&$chaindata) {
        $transactions = array();
        if (!empty($genesis_block_bootstrap['transactions'])) {
            foreach ($genesis_block_bootstrap['transactions'] as $transactionInfo) {
                $transactions[] = new Transaction(
                    $transactionInfo['wallet_from_key'],
                    $transactionInfo['wallet_to'],
                    $transactionInfo['amount'],
                    null,
                    null,
					'',
					$transactionInfo['data'],
                    true,
                    $transactionInfo['txn_hash'],
                    $transactionInfo['signature'],
                    $transactionInfo['timestamp']
                );
            }
		}

        $infoBlock = @unserialize($genesis_block_bootstrap['info']);

        $genesis_block = new Block(
            0,
            $genesis_block_bootstrap['block_previous'],
            $genesis_block_bootstrap['difficulty'],
            $transactions,
            '',
            '',
            '',
            '',
			true,
            $genesis_block_bootstrap['block_hash'],
            $genesis_block_bootstrap['nonce'],
            $genesis_block_bootstrap['timestamp_start_miner'],
            $genesis_block_bootstrap['timestamp_end_miner'],
            $genesis_block_bootstrap['root_merkle'],
            $infoBlock
        );

		//var_dump($genesis_block);
        //Check if node is connected on testnet or mainnet
        $isTestnet = ($chaindata->GetNetwork() == "testnet") ? true:false;

        //We check if the received block is valid
        if ($genesis_block->isValid(0,$isTestnet)) {
            //We add the GENESIS block to the local blockchain
            $chaindata->addBlock(0,$genesis_block);
            return true;
        }
        return false;
    }
}
