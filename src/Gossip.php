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

use React\Socket\ConnectionInterface;

class Gossip {

    public $name;
    public $key;
    public $port;
    public $ip;
    public $enable_mine;
    public $pending_transactions;
    public $coinbase;
    public $syncing;
    public $config;
    public $peers = array();
    public $difficulty;
    public $isTestNet;

    /** @var DB $chaindata */
    public $chaindata;
    private $make_genesis;
    private $bootstrap_node;
    private $connected_to_bootstrap;
    private $openned_ports;
	private $lastBlock_BootstrapNode;

    private $loop_x5 = 0;
	private $loop_x10 = 0;
	private $loop_x15 = 0;

	public $isBusy = false;

    /**
     * Gossip constructor
     *
     * @param DB        $db
     * @param string    $name
     * @param string    $ip
     * @param string    $port
     * @param bool      $enable_mine
     * @param bool      $make_genesis_block
     * @param bool      $bootstrap_node
     * @param bool      $isTestNet
     */
    public function __construct($db, $name, $ip, $port, $enable_mine, $make_genesis_block=false, $bootstrap_node = false, $isTestNet=false, $sanityBlockchain=-1)
    {
		//Clear screen
		Display::ClearScreen();

		//Init Display message
		Display::print("Welcome to the %G%J4F node%W% - %G%Version%W%: " . VERSION);
		Display::print("Maximum peer count                       %G%value%W%=".PEERS_MAX);
		Display::print("PeerID %G%".Tools::GetIdFromIpAndPort($ip,$port));

		$this->make_genesis = $make_genesis_block;
		$this->bootstrap_node = $bootstrap_node;
		$this->isTestNet = $isTestNet;
		$this->enable_mine = $enable_mine;

		//Save node info
		$db->SetConfig('node_name',$name);
		$db->SetConfig('node_ip',$ip);
		$db->SetConfig('node_port',$port);
		$db->SetConfig('node_version',VERSION);

		//Set network config
		if ($this->isTestNet)
			$db->SetConfig('network','testnet');
		else
			$db->SetConfig('network','mainnet');

		//Set miner config
		if ($this->enable_mine)
			$db->SetConfig('miner','on');
		else
			$db->SetConfig('miner','off');

		//Set bootstrap config
		if ($this->bootstrap_node)
			$db->SetConfig('isBootstrap','on');
		else
			$db->SetConfig('isBootstrap','off');

		//Set default hashrate to 0
		$db->SetConfig('hashrate','0');

		//We declare that we are not synchronizing
		$this->syncing = false;
		$db->SetConfig('syncing','off');
		$db->SetConfig('p2p','off');

		$this->name = $name;
		$this->ip = $ip;
		$this->port = $port;

		//We create default folders
		Tools::MakeDataDirectory();

		//Clear TMP files
		Tools::clearTmpFolder();
		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.'node_log');

		//Default miners stopped
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

		//Update MainThread time for subprocess
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

		//Instance the pointer to the chaindata and get config
		$this->chaindata = $db;
		$this->config = $this->chaindata->GetAllConfig();

		//We started with that we do not have pending transactions
		$this->pending_transactions = array();

		//We create the Wallet for the node
		$this->key = new Key(Wallet::LoadOrCreate('coinbase',null));

		if (strlen($this->key->pubKey) != 451) {
			Display::_error("Can't get the public/private key");
			Display::_error("Make sure you have openssl installed and activated in php");
			exit();
		}
		$this->coinbase = Wallet::GetWalletAddressFromPubKey($this->key->pubKey);
		Display::print("Coinbase detected: %LG%".$this->coinbase);

		//We cleaned the table of peers
		if (!$this->bootstrap_node)
			$this->chaindata->truncate("peers");

		//By default we mark that we are not connected to the bootstrap and that we do not have ports open for P2P
		$this->connected_to_bootstrap = false;
		$this->openned_ports = false;

		//Get last block from Bootstrap
		if (!$this->bootstrap_node)
			$this->lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
		else
			$this->lastBlock_BootstrapNode = $this->chaindata->GetNextBlockNum();

		//Save pointer of Gossip
		$gossip = $this;

		//Check integrity of last 20 blocks
		Blockchain::checkIntegrity($gossip->chaindata,null,20);

		if ($sanityBlockchain > 0) {
			$gossip->SanityFromBlockHeight($sanityBlockchain);
			exit();
		}

		$loop = React\EventLoop\Factory::create();

		$config = React\Dns\Config\Config::loadSystemConfigBlocking();
		$server = $config->nameservers ? reset($config->nameservers) : '8.8.8.8';

		$factory = new React\Dns\Resolver\Factory();
		$dns = $factory->create($server, $loop);

