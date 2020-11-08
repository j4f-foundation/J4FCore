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

use React\Socket\ConnectionInterface;

final class Gossip {

    public $name;
    public $key;
    public $port;
    public $ip;
    public $enable_mine;
    public $pending_transactions;
    public $coinbase;
    public $syncing;
    public $config;
    public $peers = [];
    public $difficulty;
    public $isTestNet;

    /** @var DB $chaindata */
    public $chaindata;
    private $make_genesis;
    private $bootstrap_node;

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
    public function __construct(DB $db,string $name,string $ip,string $port,bool $enable_mine,bool $make_genesis_block=false,bool $bootstrap_node = false,bool $isTestNet=false, int $sanityBlockchain=-1) {
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
		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sanity");

		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."busy");
		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."syncing");
		@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display");

		//Default miners stopped
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

		//Stop socket thread (if is actived)
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD);

		//Update MainThread time for subprocess
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

		//Instance the pointer to the chaindata and get config
		$this->chaindata = $db;
		$this->config = $this->chaindata->GetAllConfig();

		//We started with that we do not have pending transactions
		$this->pending_transactions = array();

		//We create the Wallet for the node
		$this->key = new Key(Wallet::LoadOrCreate('coinbase',"null"));

		if (strlen($this->key->pubKey) != 451) {
			Display::_error("Can't get the public/private key");
			Display::_error("Make sure you have openssl installed and activated in php");
			exit();
		}
		$this->coinbase = Wallet::GetWalletAddressFromPubKey($this->key->pubKey);
		Display::print("Coinbase detected: %LG%".$this->coinbase);

		//Save pointer of Gossip
		$gossip = $this;

		if ($sanityBlockchain > 0) {
			$gossip->SanityFromBlockHeight($sanityBlockchain);
			exit();
		}

		//Check integrity of last 20 blocks
		if ($this->chaindata->GetCurrentBlockNum() > 0) {
			Display::print("%Y%CHECKING INTEGRITY%W% of last 20 blocks");
			Blockchain::checkIntegrity($gossip->chaindata,null,20);
		}

		//Start Subprocess socketServer
		@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD);
		$this->_startSocketServer();

		$loop = React\EventLoop\Factory::create();

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
				//Check if have peers
				$peers = $gossip->chaindata->GetAllPeers();
				if (count($peers) == 0) {
					//If no have peers, connect to boostrap and get peers
					$gossip->_connectToBootstrapNode($gossip);
					//We ask the BootstrapNode to give us the information of the connected peers
	                $peersNode = BootstrapNode::GetPeers($gossip->chaindata,$gossip->isTestNet);
					$this->ConnectToBootstrapPeers($peersNode);
				}
				else {
					//We have peers, connect to all peers
					$this->ConnectToMyPeers($peers);
				}

				//If can't connect to any peers, try to connect to bootstrap
				if (count($gossip->peers) == 0) {
					$gossip->_connectToBootstrapNode($gossip);
				}

				//Get more peers from my current peers list (connected)
				$this->GetMorePeersFromMyPeers();

				//Check if have required peers to run node
                if (count($gossip->peers) < PEERS_REQUIRED) {
                    Display::_error("there are not enough peers       count=".count($gossip->peers)."   required=".PEERS_REQUIRED);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

				//Select random peer to check status
				$ipAndPort = Peer::GetHighestBlockFromPeers($gossip);
				$lastBlock_PeerNode = Peer::GetLastBlockNum($ipAndPort);
                $lastBlock_LocalNode = $this->chaindata->GetCurrentBlockNum();

                //We check if we need to synchronize or not
                if ($lastBlock_LocalNode < $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {
                    Display::print("%LR%DeSync detected %W%- Downloading blocks (%G%".$lastBlock_LocalNode."%W%/%Y%".$lastBlock_PeerNode.")");

					//We declare that we are synchronizing
                    $gossip->syncing = true;
					Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");

                    $gossip->chaindata->SetConfig('syncing','on');

					//Select a peer to sync
					$ipPort = explode(':',$ipAndPort);
					Display::print("Selected peer to sync -> %G%".Tools::GetIdFromIpAndPort($ipPort[0],$ipPort[1]));
					Tools::writeLog('Selected peer to sync			%G%'.Tools::GetIdFromIpAndPort($ipPort[0],$ipPort[1]));
				}
				else if ($lastBlock_LocalNode >= $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {
					$gossip->syncing = false;
					@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing");
					$gossip->chaindata->SetConfig('syncing','off');

					//Delete sync file
					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
				}
				else {
					$gossip->syncing = true;
					Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
					$gossip->chaindata->SetConfig('syncing','on');

					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
					$ipAndPortToSync = Peer::GetHighestBlockFromPeers($gossip);
					Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$ipAndPortToSync);
				}

				//If we do not have the GENESIS block, we download it from Peer (HighestChain)
				if ($lastBlock_LocalNode == 0) {
					//Make Genesis from Peer
					$genesis_block_peer = Peer::GetGenesisBlock($ipAndPort);
					$genesisMakeBlockStatus = GenesisBlock::makeFromPeer($gossip->chaindata,$genesis_block_peer);

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

                //Check if have same GENESIS block from peer
                $genesis_block_peer = Peer::GetGenesisBlock($ipAndPort);
                $genesis_block_local = $gossip->chaindata->GetGenesisBlock();
                if ($genesis_block_local['block_hash'] != $genesis_block_peer['block_hash']) {
                    Display::_error("%Y%GENESIS BLOCK NO MATCH%W%    genesis block does not match the block genesis of peer");
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }
	        }

			if ($gossip->make_genesis)
				return;

			//Get pending transactions from bootstrap
			$gossip->GetPendingTransactions();

		});

		//Check peer status every 120s
		$loop->addPeriodicTimer(120, function() use (&$gossip) {
			$gossip->CheckConnectionWithPeers($gossip);

			//If isnt bootstrap
			if (!$gossip->bootstrap_node) {
				$ipAndPort = Peer::GetHighestBlockFromPeers($gossip);
				$lastBlock_PeerNode = Peer::GetLastBlockNum($ipAndPort);
				$lastBlock_LocalNode = $gossip->chaindata->GetCurrentBlockNum();

				//We check if we need to synchronize or not
				if ($lastBlock_LocalNode < $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {
					//If have miner enabled, stop it and start sync
					if ($gossip->enable_mine && @file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
						//Stop minning subprocess
						Tools::clearTmpFolder();
						Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
					}

					Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer", $ipAndPort);

					//We declare that we are synchronizing
					$gossip->syncing = true;
					Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");

					$gossip->chaindata->SetConfig('syncing','on');
				}
				else if ($lastBlock_LocalNode >= $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {

					$gossip->syncing = false;
					@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing");
					$gossip->chaindata->SetConfig('syncing','off');

					//Delete sync file
					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
				}
				else {
					$gossip->syncing = true;
					Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
					$gossip->chaindata->SetConfig('syncing','on');

					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
					$ipAndPortToSync = Peer::GetHighestBlockFromPeers($gossip);
					Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$ipAndPortToSync);
				}
			}
		});

		//Loop every 5s
		$loop->addPeriodicTimer(5, function() use (&$gossip) {
			//If have miners show log
			if ($gossip->enable_mine)
				$this->ShowInfoSubprocessMiners();

			if ($gossip->syncing)
				return;

			if (!$gossip->bootstrap_node)
				return;

			//Get Pending transactions from network
			$gossip->GetPendingTransactions();
		});

		//General loop of node
		$loop->addPeriodicTimer(0.1, function() use (&$gossip) {

			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing")) {
				$gossip->syncing = true;
			}

			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy")) {
				$gossip->busy = true;
			}

			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."new_peer")) {
				Display::displayFromSubprocess("new_peer");
				@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."new_peer");
			}

			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display")) {
				Display::displayFromSubprocess("display");
				@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display");
			}

			//Check if MainThread is alive
			if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD_CLOCK)) {
				$socketThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD_CLOCK);
				if (strlen($socketThreadTime) > 0) {
					$minedTime = date_diff(
						date_create(date('Y-m-d H:i:s', intval($socketThreadTime))),
						date_create(date('Y-m-d H:i:s', time()))
					);
					$diffTime = $minedTime->format('%s');
					if ($diffTime > 10) {
						$this->_startSocketServer();
					}
				}
			}

			//We establish the title of the process
			$gossip->SetTitleProcess();

			//Update MainThread time for subprocess still alive
			Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

			if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 3)
				$gossip->ShowLogSubprocess();

			//Check if need to sync with any peer
			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer")) {
				$gossip->syncing = true;
				Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
			}

			//Check if need to sanity (From subprocess propagation)
			if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sanity")) {
				$gossip->busy = true;

				$lastBlock_LocalNode = $gossip->chaindata->GetCurrentBlockNum();

				//Micro-Sanity and resync
				Display::_warning("Started Micro-Sanity       %G%height%W%=".$lastBlock_LocalNode."	%G%newHeight%W%=".($lastBlock_LocalNode-1));
				$gossip->chaindata->RemoveLastBlocksFrom(($lastBlock_LocalNode-1));
				Display::_warning("Finished Micro-Sanity, re-sync with peer");
				@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sanity");
				$gossip->busy = false;
			}

			//If we are not synchronizing
			if (!$gossip->syncing) {

				//We have miner, start miner process
				if ($gossip->enable_mine) {
					$gossip->mineProcess();
				}
			}

			//If we are synchronizing and we are connected with the bootstrap
			else if ($gossip->syncing) {

				//Select a peer to sync
				if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer"))
					$ipAndPort = @file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer");
				else
					$ipAndPort = Peer::GetHighestBlockFromPeers($gossip);

				//We prevent it from synchronizing itself
				if ($ipAndPort == $gossip->ip . ":" . $gossip->port) {
					$ipAndPort = "";
					$gossip->syncing = false;
					@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing");
					//Delete sync file
					@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
				}

				//Check if have ip and port
				if (strlen($ipAndPort) > 0) {

					//We get the last block from peer
					$lastBlock_PeerNode = Peer::GetLastBlockNum($ipAndPort);
					$lastBlock_LocalNode = $gossip->chaindata->GetCurrentBlockNum();

					if ($lastBlock_LocalNode < $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {

						//Save highest block in config table (used in API)
						$gossip->chaindata->SetConfig('highestBlock',$lastBlock_PeerNode);

						$gossip->syncing = true;
						Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
						$gossip->chaindata->SetConfig('syncing','on');

						//Get next peer blocks
						$nextBlocksToSyncFromPeer = Peer::SyncNextBlocksFrom($ipAndPort,$lastBlock_LocalNode);

						if (is_array($nextBlocksToSyncFromPeer) && !empty($nextBlocksToSyncFromPeer)) {

							//Sync blocks
							$resultSync = Peer::SyncBlocks($gossip,$nextBlocksToSyncFromPeer,$lastBlock_LocalNode,$lastBlock_PeerNode,$ipAndPort);

							//If dont have result of sync, stop sync with this peer
							if ($resultSync == null) {
								//Delete sync file
								@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
							}
						}
					} else if ($lastBlock_LocalNode >= $lastBlock_PeerNode && $lastBlock_PeerNode != -1) {

						$gossip->syncing = false;
						@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing");
						$gossip->chaindata->SetConfig('syncing','off');

						//Delete sync file
						@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");

						//We check the difficulty
						$gossip->difficulty = Blockchain::checkDifficulty($gossip->chaindata, null, $gossip->isTestNet)[0];

						//We clean the table of blocks mined by the peers
						$gossip->chaindata->truncate("txnpool");
					}
					else {

						$gossip->syncing = true;
						Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
						$gossip->chaindata->SetConfig('syncing','on');

						@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
						$ipAndPortToSync = Peer::GetHighestBlockFromPeers($gossip);
						Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$ipAndPortToSync);
					}
				}
			}
		});

		//Start node
		$loop->run();
	}

	public function _startSocketServer() {
		$params = [$this->ip, $this->port];
		Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'socketServer',$params);
		Display::print("%LP%Network%W% Listening on		%G%tcp://0.0.0.0:{$this->port}%W%");
	}

    /**
     * Connect to bootstrap node and add it to peer list
     *
     * @return  bool
     */
    public function _connectToBootstrapNode(Gossip &$gossip) : void {

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

        $response = Socket::sendMessageWithReturn($ip, $port, $infoToSend, 5);
		if ($response != null && isset($response['status'])) {
			$gossip->chaindata->addPeer($ip, $port);

			$gossip->peers[$ip.':'.$port] = true;

			if ($gossip->isTestNet)
				Display::print("%LP%Network%W% Connected to BootstrapNode		%G%peerId%W%=".Tools::GetIdFromIpAndPort($ip,$port));
		}
		else {
			if ($gossip->isTestNet)
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
    public function _addPeer(string $ip,string $port, bool $displayMessage=true) : bool {

        if (!$this->chaindata->haveThisPeer($ip,$port) && ($this->ip != $ip || ($this->ip == $ip && $this->port != $port))) {

            $infoToSend = array(
                'action' => 'HELLO',
                'client_ip' => $this->ip,
                'client_port' => $this->port
            );
            $response = Socket::sendMessageWithReturn($ip, $port, $infoToSend, 5);
            if ($response != null && isset($response['status'])) {
                if ($response['status'] == true) {
                    $this->chaindata->addPeer($ip, $port);
                    $this->peers[$ip.':'.$port] = true;
                    if ($displayMessage)
						Display::print('%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($ip,$port));

					return true;
                }
            }
        }

		return false;
    }

	/**
     * We connect to this peer
     *
     * @param   string    $ip
     * @param   string    $port
     * @param   bool      $displayMessage
     * @return  bool
     */
	public function _connectToPeer(string $ip,string $port, bool $displayMessage=true) : bool {

        if ($this->chaindata->haveThisPeer($ip,$port) && ($this->ip != $ip || ($this->ip == $ip && $this->port != $port))) {

            $infoToSend = array(
                'action' => 'HELLO',
                'client_ip' => $this->ip,
                'client_port' => $this->port
            );
            $response = Socket::sendMessageWithReturn($ip, $port, $infoToSend, 5);
            if ($response != null && isset($response['status'])) {
                if ($response['status'] == true) {
                    $this->peers[$ip.':'.$port] = true;
                    if ($displayMessage)
						Display::print('%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($ip,$port));

					return true;
                }
            }
        }

		return false;
    }

    /**
     * Check the connection with the peers, if they do not respond remove them
     */
    public function CheckConnectionWithPeers(Gossip &$gossip) : void {

        //Run subprocess peerAlive per peer
        $peers = $gossip->chaindata->GetAllPeersWithoutBootstrap();

        if (count($peers) > 0) {

            Display::_debug("Checking status of peers                 %G%count%W%=".count($peers),1);

            Tools::writeLog('Checking status of peers count='.count($peers));

            //Run subprocess propagation
            Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'peerAlive',[],-1);
        }
    }

    /**
     * Set the title of the process with useful information
     */
    public function SetTitleProcess() : void {
        $title = "J4F Node";
        $title .= " | PeerID: " . substr(PoW::hash($this->ip . $this->port), 0, 18);
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
     * We get the pending transactions from random peer
     */
    public function GetPendingTransactions() : void {
        if (!$this->bootstrap_node) {

			$ipAndPort = Peer::SelectPeerToSync($this);

            //Get transactions from peer
            $transactionsByPeer = Peer::GetPendingTransactions($ipAndPort);

            //Check if have transactions by peer
            if ($transactionsByPeer != null && is_array($transactionsByPeer) && !empty($transactionsByPeer)) {
				$this->chaindata->addTxnsToPoolByPeer($transactionsByPeer);
            }
        }
    }

    /**
     * Show subprocess miners log
     */
    public function ShowInfoSubprocessMiners() : void {

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
            	Display::print("Miners Threads Status             %G%count%W%=".$multiplyNonce."            %G%hashRate%W%=" . $hashRateMiner);
        }
    }

    /**
     * Show subprocess propagation log
     */
    public function ShowLogSubprocess() : void {
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
	 * MainThread
	 * Mine process
	 */
	public function mineProcess() : void {

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
					if (SHOW_INFO_SUBPROCESS)
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

						Display::_debug("MinerTimer  : " . intval($timeMiner),4);
						Display::_debug("CurrentTimer: " . time(),4);

						if (SHOW_INFO_SUBPROCESS)
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

			$this->isBusy = true;
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy","");

			/** @var Block $blockMined */
			$blockMined = Tools::objectToObject(@unserialize(Tools::hex2str(file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK))),'Block');

			//Get next block height
			$nextHeight = $this->chaindata->GetNextBlockNum();

			//Stop minning subprocess
			Tools::clearTmpFolder();
			Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

			if (is_object($blockMined) && isset($blockMined->hash)) {
				//Check if block mined is valid
				if ($blockMined->isValid()) {
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
						Display::_error("Block mined reward not valid");
					}
				} else {
					Display::_error("Block mined not valid");
				}
			}
			else{
				Display::_error("Block mined malformed");
			}
		}

		$this->isBusy = false;
		@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy");
	}

	/**
	 * Connect to bootstrap peers
	 */
	public function ConnectToBootstrapPeers($peersNode) : void {
		if (is_array($peersNode) && !empty($peersNode)) {
			foreach ($peersNode as $peer) {
				if (trim($this->ip).":".trim($this->port) != trim($peer['ip']).":".trim($peer['port'])) {
					if (count($this->peers) <= PEERS_MAX) {
						$this->_addPeer(trim($peer['ip']),trim($peer['port']));
					}
				}
			}
		}
	}

	/**
	 * Connect to all my peers
	 */
	public function ConnectToMyPeers($peers) : void {
		foreach ($peers as $peer) {
			$infoToSend = array(
				'action' => 'STATUSNODE'
			);
			$response = Socket::sendMessageWithReturn($peer['ip'],$peer['port'],$infoToSend,5);
			if ($response != null && isset($response['status'])) {
				$this->_connectToPeer($peer['ip'], $peer['port'], true);
			}
			else {
				//Remove this peer
				$this->chaindata->removePeer($peer['ip'],$peer['port']);
			}
		}
	}

	/**
	 * Get more peers from my current peers
	 */
	public function GetMorePeersFromMyPeers() : void {
		//Data to send
		$infoToSend = array(
            'action' => 'GETPEERS'
        );
		foreach($this->peers as $ipAndPort => $v) {
			$peer = explode(":", $ipAndPort);
			$infoPOST = Socket::sendMessageWithReturn($peer[0],$peer[1],$infoToSend,5);
			if ($infoPOST != null && isset($infoPOST['status']) && $infoPOST['status'] == 1) {
				if (is_array($infoPOST['result']) && !empty($infoPOST['result'])) {
					foreach ($infoPOST['result'] as $newPeerInfo) {
						if (count($this->peers) < PEERS_MAX) {
							$this->_addPeer($newPeerInfo['ip'],$newPeerInfo['port']);
						}
					}
				}
			}
		}
	}

    /**
     * Start Sanity of Blockchain
     *
     * @param   int    $numBlocksToRemove
     */
	public function SanityFromBlockHeight(int $numBlocksToRemove=1) : void {
		Display::print("%LR%SANITY Started %W%- Removing blocks (%G%" . $numBlocksToRemove . "%W%)");
		Blockchain::SanityFromBlockHeight($this->chaindata,$numBlocksToRemove);
		Display::print("%LR%SANITY Finished %W%- Restart your client");
	}
}
?>
