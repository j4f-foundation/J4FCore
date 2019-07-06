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

class Peer {

    /**
     * Sync blocks received by peer
     *
     * @param Gossip $gossip
     * @param $nextBlocksToSyncFromPeer
     * @param $currentBlocks
     * @param $totalBlocks
     * @param $ipAndPort
     *
     * @return bool
     */
    public static function SyncBlocks(&$gossip,$nextBlocksToSyncFromPeer,$currentBlocks,$totalBlocks,$ipAndPort) {
        $blocksSynced = 0;
        $blockSynced = null;
        $transactionsSynced = null;

        //Check if node is connected on testnet or mainnet
        $isTestnet = ($gossip->chaindata->GetNetwork() == "testnet") ? true:false;

        if (count($nextBlocksToSyncFromPeer) > 0) {
            foreach ($nextBlocksToSyncFromPeer as $object) {

                $infoBlock = @unserialize($object->info);

                $transactions = array();
                foreach ($object->transactions as $transactionInfo) {
                    $transactions[] = new Transaction(
                        $transactionInfo->wallet_from_key,
                        $transactionInfo->wallet_to,
                        $transactionInfo->amount,
                        null,
                        null,
						(isset($transactionInfo->tx_fee)) ? $transactionInfo->tx_fee:'',
						$transactionInfo->data,
                        true,
                        $transactionInfo->txn_hash,
                        $transactionInfo->signature,
                        $transactionInfo->timestamp
                    );
                }
                $transactionsSynced = $transactions;

                $blockToImport = new Block(
                    $object->height,
                    $object->block_previous,
                    $object->difficulty,
                    $transactions,
                    '',
                    '',
                    '',
                    '',
                    true,
                    $object->block_hash,
                    $object->nonce,
                    $object->timestamp_start_miner,
                    $object->timestamp_end_miner,
                    $object->root_merkle,
                    $infoBlock
                );

                //Get last local block
                $lastBlock = $gossip->chaindata->GetLastBlock();

                //Check if my last block is the previous block of the block to import
                if ($lastBlock['block_hash'] == $object->block_previous) {

                    //Define new height for next block
                    $nextHeight = $lastBlock['height']+1;

                    //If block is valid
                    if ($blockToImport->isValid($nextHeight,$isTestnet)) {

                        //Check if rewarded transaction is valid, prevent hack money
                        if ($blockToImport->isValidReward($nextHeight,$gossip->isTestNet)) {

                            //We add block to blockchain
                            if ($gossip->chaindata->addBlock($nextHeight,$blockToImport)) {
								//Make SmartContracts on local blockchain
								Blockchain::MakeSmartContracts($gossip->chaindata,$blockToImport);
							}

                            //Save block pointer
                            $blockSynced = $blockToImport;

                            $blocksSynced++;
                        } else {
                            Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Reward transaction not valid");
                            $gossip->chaindata->addPeerToBlackList($ipAndPort);
                            return null;
                        }
                    } else {
                        Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Has a block that I can not validate");
                        $gossip->chaindata->addPeerToBlackList($ipAndPort);
                        return null;
                    }

                //Check if my last block is the same height of the block to import
                } else if ($lastBlock['block_previous'] == $blockToImport->previous && $lastBlock['block_hash'] != $blockToImport->hash) {
                    //Valid new block in same hiehgt to add in Blockchain
                    $returnCode = Blockchain::isValidBlockMinedByPeerInSameHeight($gossip->chaindata,$lastBlock,$blockToImport);
                    if ($returnCode == "0x00000000") {
                        //Save block pointer
                        $blockSynced = $blockToImport;

                        $blocksSynced++;
                    }
                } else if ($lastBlock['block_previous'] == $object->block_previous && $lastBlock['block_hash'] == $object->block_hash) {
                    continue;
                } else {
                    //Sanity last block and resync
					$gossip->chaindata->RemoveBlock($lastBlock['height']);
					Display::_warning("Sanity last block and re-sync       %G%height%W%=".$lastBlock['height']);

					Tools::clearTmpFolder();

					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");

                    //Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Peer Previous block doesnt match with local last block");
                    //$gossip->chaindata->addPeerToBlackList($ipAndPort);
                    return null;
                }
            }
        }

        if ($blocksSynced == 1) {

            $numBlock = $gossip->chaindata->GetNextBlockNum() - 1; //-1 because add this block before
            $mini_hash = substr($blockSynced->hash,-12);
            $mini_hash_previous = substr($blockSynced->previous,-12);

            //We obtain the difference between the creation of the block and the completion of the mining
            $minedTime = date_diff(
                date_create(date('Y-m-d H:i:s', $blockSynced->timestamp)),
                date_create(date('Y-m-d H:i:s', $blockSynced->timestamp_end))
            );
            $blockMinedInSeconds = $minedTime->format('%im%ss');

            if ($transactionsSynced[0]->to == $gossip->coinbase) {
                Display::_printer("%Y%Rewarded%W% new block headers               %G%nonce%W%=".$blockSynced->nonce."      %G%elapsed%W%=".$blockMinedInSeconds."     %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash."      %G%number%W%=".$numBlock."");
            } else {
                Display::_printer("%Y%Imported%W% new block headers               %G%nonce%W%=".$blockSynced->nonce."      %G%elapsed%W%=".$blockMinedInSeconds."     %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash."      %G%number%W%=".$numBlock."");
            }
        } else if ($blocksSynced > 0) {
            Display::_printer("%Y%Imported%W% new blocks headers              %G%count%W%=".$blocksSynced."             %G%current%W%=".$currentBlocks."   %G%total%W%=".$totalBlocks);
        }

        return null;
    }

    /**
     *
     * We obtain the GENESIS block from Peer
     *
     * @param $ipAndPort
     * @return mixed
     */
    public static function GetGenesisBlock($ipAndPort) {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
            'action' => 'GETGENESIS'
        );

        if ($ip == NODE_BOOTSTRAP_TESTNET || $ip == NODE_BOOTSTRAP) {
            $infoPOST = Tools::postContent('https://'.$ip.'/gossip.php', $infoToSend);
        } else {
            $infoPOST = Tools::postContent('http://'.$ip.':'.$port.'/gossip.php', $infoToSend);
        }

        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the last block from peer
     *
     * @param $ipAndPort
     * @param $isTestNet
     * @return int
     */
    public static function GetLastBlockNum($ipAndPort) {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
            'action' => 'LASTBLOCKNUM'
        );

        if ($ip == NODE_BOOTSTRAP_TESTNET || $ip == NODE_BOOTSTRAP) {
            $infoPOST = Tools::postContent('https://'.$ip.'/gossip.php', $infoToSend);
        } else {
            $infoPOST = Tools::postContent('http://'.$ip.':'.$port.'/gossip.php', $infoToSend);
        }

        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the next 100 blocks from peer given a current height
     *
     * @param string $ipAndPort
     * @param string $lastBlockOnLocal
     * @return mixed
     */
    public static function SyncNextBlocksFrom($ipAndPort,$lastBlockOnLocal) {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'SYNCBLOCKS',
            'from' => $lastBlockOnLocal
        );
        if ($ip == NODE_BOOTSTRAP_TESTNET || $ip == NODE_BOOTSTRAP) {
            $infoPOST = Tools::postContent('https://'.$ip.'/gossip.php', $infoToSend);
        } else {
            $infoPOST = Tools::postContent('http://'.$ip.':'.$port.'/gossip.php', $infoToSend);
        }
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

}