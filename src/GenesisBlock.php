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

class GenesisBlock {

    /**
     * Mine a GENESIS BLOCK
     *
     * @param DB $chaindata
     * @param $coinbase
     * @param $privKey
     * @param $isTestNet
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

            //We created the GENESIS block for miners
            Block::createGenesis($coinbase, $privKey,$amount,$isTestNet);
            while(true) {

                //Update MainThread time for subprocess
                Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

                //If found new block
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
                    $genesisBlock = Tools::objectToObject(@unserialize(@file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)),'Block');

                    if (!$genesisBlock->isValid(0,$isTestNet)) {
                        Display::_printer("%LR%ERROR%W%     %G%GENESIS%W% no valid");
                        if (IS_WIN)
                            readline("Press any Enter to close close window");
                        exit();
                    }

                    //Save genesis block into blockchain
                    $chaindata->addBlock(0,$genesisBlock);

                    //Display message
                    Display::NewBlockMined($genesisBlock);

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
     * @param $genesis_block_bootstrap
     * @param DB $chaindata
     * @return bool
     */
    public static function makeFromPeer($genesis_block_bootstrap,&$chaindata) {
        $transactions = array();
        if (!empty($genesis_block_bootstrap->transactions)) {
            foreach ($genesis_block_bootstrap->transactions as $transactionInfo) {
                $transactions[] = new Transaction(
                    $transactionInfo->wallet_from_key,
                    $transactionInfo->wallet_to,
                    $transactionInfo->amount,
                    null,
                    null,
                    '',
                    true,
                    $transactionInfo->txn_hash,
                    $transactionInfo->signature,
                    $transactionInfo->timestamp
                );
            }
		}

        $infoBlock = @unserialize($genesis_block_bootstrap->info);

        $genesis_block = new Block(
            0,
            $genesis_block_bootstrap->block_previous,
            $genesis_block_bootstrap->difficulty,
            $transactions,
            '',
            '',
            '',
            '',
            true,
            $genesis_block_bootstrap->block_hash,
            $genesis_block_bootstrap->nonce,
            $genesis_block_bootstrap->timestamp_start_miner,
            $genesis_block_bootstrap->timestamp_end_miner,
            $genesis_block_bootstrap->root_merkle,
            $infoBlock
        );

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