		//Delayed Init Function
		$loop->addTimer(0, function () use ($gossip) {

	        //WE GENERATE THE GENESIS BLOCK
	        if ($gossip->make_genesis) {
	            if(!$gossip->isTestNet)
	                GenesisBlock::make($gossip->chaindata,$gossip->coinbase,$gossip->key->privKey,$gossip->isTestNet,bcadd("2","0",18));
	            else
	                GenesisBlock::make($gossip->chaindata,$gossip->coinbase,$gossip->key->privKey,$gossip->isTestNet,bcadd("99999999999999999999999999999999","0",18));
	        }

	        //We are a BOOTSTRAP node
	        else if ($gossip->bootstrap_node) {
	            if ($gossip->isTestNet)
	                Display::print("%Y%BOOTSTRAP NODE %W%(%G%TESTNET%W%) loaded successfully");
	            else
	                Display::print("%Y%BOOTSTRAP NODE %W%loaded successfully");

	            $lastBlock = $gossip->chaindata->GetLastBlock(false);

	            Display::print("Height: %G%".$lastBlock['height']);

	            $gossip->difficulty = Blockchain::checkDifficulty($gossip->chaindata,null, $gossip->isTestNet)[0];

	            Display::print("LastBlock: %G%".$lastBlock['block_hash']);
	            Display::print("Difficulty: %G%".$gossip->difficulty);
	            Display::print("Current peers: %G%".count($gossip->chaindata->GetAllPeers()));

	            //Check peers status
	            $gossip->CheckConnectionWithPeers($gossip);
	        }

	        //If we already have information, we establish the loaded state
	        else {
	            //We connect to the Bootstrap node
	            $gossip->_addBootstrapNode($gossip);

                //If we do not have open ports, we can not continue
                if (!$gossip->connected_to_bootstrap) {
                    Display::_error("Impossible to establish a P2P connection with Bootstrap");
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

				//If we do not have open ports, we can not continue
                if (!$gossip->openned_ports) {
                    Display::_error("Impossible to establish a P2P connection");
                    Display::_error("Check that it is accessible from the internet: %Y%tcp://".$gossip->ip.":".$gossip->port);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }


                //Set p2p ON
                $gossip->chaindata->SetConfig('p2p','on');

                //We ask the BootstrapNode to give us the information of the connected peers
                $peersNode = BootstrapNode::GetPeers($gossip->chaindata,$gossip->isTestNet);
                if (is_array($peersNode) && !empty($peersNode)) {
                    $maxRand = PEERS_MAX;
                    foreach ($peersNode as $peer) {
                        if (trim($gossip->ip).":".trim($gossip->port) != trim($peer['ip']).":".trim($peer['port'])) {
                            if (count($gossip->peers) < PEERS_MAX) {
                                $gossip->_addPeer(trim($peer['ip']),trim($peer['port']));
                            }
                        }
                    }
                }

                if (count($gossip->peers) < PEERS_REQUIRED) {
                    Display::_error("there are not enough peers       count=".count($gossip->peers)."   required=".PEERS_REQUIRED);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

				$lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($gossip->chaindata,$gossip->isTestNet);
                $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();

                //We check if we need to synchronize or not
                if ($lastBlock_LocalNode < $lastBlock_BootstrapNode) {
                    Display::print("%LR%DeSync detected %W%- Downloading blocks (%G%".$lastBlock_LocalNode."%W%/%Y%".$lastBlock_BootstrapNode.")");

					//We declare that we are synchronizing
                    $gossip->syncing = true;

                    $gossip->chaindata->SetConfig('syncing','on');

					//Select a peer to sync
					$ipAndPort = Peer::SelectPeerToSync($gossip);
					$ipPort = explode(':',$ipAndPort);
					Display::print("Selected peer to sync -> %G%".Tools::GetIdFromIpAndPort($ipPort[0],$ipPort[1]));
					Tools::writeLog('Selected peer to sync			%G%'.Tools::GetIdFromIpAndPort($ipPort[0],$ipPort[1]));
				}

				//If we do not have the GENESIS block, we download it from Peer (HighestChain)
				if ($lastBlock_LocalNode == 0) {
					//Make Genesis from Peer
					$genesis_block_peer = Peer::GetGenesisBlock($ipAndPort);
					$genesisMakeBlockStatus = GenesisBlock::makeFromPeer($genesis_block_peer,$gossip->chaindata);

					if ($genesisMakeBlockStatus)
						Display::print("%Y%Imported%W% GENESIS block header               %G%count%W%=1");
					else {
						Display::_error("Can't make GENESIS block");
						if (IS_WIN)
							readline("Press any Enter to close close window");
						exit();
					}
				}
				else {
					$lastBlock = $gossip->chaindata->GetLastBlock(false);

		            Display::print("Height: %G%".$lastBlock['height']);

					$gossip->difficulty = Blockchain::checkDifficulty($gossip->chaindata,null,$gossip->isTestNet)[0];

		            Display::print("LastBlock: %G%".$lastBlock['block_hash']);
		            Display::print("Difficulty: %G%".$gossip->difficulty);
		            Display::print("Current peers: %G%".count($gossip->chaindata->GetAllPeers()));
				}

                //Check if have same GENESIS block from BootstrapNode
                $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($gossip->chaindata,$gossip->isTestNet);
                $genesis_block_local = $gossip->chaindata->GetGenesisBlock();
                if ($genesis_block_local['block_hash'] != $genesis_block_bootstrap['block_hash']) {
                    Display::_error("%Y%GENESIS BLOCK NO MATCH%W%    genesis block does not match the block genesis of bootstrapNode");
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }
	        }


			if ($gossip->make_genesis)
				return;

			if (!$gossip->connected_to_bootstrap && !$gossip->bootstrap_node)
				return;

			//Get pending transactions from bootstrap
			$gossip->GetPendingTransactions();

		});

		//Check peer status every 60s
		$loop->addPeriodicTimer(60, function() use (&$gossip) {
			$gossip->CheckConnectionWithPeers($gossip);
		});

		//Loop every 5s
		$loop->addPeriodicTimer(5, function() use (&$gossip) {
			//If have miners show log
			if ($gossip->enable_mine)
				$this->ShowInfoSubprocessMiners();

			if ($gossip->syncing)
				return;

			if (!$gossip->connected_to_bootstrap || !$gossip->bootstrap_node)
				return;

			//Get Pending transactions from network
			$gossip->GetPendingTransactions();

			//Get lastblockNum from BootstrapNode
			if (!$gossip->bootstrap_node)
				$gossip->lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($gossip->chaindata,$gossip->isTestNet);
			else
				$gossip->lastBlock_BootstrapNode = $gossip->chaindata->GetNextBlockNum();
		});

		//General loop of node
		$loop->addPeriodicTimer(1, function() use (&$gossip) {

			//We establish the title of the process
			$gossip->SetTitleProcess();

			//Update MainThread time for subprocess
			Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

			if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 3)
				$gossip->ShowLogSubprocess();

			//Check if need to sync with any peer
			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer"))
				$gossip->syncing = true;

			//If we are not synchronizing
			if (!$gossip->syncing) {

				//We have miner, start miner process
				if ($gossip->enable_mine) {
					$gossip->mineProcess();
				}
			}

			//If we are synchronizing and we are connected with the bootstrap
			else if ($gossip->syncing) {

				if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer")) {
					$ipAndPort = file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer");
				}
				else {
					//Select a peer to sync
					$ipAndPort = Peer::SelectPeerToSync($gossip);
				}

				//Check if have ip and port
				if (strlen($ipAndPort) > 0) {

					//We get the last block from peer
					$lastBlock_PeerNode = Peer::GetLastBlockNum($ipAndPort);
					$lastBlock_LocalNode = $gossip->chaindata->GetNextBlockNum();

					if ($lastBlock_LocalNode < $lastBlock_PeerNode) {
						$nextBlocksToSyncFromPeer = Peer::SyncNextBlocksFrom($ipAndPort,$lastBlock_LocalNode);
						$resultSync = Peer::SyncBlocks($gossip,$nextBlocksToSyncFromPeer,$lastBlock_LocalNode,$lastBlock_PeerNode,$ipAndPort);

						//If dont have result of sync, stop sync with this peer
						if ($resultSync == null) {
							//Delete sync file
							@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
						}
					} else {
						$gossip->syncing = false;

						$gossip->chaindata->SetConfig('syncing','off');

						//Delete sync file
						@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");

						//We check the difficulty
						$gossip->difficulty = Blockchain::checkDifficulty($gossip->chaindata, null, $gossip->isTestNet)[0];

						//We clean the table of blocks mined by the peers
						$gossip->chaindata->truncate("txnpool");
					}
				}
			}

			//If isnt bootstrap and connected to bootstrap
			if (!$gossip->bootstrap_node && $gossip->connected_to_bootstrap) {
				//We get the last block from the BootstrapNode
				//$lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($gossip->chaindata,$gossip->isTestNet);
				$lastBlock_LocalNode = $gossip->chaindata->GetNextBlockNum();

				//We check if we need to synchronize or not
				if ($lastBlock_LocalNode < $gossip->lastBlock_BootstrapNode) {
					//Display::print("%LR%DeSync detected %W%- Downloading blocks (%G%" . $lastBlock_LocalNode . "%W%/%Y%" . $lastBlock_BootstrapNode . ")");

					if ($gossip->enable_mine && @file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
						//Stop minning subprocess
						Tools::clearTmpFolder();
						Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
						Display::print("%Y%Miner work cancelled%W%     Imported new headers");
					}

					//We declare that we are synchronizing
					$gossip->syncing = true;

					$gossip->chaindata->SetConfig('syncing','on');
				}
			}

			//If is bootstrap
			if ($gossip->bootstrap_node && !$gossip->syncing) {
				if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer")) {
					//We declare that we are synchronizing
					$gossip->syncing = true;

					$gossip->chaindata->SetConfig('syncing','on');

					if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 1)
						Display::_debug("Getting blocks from peer: " . @file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer"));
				}
			}
		});

		//Start Socket
		$socket = new React\Socket\Server('0.0.0.0:'.$gossip->port, $loop, array(
		    'tcp' => array(
		        'backlog' => 200,
		        'so_reuseport' => true,
		        'ipv6_v6only' => false
		    )
		));

		Display::print("%LP%Network%W% Listening on		%G%{$gossip->ip}%W%:%G%{$gossip->port}%W%");

		//Gossip
		$socket->on('connection', function(ConnectionInterface $connection) use (&$gossip) {

			$dataFromPeer = '';

			$connection->on('data', function($data) use (&$connection, &$dataFromPeer, &$gossip){
				if (strlen($data) > 0) {
					//Concatenate data from peer
					$dataFromPeer .= $data;

					//Check if msgFromPeer have a correct format
					$msgFromPeer = @json_decode($dataFromPeer,true);
					if (@is_array($msgFromPeer)) {

						$return = array(
							'status'    => false,
							'error'     => null,
							'message'   => null,
							'result'    => null
						);

						$address = $connection->getRemoteAddress();
						$address = str_replace('tcp://','',$address);

						if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 2)
							Display::print("%LP%Network%W% Message from client		%G%Address%W%=".$address."	%G%msg%W%=" . substr($dataFromPeer,0,32)."...");

						switch (strtoupper($msgFromPeer['action'])) {
							case 'GETPENDINGTRANSACTIONS':
								$return['status'] = true;
								$return['result'] = $gossip->chaindata->GetTxnFromPool();
							break;
							case 'ADDPENDINGTRANSACTIONS':
								if (isset($msgFromPeer['txs'])) {
									$return['status'] = true;
									$return['result'] = $gossip->chaindata->addTxnsToPoolByPeer(@unserialize($msgFromPeer['txs']));
								}
							break;
							case 'GETBLOCKBYHASH':
								if (isset($msgFromPeer['hash'])) {
									//We get a block given a hash
									$return['status'] = true;
									$return['result'] = $gossip->chaindata->GetBlockByHash($msgFromPeer['hash']);
								}
								break;
							case 'PING':
								$return['status'] = true;
							break;
							case 'GETPEERS':
								$return['status'] = true;
								$return['result'] = $gossip->chaindata->GetAllPeers();
							break;
							case 'MINEDBLOCK':

								//Check if blockchain is syncing
								if ($gossip->syncing) {
									$return['status'] = true;
									$return['error'] = "3x00000000";
									$return['message'] = "Blockchain syncing";
									break;
								}

								//Check if im busy
								if ($gossip->isBusy) {
									$return['status'] = true;
									$return['error'] = "0x10000003";
									$return['message'] = "Busy";
									break;
								}

								//Get current network
								$isTestNet = ($gossip->chaindata->GetConfig('network') == 'testnet') ? true:false;

								//Get last block
								$lastBlock = $gossip->chaindata->GetLastBlock();

								//Check if have previous block hash and new block info
								if (!isset($msgFromPeer['hash_previous']) || !isset($msgFromPeer['block'])) {
									$return['status'] = true;
									$return['error'] = "0x10000002";
									$return['message'] = "Need hashPrevious & blockInfo";
									break;
								}

								/** @var Block $blockMinedByPeer */
								$blockMinedByPeer = Tools::objectToObject(unserialize($msgFromPeer['block']),"Block");

								//Determine isBusy
								$gossip->isBusy = true;

								//Check if block received its OK
								if (!is_object($blockMinedByPeer) || ( is_object($blockMinedByPeer) && !isset($blockMinedByPeer->hash) )) {
									$return['status'] = true;
									$return['error'] = "5x00000000";
									$return['message'] = "Block received malformed";
									Display::_error('Block received malformed');
									break;
								}

								$currentLocalTime = Tools::GetGlobalTime() + 5;
								//We check that the date of the block sent is not superior to mine
								if ($blockMinedByPeer->timestamp_end > $currentLocalTime) {
									$return['status'] = true;
									$return['error'] = "6x00000002";
									$return['message'] = "Block date is from the future";
									break;
								}

								//Same block
								if ($lastBlock['block_hash'] == $blockMinedByPeer->hash) {
									$return['status'] = true;
									$return['error'] = "0x00000000";
									break;
								}

								//Same height, different hash block
								if ($lastBlock['block_previous'] == $blockMinedByPeer->previous && $lastBlock['block_hash'] != $blockMinedByPeer->hash) {

									//Check if difficulty its ok
									$currentDifficulty = Blockchain::checkDifficulty($gossip->chaindata,($lastBlock['height']-1),$isTestNet);
									if ($currentDifficulty[0] != $blockMinedByPeer->difficulty) {
										$return['status'] = true;
										$return['error'] = "4x00000000";
										$return['message'] = "Block difficulty hacked?";
										break;
									}

									// We check if the time difference is equal orgreater than 2s
									$diffTimeBlocks = date_diff(
							            date_create(date('Y-m-d H:i:s', $lastBlock['timestamp_end_miner'])),
							            date_create(date('Y-m-d H:i:s', $blockMinedByPeer->timestamp_end))
							        );
									$diffTimeSeconds = ($diffTimeBlocks->format('%i') * 60) + $diffTimeBlocks->format('%s');
									$diffTimeSeconds = ($diffTimeSeconds < 0) ? ($diffTimeSeconds * -1):$diffTimeSeconds;
									if ($diffTimeSeconds >= 2) {
										$return['status'] = true;
										$return['error'] = "5x00000000";
										$return['result'] = 'sanity';
										break;
									}

									//Valid new block in same hiehgt to add in Blockchain
									$returnCode = Blockchain::isValidBlockMinedByPeerInSameHeight($gossip->chaindata,$lastBlock,$blockMinedByPeer);
									if ($returnCode == "0x00000000") {

										Display::ShowMessageNewBlock('sanity',$lastBlock['height'],$blockMinedByPeer);

										$return['status'] = true;
										$return['error'] = $returnCode;

									}
									//Block no accepted, suggest microsanity
									else {
										if ($returnCode == "0x00000001") {
											Display::ShowMessageNewBlock('novalid',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000002") {
											Display::ShowMessageNewBlock('rewardko',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000003") {
											Display::ShowMessageNewBlock('previousko',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000004") {
											Display::ShowMessageNewBlock('malformed',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000005") {
											Display::ShowMessageNewBlock('noaccepted',$lastBlock['height'],$blockMinedByPeer);
										}

										$return['status'] = true;
										$return['error'] = $returnCode;
										$return['result'] = 'sanity';
									}

									//If have miner enabled, stop all miners
									Tools::clearTmpFolder();
									Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

									//Check integrity of my blockchain
									Blockchain::checkIntegrity($gossip->chaindata,null,50);

									break;
								}

								//Check if is a next block
								else if ($lastBlock['block_hash'] == $blockMinedByPeer->previous) {

									//We check that date of new block is not less than the last block
									if ($blockMinedByPeer->timestamp_end <= $lastBlock['timestamp_end_miner']) {
										//Tools::writeLog('GOSSIP_MINEDBLOCK ('.Tools::GetIdFromIpAndPort($_SERVER['REMOTE_ADDR'],0).') -> Error 6x00000000');
										$return['status'] = true;
										$return['error'] = "6x00000000";
										$return['message'] = "Block date is from the past";
										break;
									}

									//Check if i have this block
									$blockAlreadyAdded = $gossip->chaindata->GetBlockByHash($blockMinedByPeer->hash);
									if ($blockAlreadyAdded != null) {
										$return['status'] = true;
										$return['error'] = '7x00000000';
										break;
									}

									//Check if difficulty its ok
									$currentDifficulty = Blockchain::checkDifficulty($gossip->chaindata,null,$isTestNet);
									if ($currentDifficulty[0] != $blockMinedByPeer->difficulty) {
										$return['status'] = true;
										$return['error'] = "4x00000000";
										$return['message'] = "Block difficulty hacked?";
										break;
									}

									//Valid block to add in Blockchain
									$returnCode = Blockchain::isValidBlockMinedByPeer($gossip->chaindata,$lastBlock,$blockMinedByPeer);
									if ($returnCode == "0x00000000") {

										Display::ShowMessageNewBlock('imported',$lastBlock['height'],$blockMinedByPeer);

										//If have miner enabled, stop all miners
										Tools::clearTmpFolder();
										Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

										if ( isset( $msgFromPeer['node_ip'] ) && isset($msgFromPeer['node_port']) ) {
											Tools::sendBlockMinedToNetworkWithSubprocess($gossip->chaindata,$blockMinedByPeer,array(
												'ip' => $msgFromPeer['node_ip'],
												'port' => $msgFromPeer['node_port']
											));
										}
										else {
											Tools::sendBlockMinedToNetworkWithSubprocess($gossip->chaindata,$blockMinedByPeer,array());
										}

										$return['status'] = true;
										$return['error'] = $returnCode;
										$return['message'] = "Block added";
									}
									else {
										if ($returnCode == "0x00000001") {
											Display::ShowMessageNewBlock('novalid',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000002") {
											Display::ShowMessageNewBlock('rewardko',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000003") {
											Display::ShowMessageNewBlock('previousko',$lastBlock['height'],$blockMinedByPeer);
										}
										else if ($returnCode == "0x00000004") {
											Display::ShowMessageNewBlock('malformed',$lastBlock['height'],$blockMinedByPeer);
										}
										else {
											Display::ShowMessageNewBlock('unkown',$lastBlock['height'],$blockMinedByPeer);
										}
										$return['status'] = true;
										$return['error'] = $returnCode;
									}
									break;
								}

								else {

									// if height of block submitted is lower than our current height, send sanity to peer
									if ($msgFromPeer['height'] < $lastBlock['height']) {
										$return['status'] = true;
										$return['error'] = 'Block old';
										$return['result'] = 'sanity';
										//Display::_warning('Peer '.Tools::GetIdFromIpAndPort($msgFromPeer['node_ip'],$msgFromPeer['node_port']).' need to be sync with me');
									}
									else if (($msgFromPeer['height'] - $lastBlock['height']) > 10) {

										//Check integrity of my blockchain
										Blockchain::checkIntegrity($gossip->chaindata,null,50);

										Tools::clearTmpFolder();

										// Start microsanity with this peer
										if (strlen($msgFromPeer['node_ip'] > 0) && strlen($msgFromPeer['node_port']) > 0)
											Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$msgFromPeer['node_ip'].":".$msgFromPeer['node_port']);
										Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
									}
									break;
								}
							break;
							case 'HELLOBOOTSTRAP':
								if (isset($msgFromPeer['client_ip']) && isset($msgFromPeer['client_port'])) {
									$return['status'] = true;

									$infoToSend = array(
										'action' => 'HELLO_PONG'
									);
									if (Socket::isAlive($msgFromPeer['client_ip'],$msgFromPeer['client_port'])) {
										$gossip->chaindata->addPeer($msgFromPeer['client_ip'],$msgFromPeer['client_port']);
										Display::print('%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($msgFromPeer['client_ip'],$msgFromPeer['client_port']));
										$return['result'] = "p2p_on";
									}
									else
										$return['result'] = "p2p_off";
								}
							BREAK;
							case 'HELLO':
								if (isset($msgFromPeer['client_ip']) && isset($msgFromPeer['client_port'])) {
									$return['status'] = true;
									$gossip->chaindata->addPeer($msgFromPeer['client_ip'],$msgFromPeer['client_port']);
									Display::print('%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($msgFromPeer['client_ip'],$msgFromPeer['client_port']));
								} else {
									$return['message'] = "No ClientIP or ClientPort defined";
								}
							BREAK;
							case 'LASTBLOCKNUM':
								$return['status'] = true;
								$return['result'] = $gossip->chaindata->GetNextBlockNum();
							break;
							case 'STATUSNODE':
								$return['status'] = true;
								$config = $gossip->chaindata->GetAllConfig();
								$return['result'] = array(
									'hashrate'      => $config['hashrate'],
									'miner'         => $config['miner'],
									'network'       => $config['network'],
									'p2p'           => $config['p2p'],
									'syncing'       => $config['syncing'],
									'dbversion'     => $config['dbversion'],
									'nodeversion'   => $config['node_version'],
									'lastBlock'     => $gossip->chaindata->GetNextBlockNum()
								);
							break;
							case 'GETGENESIS':
								$return['status'] = true;
								$return['result'] = $gossip->chaindata->GetGenesisBlock();
							break;
							case 'SYNCBLOCKS':
								if (isset($msgFromPeer['from'])) {

									//Check integrity of my blockchain
									$integrityOk = Blockchain::checkIntegrity($gossip->chaindata,($msgFromPeer['from']+100),150);

									if ($integrityOk) {
										$return['status'] = true;
										$return['result'] = $gossip->chaindata->SyncBlocks($msgFromPeer['from']);
									}
									else {
										$return['status'] = true;
										$return['result'] = null;
										$return['error'] = 'IntegrityKO';
									}
								}
							break;
							case 'HELLO_PONG':
								$return['status'] = true;
							break;
						}

						//Determine isBusy
						if (strtoupper($msgFromPeer['action']) == 'MINEDBLOCK')
							$gossip->isBusy = false;

						$connection->write(@json_encode($return));
						$connection->end();
						//$connection->close();
					}
				}
		    });
		});

		//Start node
		$loop->run();
		$socket->close();
	}

    /**
     * We add the BootstrapNode
     *
     * @return  bool
     */
    public function _addBootstrapNode(&$gossip) {

        if ($gossip->isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $infoToSend = array(
            'action' => 'HELLOBOOTSTRAP',
            'client_ip' => $gossip->ip,
            'client_port' => $gossip->port
        );

        $response = Socket::sendMessageWithReturn($ip, $port, $infoToSend, 1);
		if ($response != null && isset($response['status'])) {
			$gossip->chaindata->addPeer($ip, $port);

			$gossip->peers[] = array($ip.':'.$port => $ip,$port);

			if ($gossip->isTestNet)
				Display::print("%LP%Network%W% Connected to peer		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));
			else
				Display::print("%LP%Network%W% Connected to peer		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));

			$gossip->openned_ports = true;
			$gossip->connected_to_bootstrap = true;
		}
		else {
			$gossip->openned_ports = false;
			$gossip->connected_to_bootstrap = false;

			if ($gossip->isTestNet)
				Display::_error("%LP%Network%W% Can't connect to BootstrapNode		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));
			else
				Display::_error("%LP%Network%W% Can't connect to BootstrapNode		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));
		}
    }

    /**
     * We add to the chaindata
     * First we check if we have a connection to the
     *
     * @param   string    $ip
     * @param   string    $port
     * @param   bool      $displayMessage
     * @return  bool
     */
    public function _addPeer($ip, $port,$displayMessage=true) {

        if (!$this->chaindata->haveThisPeer($ip,$port) && ($this->ip != $ip || ($this->ip == $ip && $this->port != $port))) {

            $infoToSend = array(
                'action' => 'HELLO',
                'client_ip' => $this->ip,
                'client_port' => $this->port
            );
            $response = Socket::sendMessageWithReturn($ip, $port, $infoToSend, 1);
            if ($response != null && isset($response['status'])) {
                if ($response['status'] == true) {
                    $this->chaindata->addPeer($ip, $port);
                    $this->peers[] = array($ip.':'.$port => $ip,$port);
                    if ($displayMessage)
						Display::print('%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($ip,$port));
                }
                return true;
            }
            else {
                if ($displayMessage)
					Display::_warning("%LP%Network%W% Can't connect to peer		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));
                return false;
            }
        }
    }

    /**
     * Check the connection with the peers, if they do not respond remove them
     */
    public function CheckConnectionWithPeers(&$gossip) {

        //Run subprocess peerAlive per peer
        $peers = $gossip->chaindata->GetAllPeersWithoutBootstrap();

        if (count($peers) > 0) {

            if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 1)
                Display::_debug("Checking status of peers                 %G%count%W%=".count($peers));

            Tools::writeLog('Checking status of peers count='.count($peers));

            //Run subprocess propagation
            Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'peerAlive',"",-1);
        }
    }

    /**
     * Set the title of the process with useful information
     */
    public function SetTitleProcess() {
        $title = "J4F Node";
        $title .= " | PeerID: " . substr(PoW::hash($this->ip . $this->port), 0, 18);
        if ($this->connected_to_bootstrap || $this->bootstrap_node)
            $title .= " | BootstrapNode: Connected";
        else
            $title .= " | BootstrapNode: Disconnected";
        $title .= " | Peers: " . count($this->chaindata->GetAllPeers());

        if ($this->syncing)
            $title .= " | Blockchain: Synchronizing";
        else
            $title .= " | Blockchain: Synchronized";

        if ($this->enable_mine)
            $title .= " | Minning";

        if ($this->isTestNet)
            $title .= " | TESTNET";
        else
            $title .= " | MAINNET";

        cli_set_process_title($title);
    }

    /**
     * We get the pending transactions from BootstrapNode
     */
    public function GetPendingTransactions() {
        if (!$this->bootstrap_node) {

            //Get transactions from peer
            $transactionsByPeer = BootstrapNode::GetPendingTransactions($this->chaindata,$this->isTestNet);

            //Check if have transactions by peer
            if ($transactionsByPeer != null && is_array($transactionsByPeer) && !empty($transactionsByPeer)) {
				$this->chaindata->addTxnsToPoolByPeer($transactionsByPeer);
            }
        }
    }

    /**
     * Show subprocess miners log
     */
    public function ShowInfoSubprocessMiners() {

        //Check if miners are enabled
        if (@!file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED))
            return;

        $hashRateMiner = 0;
        $multiplyNonce = 0;
        for ($i=0;$i<MINER_MAX_SUBPROCESS;$i++) {
            $file = Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i."_hashrate";
            if (@file_exists($file)) {
                $tmpHashRateMiner = @intval(@file_get_contents($file));
                if ($tmpHashRateMiner > 0) {
                    $multiplyNonce++;
                    $hashRateMiner += $tmpHashRateMiner;
                }
                @unlink($file);
            }
        }

        if ($hashRateMiner > 1000000000) {
            $hashRateMiner = $hashRateMiner / 1000000000;
            $hashRateMiner = number_format($hashRateMiner,2)." GH/s";
        }
        else if ($hashRateMiner > 1000000) {
            $hashRateMiner = $hashRateMiner / 1000000;
            $hashRateMiner = number_format($hashRateMiner,2)." MH/s";
        }
        else if ($hashRateMiner > 1000) {
            $hashRateMiner = $hashRateMiner / 1000;
            $hashRateMiner = number_format($hashRateMiner,2)." KH/s";
        } else if ($hashRateMiner > 0) {
            $hashRateMiner = number_format($hashRateMiner,2)." H/s";
        } else {
            $hashRateMiner = null;
        }
        if ($hashRateMiner != null) {
            $this->chaindata->SetConfig('hashrate',$hashRateMiner);

			if (SHOW_INFO_SUBPROCESS)
            	Display::print("Miners Threads Status                    %G%count%W%=".$multiplyNonce."            %G%hashRate%W%=" . $hashRateMiner);
        }
    }

    /**
     * Show subprocess propagation log
     */
    public function ShowLogSubprocess() {
        $logFile = Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."log";
        if (@file_exists($logFile)) {
            $currentLog = @file($logFile);
            if (!empty($currentLog)) {
                @unlink($logFile);
                foreach ($currentLog as $line) {
                    Display::print(trim($line));
                }
            }
        }
    }

	/**
	 * Mine process
	 */
	public function mineProcess() {
		//Enable Miners if not enabled
		if (@!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {

			//We check the difficulty before start miners
			$this->difficulty = Blockchain::checkDifficulty($this->chaindata, null, $this->isTestNet)[0];

			//Start miners
			Miner::MineNewBlock($this);

			//Wait 0.5s
			usleep(500000);
		}

		//Check if threads are enabled
		else {

			for($i=0;$i<MINER_MAX_SUBPROCESS;$i++){

				if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK))
					break;

				if (@!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i)) {
					Display::print("The miner thread #".$i." do not seem to respond. Restarting Thread");

					//Get info to pass miner
					$lastBlock = $this->chaindata->GetLastBlock();
					$directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;

					Subprocess::RestartMinerThread($lastBlock,$directoryProcessFile,$this->isTestNet,$this->difficulty,$i);

					//Wait 0.5s
					usleep(500000);
				} else {
					$timeMiner = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i);

					//Check if subprocess is dead
					$threadTime = date_diff(
						date_create(date('Y-m-d H:i:s', intval($timeMiner))),
						date_create(date('Y-m-d H:i:s', time()))
					);
					$seconds = $threadTime->format('%s');
					if ($seconds >= MINER_TIMEOUT_CLOSE) {

						if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 4) {
							Display::_debug("MinerTimer  : " . intval($timeMiner));
							Display::_debug("CurrentTimer: " . time());
						}

						Display::print("The miner thread #".$i." do not seem to respond (Timeout ".$seconds."s). Restarting Thread");

						//Get info to pass miner
						$lastBlock = $this->chaindata->GetLastBlock();
						$directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;

						Subprocess::RestartMinerThread($lastBlock,$directoryProcessFile,$this->isTestNet,$this->difficulty,$i);

						//Wait 0.5s
						usleep(500000);

					}
				}
			}
		}

		//Check If i found new block
		if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {

			//Determine isBusy
			$this->isBusy = true;

			/** @var Block $blockMined */
			$blockMined = Tools::objectToObject(@unserialize(Tools::hex2str(file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK))),'Block');

			//Get next block height
			$nextHeight = $this->chaindata->GetNextBlockNum();

			//Stop minning subprocess
			Tools::clearTmpFolder();
			Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

			if (is_object($blockMined) && isset($blockMined->hash)) {
				//Check if block mined is valid
				if ($blockMined->isValid($nextHeight,$this->isTestNet)) {
					if ($blockMined->isValidReward($nextHeight,$this->isTestNet)) {
						//Display new block mined
						Display::ShowMessageNewBlock('mined',$nextHeight,$blockMined);

						//Add this block on local blockchain
						if ($this->chaindata->addBlock($nextHeight,$blockMined)) {
							//Make SmartContracts on local blockchain
							SmartContract::Make($this->chaindata,$blockMined);

							//Call Functions of SmartContracts on local blockchain
							SmartContract::CallFunction($this->chaindata,$blockMined);
						}

						//Propagate block on network
						Tools::sendBlockMinedToNetworkWithSubprocess($this->chaindata,$blockMined);

						Tools::writeLog('MINER (MINED NEW BLOCK)');
					} else {
						Display::_error("Block reward not valid");
					}
				} else {
					Display::_error("Block mined not valid");
				}
			}
			else{
				Display::_error("Block mined malformed");
			}

			//Wait 2-2.5s
			//usleep(rand(2000000,2500000));
		}
		//Determine isBusy
		$this->isBusy = false;
	}

    /**
     * Start Sanity of Blockchain
     *
     * @param   int    $numBlocksToRemove
     */
	public function SanityFromBlockHeight($numBlocksToRemove=1) {
		Display::print("%LR%SANITY Started %W%- Removing blocks (%G%" . $numBlocksToRemove . "%W%)");
		Blockchain::SanityFromBlockHeight($this->chaindata,$numBlocksToRemove);
		Display::print("%LR%SANITY Finished %W%- Restart your client");
	}
}
?>
