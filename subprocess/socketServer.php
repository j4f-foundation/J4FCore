<?php
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'CONFIG.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/DB/DBTransactions.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/DB/DBContracts.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/DB/DBBlocks.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/DB/DBBase.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/DB/DB.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/ColorsCLI.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Display.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Subprocess.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Tools.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/BootstrapNode.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/ArgvParser.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Wallet.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Block.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Blockchain.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Gossip.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Key.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Pki.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/PoW.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/REGEX.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Transaction.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Miner.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/GenesisBlock.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Peer.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/SmartContract.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/SmartContractStateMachine.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/J4FVM/0.0.2/J4FVMBase.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/J4FVM/J4FVM.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/J4FVM/J4FVMTools.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/J4FVM/J4FVMSubprocess.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/uint256.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Socket.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/CLI.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src/Gas.php');
include(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'funity/js.php');

require __DIR__ . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

if (!isset($argv[1]))
    die("ID not defined");

if (!isset($argv[2]))
    die("IP not defined");

if (!isset($argv[3]))
    die("PORT not defined");

$id = $argv[1];
$serverIP = $argv[2];
$serverPORT = $argv[3];

//Update socket thread clock
Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD_CLOCK,time());

ob_start();

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$config = React\Dns\Config\Config::loadSystemConfigBlocking();
$server = $config->nameservers ? reset($config->nameservers) : '8.8.8.8';

$factory = new React\Dns\Resolver\Factory();
$dns = $factory->create($server, $loop);

//Start Socket
$socket = new React\Socket\Server('0.0.0.0:'.$serverPORT, $loop, array(
	'tcp' => array(
		'backlog' => 200,
		'so_reuseport' => true,
		'ipv6_v6only' => false
	)
));

$address = $socket->getAddress();
Display::print("%LP%Network%W% Listening on		%G%{$socket->getAddress()}%W%");

$chaindata = new DB();

$gossipINFO = array(
	"busy" => false,
	"syncing" => false
);

$loop->addPeriodicTimer(0.1, function() use (&$gossipINFO) {

	//Update socket thread clock
	Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD_CLOCK,time());

	//Check if node is busy
	if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy"))
		$gossipINFO['busy'] = true;
	else
		$gossipINFO['busy'] = false;

	//Check if node is syncing
	if (@file_exists(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing"))
		$gossipINFO['syncing'] = true;
	else
		$gossipINFO['syncing'] = false;

		//Check if MainThread is alive
	if (file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD)){
		@unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_SOCKET_THREAD);
		die('SOCKET THREAD CANCELLED');
	}

	//Check if MainThread is alive
	if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK)) {
		$mainThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK);
		if (strlen($mainThreadTime) > 0) {
			$minedTime = date_diff(
				date_create(date('Y-m-d H:i:s', intval($mainThreadTime))),
				date_create(date('Y-m-d H:i:s', time()))
			);
			$diffTime = $minedTime->format('%s');
			if ($diffTime > 30)
				die('MAINTHREAD NOT FOUND');
		}
	}
});

