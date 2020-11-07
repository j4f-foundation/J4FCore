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
    public static function SyncBlocks(Gossip &$gossip,array $nextBlocksToSyncFromPeer,int $currentBlocks,int $totalBlocks,string $ipAndPort) : bool {
        $blocksSynced = 0;
        $blockSynced = null;
        $transactionsSynced = null;

        //Check if node is connected on testnet or mainnet
        $isTestNet = ($gossip->chaindata->GetNetwork() == "testnet") ? true:false;

        if (is_array($nextBlocksToSyncFromPeer) && count($nextBlocksToSyncFromPeer) > 0) {
			$gossip->isBusy = true;
            foreach ($nextBlocksToSyncFromPeer as $object) {

				//Transform blockArray into blockObject
				$blockToImport = BlockChain::BlockArrayToObject($object);

                //Get last local block
                $lastBlock = $gossip->chaindata->GetLastBlock();

				if (@is_array($lastBlock) && !@empty($lastBlock)) {

					//Check if block received its OK
					if (!is_object($blockToImport) || ( is_object($blockToImport) && !isset($blockToImport->hash) )) {
						$return['status'] = true;
						$return['error'] = "5x00000000";
						$return['message'] = "Block received malformed";
						break;
					}

					$currentLocalTime = Tools::GetGlobalTime() + 5;
					//We check that the date of the block sent is not superior to mine
					if ($blockToImport->timestamp_end > $currentLocalTime) {
						$return['status'] = true;
						$return['error'] = "6x00000002";
						$return['message'] = "Block date is from the future";
						break;
					}

					//Check if my last block is the previous block of the block to import
					if ($lastBlock['block_hash'] == $blockToImport->previous) {

						//Define new height for next block
						$nextHeight = $lastBlock['height']+1;

						//Check if difficulty its ok
						$currentDifficulty = Blockchain::checkDifficulty($gossip->chaindata,null,$isTestNet);
						if ($currentDifficulty[0] != $blockToImport->difficulty) {
							Display::ShowMessageNewBlock('diffko',$lastBlock['height'],$blockToImport);
							break;
						}

						//If block is valid
						if ($blockToImport->isValid()) {
							//Check if rewarded transaction is valid, prevent hack money
							if ($blockToImport->isValidReward($nextHeight,$gossip->isTestNet)) {
								//We add block to blockchain
								if ($gossip->chaindata->addBlock($nextHeight,$blockToImport)) {
									//Make SmartContracts on local blockchain
									SmartContract::Make($gossip->chaindata,$blockToImport);

									//Call Functions of SmartContracts on local blockchain
									SmartContract::CallFunction($gossip->chaindata,$blockToImport);

									//Save block pointer
									$blockSynced = $blockToImport;

									$blocksSynced++;
								}
							} else {
								Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Reward transaction not valid");
								$gossip->chaindata->addPeerToBlackList($ipAndPort);
								return false;
							}
						} else {
							Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Has a block that I can not validate");
							$gossip->chaindata->addPeerToBlackList($ipAndPort);
							return false;
						}
					}

					//Check if my last block is the same height of the block to import
					else if ($lastBlock['block_previous'] == $blockToImport->previous && $lastBlock['block_hash'] != $blockToImport->hash) {

						//Check if difficulty its ok
						$currentDifficulty = Blockchain::checkDifficulty($gossip->chaindata,($lastBlock['height']-1),$isTestNet);
						if ($currentDifficulty[0] != $blockToImport->difficulty) {
							Display::ShowMessageNewBlock('novalid',$lastBlock['height'],$blockToImport);
							Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Difficulty hacked?");
							$gossip->chaindata->addPeerToBlackList($ipAndPort);
							break;
						}

						// We check if the time difference is equal orgreater than 2s
						$diffTimeBlocks = date_diff(
							date_create(date('Y-m-d H:i:s', $lastBlock['timestamp_end_miner'])),
							date_create(date('Y-m-d H:i:s', $blockToImport->timestamp_end))
						);
						$diffTimeSeconds = ($diffTimeBlocks->format('%i') * 60) + $diffTimeBlocks->format('%s');
						$diffTimeSeconds = ($diffTimeSeconds < 0) ? ($diffTimeSeconds * -1):$diffTimeSeconds;
						if ($diffTimeSeconds > 2) {
							Display::ShowMessageNewBlock('novalid',$lastBlock['height'],$blockToImport);
							Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Block in past");
							$gossip->chaindata->addPeerToBlackList($ipAndPort);
							break;
						}

						//Valid new block in same hiehgt to add in Blockchain
						$returnCode = Blockchain::isValidBlockMinedByPeerInSameHeight($gossip->chaindata,$lastBlock,$blockToImport);
						if ($returnCode == "0x00000000") {
							//Save block pointer
							$blockSynced = $blockToImport;

							$blocksSynced++;
						}
						else {
							if ($returnCode == "0x00000001") {
								Display::ShowMessageNewBlock('novalid',$lastBlock['height'],$blockToImport);
								Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Has a block that I can not validateHas a block that I can not validate");
								$gossip->chaindata->addPeerToBlackList($ipAndPort);
							}
							else if ($returnCode == "0x00000002") {
								Display::ShowMessageNewBlock('rewardko',$lastBlock['height'],$blockToImport);
								Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Reward transaction not valid");
								$gossip->chaindata->addPeerToBlackList($ipAndPort);
							}
							else if ($returnCode == "0x00000003") {
								Display::ShowMessageNewBlock('previousko',$lastBlock['height'],$blockToImport);
								Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Previous block fail");
								$gossip->chaindata->addPeerToBlackList($ipAndPort);
							}
							else if ($returnCode == "0x00000004") {
								Display::ShowMessageNewBlock('malformed',$lastBlock['height'],$blockToImport);
								Display::_warning("Peer ".$ipAndPort." added to blacklist       %G%reason%W%=Block malformed");
								$gossip->chaindata->addPeerToBlackList($ipAndPort);
							}
							else if ($returnCode == "0x00000005") {
								Display::ShowMessageNewBlock('noaccepted',$lastBlock['height'],$blockToImport);
							}
							break;
						}
					}

					//Check if same block
					else if ($lastBlock['block_previous'] == $blockToImport->previous && $lastBlock['block_hash'] == $blockToImport->hash) {
						continue;
					}
					else {
						//Improve peer system with autoSanity
						$numBlocksSanity = 200 + $blocksSynced;
						if ($lastBlock['height'] <= $numBlocksSanity)
							$numBlocksSanity = 1;
						$heightBlockFromRemove = $lastBlock['height'] - $numBlocksSanity;

						//Micro-Sanity and resync
						Display::_warning("Started Micro-Sanity       %G%height%W%=".$lastBlock['height']."	%G%newHeight%W%=".$heightBlockFromRemove);
						$gossip->chaindata->RemoveLastBlocksFrom($heightBlockFromRemove);
						Display::_warning("Finished Micro-Sanity, re-sync with peer");

						$ipAndPortToSync = Peer::GetHighestBlockFromPeers($gossip);
						Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$ipAndPortToSync);

						$gossip->syncing = true;
						$gossip->isBusy = false;
						return false;
					}
				}
            }
        }

        if ($blocksSynced == 1) {
			Display::ShowMessageNewBlock('imported',$lastBlock['height'],$blockSynced);
			return true;
        } else if ($blocksSynced > 0) {
            Display::print("%Y%Imported%W% new blocks         	%G%count%W%=".$blocksSynced."            %G%current%W%=".$currentBlocks."   %G%total%W%=".$totalBlocks);
			return true;
        }

		$gossip->isBusy = false;

		return false;
    }

	/**
	 * Check this peer and determine if need to sync with it
	 *
	 * @return bool
	 */
	public static function CheckPeerIfNeedToSync(Gossip &$gossip, string $peerIp, string $peerPort) : void {
		$lastBlock = $gossip->chaindata->GetLastBlock();

		$infoToSend = array(
			'action' => 'STATUSNODE'
		);
		$response = Socket::sendMessageWithReturn($peerIp,$peerPort,$infoToSend,2);

		//Check if response as ok
		if ($response != null && isset($response['status'])) {

			//Check if peer have same height block
			if ($response['result']['lastBlock'] > ($lastBlock['height']+1)) {

				//Check if have same GENESIS block from peer
				$peerGenesisBlock = Peer::GetGenesisBlock($peerIp.':'.$peerPort);
				$localGenesisBlock = $gossip->chaindata->GetGenesisBlock();

				//Check if i have genesis block (local blockchain)
				if ($localGenesisBlock != null) {
					if ($localGenesisBlock['block_hash'] == $peerGenesisBlock['block_hash']) {
						Tools::writeLog('SUBPROCESS::Selected peer '.$peer['ip'].':'.$peer['port'].' for sync');
						@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
						Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$peerIp.":".$peerPort);
					}
				}
				else {
					//Init sync
					Tools::writeLog('SUBPROCESS::Selected peer '.$peer['ip'].':'.$peer['port'].' for sync');
					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
					Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$peerIp.":".$peerPort);
				}
			}
		}
	}

	/**
	 * Select peer to sync with it
	 *
	 * @return string
	 */
	public static function SelectPeerToSync(Gossip &$gossip) : string {

		$highestChain = -1;

		if ($gossip->isTestNet)
			$ipAndPort = NODE_BOOTSTRAP_TESTNET.':'.NODE_BOOSTRAP_PORT_TESTNET;
		else
			$ipAndPort = NODE_BOOTSTRAP.':'.NODE_BOOSTRAP_PORT;

		$lastBlock = $gossip->chaindata->GetLastBlock();
		//Run subprocess peerAlive per peer
		$peers = $gossip->chaindata->GetAllPeersWithoutBootstrap();
		if (count($peers) > 0) {
			foreach ($peers as $peer) {
				$infoToSend = array(
					'action' => 'STATUSNODE'
				);

				$response = Socket::sendMessageWithReturn($peer['ip'],$peer['port'],$infoToSend,2);

				//Check if response as ok
				if ($response != null && isset($response['status'])) {

					//Check if peer have same height block
					if ($response['result']['lastBlock'] > ($lastBlock['height']+1)) {

						Tools::writeLog('SUBPROCESS::This peer '.$peer['ip'].':'.$peer['port'].' have more blocks than me');

						//Check if have same GENESIS block from peer
						$peerGenesisBlock = Peer::GetGenesisBlock($peer['ip'].':'.$peer['port']);
						$localGenesisBlock = $gossip->chaindata->GetGenesisBlock();

						//Check if i have genesis block (local blockchain)
						if ($localGenesisBlock != null) {
							if ($localGenesisBlock['block_hash'] == $peerGenesisBlock['block_hash']) {

								Tools::writeLog('SUBPROCESS::Selected peer '.$peer['ip'].':'.$peer['port'].' for sync');

								//Sync with peer (have more blocks)
								if ($response['result']['lastBlock'] > $highestChain) {
									$highestChain = $response['result']['lastBlock'];
									$ipAndPort = $peer['ip'].':'.$peer['port'];
								}
							}
						}
						else {
							//Init sync
							if ($response['result']['lastBlock'] > $highestChain) {
								$ipAndPort = $peer['ip'].':'.$peer['port'];
							}
						}
					}
				}
			}
		}

		return $ipAndPort;
	}

    /**
     *
     * We obtain the GENESIS block from Peer
     *
     * @param $ipAndPort
     * @return mixed
     */
    public static function GetGenesisBlock(string $ipAndPort) : array {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
            'action' => 'GETGENESIS'
        );

        $infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
        if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1)
            return $infoPOST['result'];
        else
            return [];
    }

    /**
     *
     * We get the last block from peer
     *
     * @param $ipAndPort
     * @param $isTestNet
     * @return int
     */
    public static function GetLastBlockNum(string $ipAndPort) : int {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
            'action' => 'LASTBLOCKNUM'
        );

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
        if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1)
            return $infoPOST['result'];
        else
            return -1;
    }

    /**
     *
     * We get the next 100 blocks from peer given a current height
     *
     * @param string $ipAndPort
     * @param string $lastBlockOnLocal
     * @return mixed
     */
    public static function SyncNextBlocksFrom(string $ipAndPort,int $lastBlockOnLocal) : array {

        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
			'action' => 'SYNCBLOCKS',
            'from' => $lastBlockOnLocal
        );

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend,30);
		if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1) {
			if (is_array($infoPOST['result']) && !empty($infoPOST['result'])) {
				return $infoPOST['result'];
			}
			else {
				return [];
			}
		}
        else
            return [];
	}

	/**
     *
     * We get the peer with more blocks
     *
     * @param Gossip $gossip
     * @return string
     */
    public static function GetHighestBlockFromPeers(Gossip &$gossip) : string {

		//Default select a bootstrap node
		if ($gossip->isTestNet)
			$selectedPeer = NODE_BOOTSTRAP_TESTNET.':'.NODE_BOOSTRAP_PORT_TESTNET;
		else
			$selectedPeer = NODE_BOOTSTRAP.':'.NODE_BOOSTRAP_PORT;

		$numBlocksPeer = 0;

		//Data to send
        $infoToSend = array(
			'action' => 'LASTBLOCKNUM'
        );

		$myPeers = $gossip->chaindata->GetAllPeers();

		//Check all peers and select highest block peer
		if (!empty($myPeers)) {
			foreach($myPeers as $peerInfo) {
				if (Socket::isAlive($peerInfo["ip"],$peerInfo["port"])) {
					$infoPOST = Socket::sendMessageWithReturn($peerInfo["ip"],$peerInfo["port"],$infoToSend,10);
					if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1) {
						if (intval($infoPOST['result']) > $numBlocksPeer) {
							$numBlocksPeer = intval($infoPOST['result']);
							$selectedPeer = $peerInfo["ip"] . ":" . $peerInfo["port"];
						}
					}
				}
			}
		}

		return $selectedPeer;
	}

	/**
     *
     * We get pending transactions from Peer
     *
     * @param string $ipAndPort
     * @return int|mixed
     */
    public static function GetPendingTransactions(string $ipAndPort) {

		//Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        $infoToSend = array(
            'action' => 'GETPENDINGTRANSACTIONS'
        );

		$infoPOST = Socket::sendMessageWithReturn($ip,$port,$infoToSend);
		if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1)
			return $infoPOST['result'];
		else
			return null;
    }

	/**
	 * Get more peers from peer
	 * @param Gossip $gossip
	 * @param string $ip
	 * @param string $port
	 * @return void
	 */
	public static function GetMorePeers(Gossip &$gossip, string $ip, string $port) : void {
		//Data to send
		$infoToSend = array(
            'action' => 'GETPEERS'
        );
		foreach($gossip->peers as $ipAndPort => $v) {
			$peer = explode(":", $ipAndPort);
			$infoPOST = Socket::sendMessageWithReturn($peer[0],$peer[1],$infoToSend,5);
			if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1) {
				if (is_array($infoPOST['result']) && !empty($infoPOST['result'])) {
					foreach ($infoPOST['result'] as $newPeerInfo) {
						if (count($gossip->peers) < PEERS_MAX) {
							$gossip->_addPeer($newPeerInfo['ip'],$newPeerInfo['port']);
						}
					}
				}
			}
		}
	}

	/**
	 * Check if peer is blacklisted
	 * @param Gossip $gossip
	 * @param string $ip
	 * @param string $port
	 * @return bool
	 */
	public static function CheckIfBlacklisted(Gossip &$gossip, string $ip, string $port) : bool {
		return $gossip->chaindata->CheckIfPeerIsBlacklisted($ip,$port);
	}
}
