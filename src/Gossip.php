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

    private $loop_x5 = 0;
	private $loop_x10 = 0;
	private $loop_x15 = 0;

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
    public function __construct($db, $name, $ip, $port, $enable_mine, $make_genesis_block=false, $bootstrap_node = false, $isTestNet=false)
    {
        //Clear screen
        Display::ClearScreen();

        //Init Display message
        Display::_printer("Welcome to the %G%MXC node - Version: " . VERSION);
        Display::_printer("Maximum peer count                       %G%value%W%=".PEERS_MAX);
        Display::_printer("Listening on %G%".$ip."%W%:%G%".$port);
        Display::_printer("PeerID %G%".Tools::GetIdFromIpAndPort($ip,$port));

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
        $this->chaindata = new DB();
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
        Display::_printer("Coinbase detected: %LG%".$this->coinbase);

        //We cleaned the table of peers
        if (!$this->bootstrap_node)
            $this->chaindata->truncate("peers");

        //By default we mark that we are not connected to the bootstrap and that we do not have ports open for P2P
        $this->connected_to_bootstrap = false;
        $this->openned_ports = false;

        //WE GENERATE THE GENESIS BLOCK
        if ($make_genesis_block) {
            if(!$isTestNet)
                GenesisBlock::make($this->chaindata,$this->coinbase,$this->key->privKey,$this->isTestNet,bcadd("50","0",18));
            else
                GenesisBlock::make($this->chaindata,$this->coinbase,$this->key->privKey,$this->isTestNet,bcadd("99999999999999999999999999999999","0",18));
        }

        //We are a BOOTSTRAP node
        else if ($bootstrap_node) {
            if ($this->isTestNet)
                Display::_printer("%Y%BOOTSTRAP NODE %W%(%G%TESTNET%W%) loaded successfully");
            else
                Display::_printer("%Y%BOOTSTRAP NODE %W%loaded successfully");

            $lastBlock = $this->chaindata->GetLastBlock(false);

            Display::_printer("Height: %G%".$lastBlock['height']);

            $this->difficulty = Blockchain::checkDifficulty($this->chaindata,null, $this->isTestNet)[0];

            Display::_printer("LastBlock: %G%".$lastBlock['block_hash']);
            Display::_printer("Difficulty: %G%".$this->difficulty);
            Display::_printer("Current peers: %G%".count($this->chaindata->GetAllPeers()));

                //Check peers status
            $this->CheckConnectionWithPeers();
        }

        //If we already have information, we establish the loaded state
        else {
            //We connect to the Bootstrap node
            if ($this->_addBootstrapNode()) {
                $this->connected_to_bootstrap = true;

                //If we do not have open ports, we can not continue
                if (!$this->openned_ports) {
                    Display::_error("Impossible to establish a P2P connection");
                    Display::_error("Check that it is accessible from the internet: %Y%http://".$this->ip.":".$this->port);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

                //Set p2p ON
                $db->SetConfig('p2p','on');

                //We ask the BootstrapNode to give us the information of the connected peers
                $peersNode = BootstrapNode::GetPeers($this->chaindata,$this->isTestNet);
                if (is_array($peersNode) && !empty($peersNode)) {

                    $maxRand = PEERS_MAX;
                    if (count($peersNode) < PEERS_MAX)
                        $maxRand = count($peersNode);

                    $randomPeers = array_rand($peersNode,$maxRand);
                    if (is_array($randomPeers)) {
                        foreach ($randomPeers as $randomPeer) {
                            if (trim($this->ip).":".trim($this->port) != trim($peersNode[$randomPeer]->ip).":".trim($peersNode[$randomPeer]->port)) {
                                if (count($this->peers) < PEERS_MAX) {
                                    $this->_addPeer(trim($peersNode[$randomPeer]->ip),trim($peersNode[$randomPeer]->port));
                                }
                            }
                        }
                    } else {
                        if (trim($this->ip).":".trim($this->port) != trim($peersNode[$randomPeers]->ip).":".trim($peersNode[$randomPeers]->port)) {
                            if (count($this->peers) < PEERS_MAX) {
                                $this->_addPeer(trim($peersNode[$randomPeers]->ip),trim($peersNode[$randomPeers]->port));
                            }
                        }
                    }
                }

                if (count($this->peers) < PEERS_REQUIRED) {
                    Display::_error("there are not enough peers       count=".count($this->peers)."   required=".PEERS_REQUIRED);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

                //We get the last block from the BootstrapNode
                $lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
                $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();

                //We check if we need to synchronize or not
                if ($lastBlock_LocalNode < $lastBlock_BootstrapNode) {
                    Display::_printer("%LR%DeSync detected %W%- Downloading blocks (%G%".$lastBlock_LocalNode."%W%/%Y%".$lastBlock_BootstrapNode.")");

                    //We declare that we are synchronizing
                    $this->syncing = true;

                    $this->chaindata->SetConfig('syncing','on');

                    //If we do not have the GENESIS block, we download it from the BootstrapNode
                    if ($lastBlock_LocalNode == 0) {
                        //Make Genesis from Peer
                        $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($this->chaindata,$this->isTestNet);
                        $genesisMakeBlockStatus = GenesisBlock::makeFromPeer($genesis_block_bootstrap,$this->chaindata);

                        if ($genesisMakeBlockStatus)
                            Display::_printer("%Y%Imported%W% GENESIS block header               %G%count%W%=1");
                        else {
                            Display::_error("Can't make GENESIS block");
                            if (IS_WIN)
                                readline("Press any Enter to close close window");
                            exit();
                        }
                    }
                }
                else {

                    $lastBlock = $this->chaindata->GetLastBlock(false);

                    Display::_printer("Blockchain up to date");
                    Display::_printer("Height: %G%".$lastBlock['height']);

                    $db->SetConfig('syncing','off');

                    $this->difficulty = Blockchain::checkDifficulty($this->chaindata, null, $this->isTestNet)[0];

                    Display::_printer("LastBlock: %G%".$lastBlock['block_hash']);
                    Display::_printer("Difficulty: %G%".$this->difficulty);
                }

                //Check if have same GENESIS block from BootstrapNode
                $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($this->chaindata,$this->isTestNet);
                $genesis_block_local = $this->chaindata->GetGenesisBlock();
                if ($genesis_block_local['block_hash'] != $genesis_block_bootstrap->block_hash) {
                    Display::_error("%Y%GENESIS BLOCK NO MATCH%W%    genesis block does not match the block genesis of bootstrapNode");
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }


            } else {
                if (IS_WIN)
                    readline("Press any Enter to close close window");
                exit();
            }
        }
    }

    /**
     * We add the BootstrapNode
     *
     * @return  bool
     */
    public function _addBootstrapNode() {

        if ($this->isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $infoToSend = array(
            'action' => 'HELLOBOOTSTRAP',
            'client_ip' => $this->ip,
            'client_port' => $this->port
        );
        $url = 'https://' . $ip . '/gossip.php';
        $response = Tools::postContent($url, $infoToSend);

        if (isset($response->status)) {
            if ($response->status == true) {
                $this->chaindata->addPeer($ip, $port);

                $this->peers[] = array($ip.':'.$port => $ip,$port);

                if ($this->isTestNet)
                    Display::_printer("Connected to BootstrapNode (TESTNET) -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                else
                    Display::_printer("Connected to BootstrapNode -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));


                $this->openned_ports = ($response->result == "p2p_off") ? false:true;
            }
            return true;
        }
        else {
            if ($this->isTestNet)
                Display::_error("Can't connect to BootstrapNode (TESTNET) %G%". Tools::GetIdFromIpAndPort($ip,$port));
            else
                Display::_error("Can't connect to BootstrapNode %G%". Tools::GetIdFromIpAndPort($ip,$port));
            return false;
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
            $response = Tools::postContent('http://' . $ip . ':' . $port . '/gossip.php', $infoToSend, 1);
            if ($response != null && isset($response->status)) {
                if ($response->status == true) {
                    $this->chaindata->addPeer($ip, $port);
                    $this->peers[] = array($ip.':'.$port => $ip,$port);
                    if ($displayMessage)
                        Display::_printer("Connected to peer -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                }
                return true;
            }
            else {
                if ($displayMessage)
                    Display::_error("Can't connect to peer %G%". Tools::GetIdFromIpAndPort($ip,$port));
                return false;
            }
        }
    }

    /**
     * Check the connection with the peers, if they do not respond remove them
     */
    public function CheckConnectionWithPeers() {

        //Run subprocess peerAlive per peer
        $peers = $this->chaindata->GetAllPeers();

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
        $title = "PhpMX client";
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
     * This loop only run this loop only runs 1 time of 5 main loop
     */
    public function loop_x5() {
        $this->loop_x5++;
        if ($this->loop_x5 == 5) {
            $this->loop_x5 = 0;

            //If have miners show log
            if ($this->enable_mine)
                $this->ShowInfoSubprocessMiners();

            if ($this->syncing)
                return;

            if (!$this->connected_to_bootstrap || !$this->bootstrap_node)
                return;

            //We get the pending transactions from BootstrapNode
            if (!$this->bootstrap_node) {
                $transactionsByPeer = BootstrapNode::GetPendingTransactions($this->chaindata,$this->isTestNet);
                foreach ($transactionsByPeer as $transactionByPeer) {

                    // Date of the transaction can not be longer than the local date
                    if ($transactionByPeer->timestamp > Tools::GetGlobalTime())
                        continue;

                    //We check not sending money to itself
                    if ($transactionByPeer->wallet_from == $transactionByPeer->wallet_to)
                        continue;

                    $this->chaindata->addPendingTransactionByBootstrap($transactionByPeer);
                }
            }
        }
    }

    /**
     * This loop only run this loop only runs 1 time of 5 main loop
     */
    public function loop_x10() {
        $this->loop_x10++;
        if ($this->loop_x10 == 10) {
            $this->loop_x10 = 0;

            //Check dead peers
            $this->CheckConnectionWithPeers();
        }
	}

    /**
     * We get the pending transactions from BootstrapNode
     */
    public function GetPendingTransactions() {
        if (!$this->bootstrap_node) {

            //Get transactions from peer
            $transactionsByPeer = BootstrapNode::GetPendingTransactions($this->chaindata,$this->isTestNet);

            //Check if have transactions by peer
            if ($transactionsByPeer != null && is_array($transactionsByPeer)) {
                foreach ($transactionsByPeer as $transactionByPeer) {

                    // Date of the transaction can not be longer than the local date
                    if ($transactionByPeer->timestamp > Tools::GetGlobalTime())
                        continue;

                    //We check not sending money to itself
                    if ($transactionByPeer->wallet_from == $transactionByPeer->wallet_to)
                        continue;

                    //We check that the date of the transaction is less than or equal to the current date
                    $this->chaindata->addPendingTransactionByBootstrap($transactionByPeer);
                }
            }
        }
    }

    /**
     * Check if need show new block
     *
     */
    public function CheckIfNeedShowNewBlocks() {

        //Get next block by last hash
        $blockPending = $this->chaindata->GetBlockPendingToDisplay();
        if (is_array($blockPending) && !empty($blockPending)) {

            $numBlock = $this->chaindata->GetNextBlockNum();
            $lastBlock = $this->chaindata->GetLastBlock();

            //$numBlock = $lastBlock['height'];

            $mini_hash = substr($blockPending['block_hash'],-12);
            $mini_hash_previous = substr($blockPending['block_previous'],-12);

            //We obtain the difference between the creation of the block and the completion of the mining
            $minedTime = date_diff(
                date_create(date('Y-m-d H:i:s', $blockPending['timestamp_start_miner'])),
                date_create(date('Y-m-d H:i:s', $blockPending['timestamp_end_miner']))
            );
            $blockMinedInSeconds = $minedTime->format('%im%ss');

            //Block validated new block
            if ($blockPending['status'] == "0x00000000") {

                //Check if i displayed this block
                if (!$this->chaindata->BlockHasBeenAnnounced($blockPending['block_hash'])) {

                    //Mark this block has announced
                    $this->chaindata->AddBlockAnnounced($blockPending['block_hash']);

                    //If have miner enabled, stop all miners
                    if ($this->enable_mine) {
                        if (@file_exists(Tools::GetBaseDir() . 'tmp' . DIRECTORY_SEPARATOR . Subprocess::$FILE_MINERS_STARTED)) {
                            Tools::clearTmpFolder();
                            Tools::writeFile(Tools::GetBaseDir() . 'tmp' . DIRECTORY_SEPARATOR . Subprocess::$FILE_STOP_MINING);
                            Display::NewBlockCancelled();
                        }
                    }
                    Display::_printer("%Y%Imported%W% new block headers     	%G%nonce%W%=" . $blockPending['nonce'] . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . $numBlock." %G%size%W%=".Tools::GetBlockSize($lastBlock));
                }
            }
            //Block validated, same height
            else if ($blockPending['status'] == "1x00000000") {
                //Mark this block has announced
                $this->chaindata->AddBlockAnnounced($blockPending['block_hash']);

                //If have miner enabled, stop all miners
                if ($this->enable_mine) {
                    if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
                        Tools::clearTmpFolder();
                        Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
                        Display::NewBlockCancelled();
                    }
                }
                Display::_printer("%Y%Sanity%W% block headers	     	%G%nonce%W%=" . $blockPending['nonce'] . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . $numBlock." %G%size%W%=".Tools::GetBlockSize($lastBlock));
            }

            //Block validated, reward
            else if ($blockPending['status'] == "2x00000000") {

				/*
                //Check if i displayed this block
                if (!$this->chaindata->BlockHasBeenAnnounced($blockPending['block_hash'])) {

                    //Mark this block has announced
                    $this->chaindata->AddBlockAnnounced($blockPending['block_hash']);

                    $typeMessage = "Imported";
                    //Check if i mined this block
                    if ($lastBlock['transactions'][0]['wallet_to'] == $this->coinbase)
                        $typeMessage = "Rewarded";

                    Display::_printer("%Y%".$typeMessage."%W% new block headers     %G%nonce%W%=" . $blockPending['nonce'] . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . $numBlock." %G%size%W%=".Tools::GetBlockSize($lastBlock));
				}
				*/
            }

            //Block error
            else {

                if ($blockPending['status'] == "0x00000001" || $blockPending['status'] == "1x00000001") {
                    Display::_printer("%LR%Ignored%W% new block headers     Reward Block not valid  %G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
                }
                else if ($blockPending['status'] == "0x00000002") {
                    Display::_printer("%LR%Ignored%W% new block headers     Block not valid  %G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
                }
                else if ($blockPending['status'] == "0x00000002") {
                    Display::_printer("%LR%Ignored%W% new block headers     Previous block does not match  %G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
                }
            }

            //Remove block from tmp table
            $this->chaindata->RemoveBlockToDisplay($blockPending['block_hash']);
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
            //Display::_printer("Miners Threads Status                    %G%count%W%=".$multiplyNonce."            %G%hashRate%W%=" . $hashRateMiner);
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
                    Display::_printer(trim($line));
                }
            }
        }
    }

    /**
     * General loop of the node
     */
    public function loop() {

        if ($this->make_genesis)
            return;

        if (!$this->connected_to_bootstrap && !$this->bootstrap_node)
            return;

        $this->GetPendingTransactions();

        //If we do not build the genesis, we'll go around
        while (true) {
            //We establish the title of the process
            $this->SetTitleProcess();

            //Update MainThread time for subprocess
            Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

            //Get pending transactions
            $this->GetPendingTransactions();

            //Exec delayed loops
            $this->loop_x5();

            if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 3)
                $this->ShowLogSubprocess();

            //If we are not synchronizing
            if (!$this->syncing) {

                //We send all transactions_pending_to_send to the network
                $this->sendPendingTransactionsToNetwork();

                //We have miner, start miner process
                if ($this->enable_mine) {
					$this->mineProcess();
                }

                //We check if there are new blocks to be display
                $this->CheckIfNeedShowNewBlocks();
            }

            //If we are synchronizing and we are connected with the bootstrap
            else if ($this->syncing) {

                if ($this->isTestNet)
                    $ipAndPort = NODE_BOOTSTRAP_TESTNET.':'.NODE_BOOSTRAP_PORT_TESTNET;
                else
                    $ipAndPort = NODE_BOOTSTRAP.':'.NODE_BOOSTRAP_PORT;

                if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer")) {
                    $ipAndPort = file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer");
                }

                //Check if have ip and port
                if (strlen($ipAndPort) > 0) {

                    //We get the last block from peer
                    $lastBlock_PeerNode = Peer::GetLastBlockNum($ipAndPort);
                    $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();

                    if ($lastBlock_LocalNode < $lastBlock_PeerNode) {
                        $nextBlocksToSyncFromPeer = Peer::SyncNextBlocksFrom($ipAndPort,$lastBlock_LocalNode);
                        $resultSync = Peer::SyncBlocks($this,$nextBlocksToSyncFromPeer,$lastBlock_LocalNode,$lastBlock_PeerNode,$ipAndPort);

                        //If dont have result of sync, stop sync with this peer
                        if ($resultSync == null) {
                            //Delete sync file
                            @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");
                        }
                    } else {
                        $this->syncing = false;

                        $this->chaindata->SetConfig('syncing','off');

                        //Delete sync file
                        @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer");

                        //We check the difficulty
                        $this->difficulty = Blockchain::checkDifficulty($this->chaindata, null, $this->isTestNet)[0];

                        //We clean the table of blocks mined by the peers
                        $this->chaindata->truncate("transactions_pending_to_send");
						$this->chaindata->truncate("transactions_pending");
						$this->chaindata->truncate("blocks_pending_to_display");
						$this->chaindata->truncate("blocks_announced");
                    }
                }
                continue;
            }

            //If isnt bootstrap and connected to bootstrap
            if (!$this->bootstrap_node && $this->connected_to_bootstrap) {
                //We get the last block from the BootstrapNode
                $lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
                $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();

                //We check if we need to synchronize or not
                if ($lastBlock_LocalNode < $lastBlock_BootstrapNode) {
                    //Display::_printer("%LR%DeSync detected %W%- Downloading blocks (%G%" . $lastBlock_LocalNode . "%W%/%Y%" . $lastBlock_BootstrapNode . ")");

                    if ($this->enable_mine && @file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
                        //Stop minning subprocess
                        Tools::clearTmpFolder();
                        Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
                        Display::_printer("%Y%Miner work cancelled%W%     Imported new headers");
                    }

                    //We declare that we are synchronizing
                    $this->syncing = true;

                    $this->chaindata->SetConfig('syncing','on');
                }
            }

            //If is bootstrap
            if ($this->bootstrap_node && !$this->syncing) {
                if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer")) {
                    //We declare that we are synchronizing
                    $this->syncing = true;

                    $this->chaindata->SetConfig('syncing','on');

                    if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 1)
                        Display::_debug("Getting blocks from peer: " . @file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."sync_with_peer"));
                }
            }

            $this->loop_x10();

            usleep(1000000);
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
					Display::_printer("The miner thread #".$i." do not seem to respond. Restarting Thread");

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

						Display::_printer("The miner thread #".$i." do not seem to respond (Timeout ".$seconds."s). Restarting Thread");

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
						//Displau new block mined
						Display::NewBlockMined($blockMined);

						//Propagate block on network
						Tools::sendBlockMinedToNetworkWithSubprocess($this->chaindata,$blockMined);

						Tools::writeLog('MINER (MINED NEW BLOCK)');

						//Add this block on local blockchain
						if ($this->chaindata->addBlock($nextHeight,$blockMined)) {
							//Make SmartContracts on local blockchain
							SmartContract::Make($this->chaindata,$blockMined);

							//Call Functions of SmartContracts on local blockchain
							SmartContract::CallFunction($this->chaindata,$blockMined);
						}
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
			usleep(rand(2000000,2500000));
		}
	}

    /**
     * Start Sanity of Blockchain
     *
     * @param   int    $numBlocksToRemove
     */
	public function SanityFromBlockHeight($numBlocksToRemove=1) {
		Display::_printer("%LR%SANITY Started %W%- Remoing blocks (%G%" . $numBlocksToRemove . "%W%)");
		Blockchain::SanityFromBlockHeight($this->chaindata,$numBlocksToRemove);
		Display::_printer("%LR%SANITY Finished %W%- Restart your client");
	}

    /**
     * We send all pending transactions to our peers
     */
    public function sendPendingTransactionsToNetwork() {

        //We obtain all pending transactions to send
        $pending_tx = $this->chaindata->GetAllPendingTransactionsToSend();

		if (!empty($pending_tx)) {
			//We add the pending transaction to the chaindata
			foreach ($pending_tx as $tx)
				$this->chaindata->addPendingTransaction($tx);

			//We get all the peers and send the pending transactions to all
			$peers = $this->chaindata->GetAllPeers();
			foreach ($peers as $peer) {

				$myPeerID = Tools::GetIdFromIpAndPort($this->ip,$this->port);
				$peerID = Tools::GetIdFromIpAndPort($peer['ip'],$peer['port']);

				if ($myPeerID != $peerID) {
					$infoToSend = array(
						'action' => 'ADDPENDINGTRANSACTIONS',
						'txs' => $pending_tx
					);
					if ($peer["ip"] == NODE_BOOTSTRAP) {
						Tools::postContent('https://'.NODE_BOOTSTRAP.'/gossip.php', $infoToSend,5);
					}
					else if ($peer["ip"] == NODE_BOOTSTRAP_TESTNET) {
						Tools::postContent('https://'.NODE_BOOTSTRAP_TESTNET.'/gossip.php', $infoToSend,5);
					}
					else {
						Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend,5);
					}
				}
			}

			//We delete transactions sent from transactions_pending_to_send
			foreach ($pending_tx as $tx)
				$this->chaindata->removePendingTransactionToSend($tx['txn_hash']);
		}
    }
}
?>