//Gossip
$socket->on('connection', function(ConnectionInterface $connection) use (&$chaindata, &$gossipINFO) {

	$dataFromPeer = '';

	$connection->on('data', function($data) use (&$connection, &$dataFromPeer, &$chaindata, &$gossipINFO) : void {
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
						$return['result'] = $chaindata->GetTxnFromPool();
					break;
					case 'ADDPENDINGTRANSACTIONS':
						if (isset($msgFromPeer['txs'])) {
							$return['status'] = true;
							$return['result'] = $chaindata->addTxnsToPoolByPeer(@unserialize($msgFromPeer['txs']));
						}
					break;
					case 'GETBLOCKBYHASH':
						if (isset($msgFromPeer['hash'])) {
							//We get a block given a hash
							$return['status'] = true;
							$return['result'] = $chaindata->GetBlockByHash($msgFromPeer['hash']);
						}
						break;
					case 'PING':
						$return['status'] = true;
					break;
					case 'GETPEERS':
						$return['status'] = true;
						$return['result'] = $chaindata->GetAllPeers();
					break;
					case 'MINEDBLOCK':

						//Check if blockchain is syncing
						if ($gossipINFO['syncing']) {
							$return['status'] = true;
							$return['error'] = "3x00000000";
							$return['message'] = "Blockchain syncing";
							//Display::_error("Blockchain syncing");
							break;
						}

						//Check if im busy
						if ($gossipINFO['busy']) {
							$return['status'] = true;
							$return['error'] = "0x10000003";
							$return['message'] = "Busy";
							//Display::_error("Busy");
							break;
						}

						//Determine isBusy
						$gossipINFO['busy'] = true;
						Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy","");

						//Get current network
						$isTestNet = ($chaindata->GetConfig('network') == 'testnet') ? true:false;

						//Get last block
						$lastBlock = $chaindata->GetLastBlock();

						//Check if have previous block hash and new block info
						if (!isset($msgFromPeer['hash_previous']) || !isset($msgFromPeer['block'])) {
							$return['status'] = true;
							$return['error'] = "0x10000002";
							$return['message'] = "Need hashPrevious & blockInfo";
							//Display::_error("Need hashPrevious & blockInfo");
							break;
						}

						//Check if this peer is in blacklist
						if (Peer::CheckIfBlacklisted($chaindata,$msgFromPeer["node_ip"],$msgFromPeer["node_port"])) {
							$return['status'] = true;
							$return['error'] = "7x00000001";
							$return['message'] = "Blacklisted";
							//Display::_error("This peer is blacklisted -> " . $msgFromPeer["node_ip"].":".$msgFromPeer["node_port"]);
							break;
						}

						/** @var Block $blockMinedByPeer */
						$blockMinedByPeer = Tools::objectToObject(unserialize($msgFromPeer['block']),"Block");

						//Check if block received its OK
						if (!is_object($blockMinedByPeer) || ( is_object($blockMinedByPeer) && !isset($blockMinedByPeer->hash) )) {
							$return['status'] = true;
							$return['error'] = "5x00000000";
							$return['message'] = "Block received malformed";
							$return['result'] = 'sanity';
							//Display::_error('Block received malformed');
							break;
						}

						$currentLocalTime = Tools::GetGlobalTime() + 5;
						//We check that the date of the block sent is not superior to mine
						if ($blockMinedByPeer->timestamp_end > $currentLocalTime) {
							$return['status'] = true;
							$return['error'] = "6x00000002";
							$return['message'] = "Block date is from the future";
							$return['result'] = 'sanity';
							//Display::_error('Block date is from the future');
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
							$currentDifficulty = Blockchain::checkDifficulty($chaindata,($blockMinedByPeer->height-1),$isTestNet);
							if ($currentDifficulty[0] != $blockMinedByPeer->difficulty) {
								$return['status'] = true;
								$return['error'] = "4x00000000";
								$return['message'] = "Block difficulty hacked?";
								$return['result'] = 'sanity';
								Display::_error('SameHeight | Block difficulty hacked?');
								break;
							}

							/*
							// We check if the time difference is equal orgreater than 2s
							$diffTimeBlocks = date_diff(
								date_create(date('Y-m-d H:i:s', $lastBlock['timestamp_end_miner'])),
								date_create(date('Y-m-d H:i:s', $blockMinedByPeer->timestamp_end))
							);
							$diffTimeSeconds = ($diffTimeBlocks->format('%i') * 60) + $diffTimeBlocks->format('%s');
							$diffTimeSeconds = ($diffTimeSeconds < 0) ? ($diffTimeSeconds * -1):$diffTimeSeconds;
							if ($diffTimeSeconds > 2) {
								$return['status'] = true;
								$return['error'] = "5x00000000";
								$return['result'] = 'sanity';
								$return['message'] = "SameHeight | Peer {$address} need sanity - DiffSeconds: {$diffTimeSeconds}";
								break;
							}
							*/

							//Valid new block in same hiehgt to add in Blockchain
							$returnCode = Blockchain::isValidBlockMinedByPeerInSameHeight($chaindata,$lastBlock,$blockMinedByPeer);
							if ($returnCode == "0x00000000") {

								Display::ShowMessageNewBlock('sanity',$lastBlock['height'],$blockMinedByPeer);

								//If have miner enabled, stop all miners
								Tools::clearTmpFolder();
								Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

								if ( isset( $msgFromPeer['node_ip'] ) && isset($msgFromPeer['node_port']) ) {
									Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer,array(
										'ip' => $msgFromPeer['node_ip'],
										'port' => $msgFromPeer['node_port']
									));
								}
								else {
									Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer,array());
								}

								$return['status'] = true;
								$return['error'] = $returnCode;
								$return['result'] = "";
								$return['message'] = "Block sane";

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

								//$return['status'] = true;
								//$return['error'] = $returnCode;
								//$return['result'] = 'sanity';
								//$return['message'] = 'Sameheight | else';
							}

							//Check integrity of my blockchain
							Blockchain::checkIntegrity($chaindata,null,10);

							break;
						}

						//Check if is a next block
						else if ($lastBlock['block_hash'] == $blockMinedByPeer->previous) {

							//We check that date of new block is not less than the last block
							if ($blockMinedByPeer->timestamp_end < $lastBlock['timestamp_end_miner']) {
								//Tools::writeLog('GOSSIP_MINEDBLOCK ('.Tools::GetIdFromIpAndPort($_SERVER['REMOTE_ADDR'],0).') -> Error 6x00000000');
								$return['status'] = true;
								$return['error'] = "6x00000000";
								$return['message'] = "Block date is from the past";
								$return['result'] = 'sanity';
								//Display::_error("NewBlock | Block date is from the past");
								break;
							}

							//Check if i have this block
							$blockAlreadyAdded = $chaindata->GetBlockByHash($blockMinedByPeer->hash);
							if ($blockAlreadyAdded != null) {
								$return['status'] = true;
								$return['error'] = '7x00000000';
								break;
							}

							//Check if difficulty its ok
							$currentDifficulty = Blockchain::checkDifficulty($chaindata,null,$isTestNet);
							if ($currentDifficulty[0] != $blockMinedByPeer->difficulty) {
								$return['status'] = true;
								$return['error'] = "4x00000000";
								$return['message'] = "Block difficulty hacked?";
								$return['result'] = 'sanity';
								//Display::_error("NewBlock | Block difficulty hacked?");
								break;
							}

							//Valid block to add in Blockchain
							$returnCode = Blockchain::isValidBlockMinedByPeer($chaindata,$lastBlock,$blockMinedByPeer);
							if ($returnCode == "0x00000000") {

								Display::ShowMessageNewBlock('imported',$lastBlock['height'],$blockMinedByPeer);

								//If have miner enabled, stop all miners
								Tools::clearTmpFolder();
								Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

								if ( isset( $msgFromPeer['node_ip'] ) && isset($msgFromPeer['node_port']) ) {
									Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer,array(
										'ip' => $msgFromPeer['node_ip'],
										'port' => $msgFromPeer['node_port']
									));
								}
								else {
									Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer,array());
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
								$return['result'] = 'sanity';
								$return['message'] = 'New block | else | ' . $returnCode;
							}

							break;
						}

						else {

							$return['status'] = true;
							$return['error'] = "7x00000000";
							$return['result'] = "";

							// if height of block submitted is lower than our current height, send sanity to peer
							if (($msgFromPeer['height'] - $lastBlock['height']) > 10) {
								//Check integrity of my blockchain
								Blockchain::checkIntegrity($chaindata,null,50);

								//If have miner enabled, stop all miners
								Tools::clearTmpFolder();
								Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

								$gossipINFO['syncing'] = true;
								Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."syncing","");
								/*
								// Start microsanity with this peer
								if (strlen($msgFromPeer['node_ip']) > 0 && strlen($msgFromPeer['node_port']) > 0)
									Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR."sync_with_peer",$msgFromPeer['node_ip'].":".$msgFromPeer['node_port']);
								Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
								*/
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
								$chaindata->addPeer($msgFromPeer['client_ip'],$msgFromPeer['client_port']);
								Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."new_peer",'%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($msgFromPeer['client_ip'],$msgFromPeer['client_port']));
								$return['result'] = "p2p_on";
							}
							else
								$return['result'] = "p2p_off";
						}
					break;
					case 'HELLO':
						//GG
						if (isset($msgFromPeer['client_ip']) && isset($msgFromPeer['client_port'])) {
							$return['status'] = true;
							$chaindata->addPeer($msgFromPeer['client_ip'],$msgFromPeer['client_port']);

							//Get more peers from this new peer
							//Peer::GetMorePeers($gossip, $msgFromPeer['client_ip'],$msgFromPeer['client_port']);
							Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."new_peer",'%LP%Network%W% Connected to peer		%G%peerId%W%='.Tools::GetIdFromIpAndPort($msgFromPeer['client_ip'],$msgFromPeer['client_port']));
						} else {
							$return['message'] = "No ClientIP or ClientPort defined";
						}
					break;
					case 'LASTBLOCKNUM':
						$return['status'] = true;
						$return['result'] = $chaindata->GetCurrentBlockNum();
					break;
					case 'STATUSNODE':
						$return['status'] = true;
						$config = $chaindata->GetAllConfig();
						$return['result'] = array(
							'hashrate'      => $config['hashrate'],
							'miner'         => $config['miner'],
							'network'       => $config['network'],
							'p2p'           => $config['p2p'],
							'syncing'       => $config['syncing'],
							'dbversion'     => $config['dbversion'],
							'nodeversion'   => $config['node_version'],
							'lastBlock'     => $chaindata->GetCurrentBlockNum()
						);
					break;
					case 'GETGENESIS':
						$return['status'] = true;
						$return['result'] = $chaindata->GetGenesisBlock();
					break;
					case 'SYNCBLOCKS':
						if (isset($msgFromPeer['from'])) {
							$return['status'] = true;
							$return['result'] = $chaindata->SyncBlocks($msgFromPeer['from']);
						}
						else {
							Display::print("ERROR SYNCBLOCKS");
						}
					break;
					case 'HELLO_PONG':
						$return['status'] = true;
					break;
				}

				//Determine isBusy
				if (strtoupper($msgFromPeer['action']) == 'MINEDBLOCK') {
					$gossipINFO['busy'] = false;
					@unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."busy");
				}

				$connection->write(@json_encode($return));
				$connection->end();
			}
		}
	});

	//Close connection from this peer
	$connection->on('end', function () use (&$connection): void {
		$connection->close();
	});

	//Remove peer when disconnect
	$connection->on('close', function () use ($connection): void {
		//unset($this->peers[$connection->getRemoteAddress()]);
	});
});

//Start node
$loop->run();
$socket->close();
?>
