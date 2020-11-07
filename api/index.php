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

include('../CONFIG.php');
include('../src/DB/DBTransactions.php');
include('../src/DB/DBContracts.php');
include('../src/DB/DBBlocks.php');
include('../src/DB/DBBase.php');
include('../src/DB/DB.php');
include('../src/ColorsCLI.php');
include('../src/Display.php');
include('../src/Subprocess.php');
include('../src/BootstrapNode.php');
include('../src/ArgvParser.php');
include('../src/Tools.php');
include('../src/Wallet.php');
include('../src/Block.php');
include('../src/Blockchain.php');
include('../src/Gossip.php');
include('../src/Key.php');
include('../src/Pki.php');
include('../src/PoW.php');
include('../src/REGEX.php');
include('../src/Transaction.php');
include('../src/GenesisBlock.php');
include('../src/Peer.php');
include('../src/Miner.php');
include('../src/SmartContract.php');
include('../src/SmartContractStateMachine.php');
include('../src/J4FVM/J4FVMTools.php');
include('../src/J4FVM/J4FVMSubprocess.php');
include('../src/uint256.php');
include('../src/Socket.php');
include('../src/Gas.php');
include('../funity/js.php');

require __DIR__ . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use React\Socket\ConnectionInterface;

date_default_timezone_set("UTC");

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

//Get Input steam data
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

//If not have data, check if have POST or GET request
if ($data != null) {
    $id = $data['id'];
    $method = $data['method'];
    $params = $data['params'];
} else {
    $id = (isset($_REQUEST['id'])) ? $_REQUEST['id']:null;
    $method = (isset($_REQUEST['method'])) ? $_REQUEST['method']:'';
    $params = (isset($_POST['params'])) ? $_POST['params']:$_GET;
}

//Instantiate response array JSON-RPC
$response_jsonrpc = array('jsonrpc'=>'2.0');

//Check if have request ID
if ($id != null) {

    //Check if NODE is alive
    if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK)) {
        $mainThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK);
        $minedTime = date_diff(
            date_create(date('Y-m-d H:i:s', $mainThreadTime)),
            date_create(date('Y-m-d H:i:s', time()))
        );
        $diffTime = $minedTime->format('%s');
        if ($diffTime >= 120) {

            $response_jsonrpc['error'] = array(
                'code'    => -100,
                'message' => 'Node not active'
            );
            die(json_encode($response_jsonrpc));
        }
	}

    //Check if have method
    if (strlen($method) > 0) {

        if (is_array($params)) {

            //Instantiate database blockchain
            $chaindata = new DB();

			$isTestnet = ($chaindata->GetConfig('network') == 'testnet') ? true:false;

            switch ($method) {

                case 'node_version':
                    $response_jsonrpc['result'] = $chaindata->GetConfig('node_version');
                break;

                case 'node_network':
                    $currentNetwork = 'mainnet';

                    $nodeNetwork = $chaindata->GetConfig('network');
                    if (strlen($nodeNetwork) > 0)
                        $currentNetwork = $nodeNetwork;

                    $response_jsonrpc['result'] = $currentNetwork;
                break;

                case 'node_peerCount':
                    $response_jsonrpc['result'] = count($chaindata->GetAllPeers());
                break;

                case 'node_listening':
                    $listening = false;

                    $nodeListening = $chaindata->GetConfig('p2p');
                    if (strlen($nodeListening) > 0 && $nodeListening == 'on')
                        $listening = true;

                    $response_jsonrpc['result'] = $listening;
                break;

                case 'node_syncing':
                    $syncing = false;

                    $nodeSyncing = $chaindata->GetConfig('syncing');
                    if (strlen($nodeSyncing) > 0 && $nodeSyncing == 'on')
                        $syncing = true;
					if ($syncing) {

						$highestBlock = $chaindata->GetConfig('highestBlock');

						$response_jsonrpc['result'] = array(
							"currentBlock" => $chaindata->GetCurrentBlockNum(),
							"highestBlock" => $highestBlock,
						);
					}
					else {
						$response_jsonrpc['result'] = $syncing;
					}
                break;

                case 'node_mining':
                    $mining = false;

                    $nodeMining = $chaindata->GetConfig('miner');
                    if (strlen($nodeMining) > 0 && $nodeMining == 'on')
                        $mining = true;

                    $response_jsonrpc['result'] = $mining;
                break;

                case 'node_hashrate':
                    $hashrate = "0 H/s";

                    $nodeHashrate = $chaindata->GetConfig('hashrate');
                    if (strlen($nodeHashrate) > 0)
                        $hashrate = $nodeHashrate;

                    $response_jsonrpc['result'] = $hashrate;
                break;

                case 'j4f_coinbase':
                    $wallet = "";

                    $walletCoinBase = Wallet::GetCoinbase();
                    if (is_array($walletCoinBase) && !empty($walletCoinBase)) {
                        $walletcb = Wallet::GetWalletAddressFromPubKey($walletCoinBase['public']);
                        if ($walletcb != null)
                            $wallet = $walletcb;
                    }

                    $response_jsonrpc['result'] = $wallet;
                break;

                case 'j4f_accounts':
                    $response_jsonrpc['result'] = Wallet::GetAccounts();
                break;

                case 'j4f_addAccount':
                    if (!isset($params['password']) || strlen($params['password']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        $infoNewWallet = Wallet::LoadOrCreate('',$params['password']);
                        if (is_array($infoNewWallet) && !empty($infoNewWallet)) {
                            $infoNewWallet['address'] = Wallet::GetWalletAddressFromPubKey($infoNewWallet['public']);

                            $response_jsonrpc['result'] = serialize($infoNewWallet);
                        }
                    }
                break;

                case 'j4f_blockNumber':
                    $response_jsonrpc['result'] = $chaindata->GetCurrentBlockNum();
                break;

                case 'j4f_getBalance':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 59) {
                            //Get wallet from Public key
                            $wallet = Wallet::GetWalletAddressFromPubKey($params['wallet']);
                        } else {
                            $wallet = $params['wallet'];
                        }

                        //Check if have wallet
                        if (strlen($wallet) < 59) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32602,
                                'message' => 'Invalid params'
                            );
                        } else if (strlen($wallet) == 0) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            //Write result on response
                            $response_jsonrpc['result'] = Wallet::API_GetBalance($wallet,$isTestnet);
                        }
                    }
                break;

                case 'j4f_getPendingBalance':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 59) {
                            //Get wallet from Public key
                            $wallet = Wallet::GetWalletAddressFromPubKey($params['wallet']);
                        } else {
                            $wallet = $params['wallet'];
                        }

                        //Check if have wallet
                        if (strlen($wallet) < 59) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32602,
                                'message' => 'Invalid params'
                            );
                        } else if (strlen($wallet) == 0) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            //Write result on response
                            $response_jsonrpc['result'] = Wallet::API_GetPendingBalance($wallet,$isTestnet);
                        }
                    }
                break;

                case 'j4f_getTransactionCount':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 59) {
                            //Get wallet from Public key
                            $wallet = Wallet::GetWalletAddressFromPubKey($params['wallet']);
                        } else {
                            $wallet = $params['wallet'];
                        }

                        //Check if have wallet
                        if (strlen($wallet) == 0) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            //Write result on response
                            $response_jsonrpc['result'] = Wallet::GetSendedTransactionsCount($wallet,$isTestnet);
                        }
                    }
                break;

                case 'j4f_getBlockTransactionCountByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $response_jsonrpc['result'] = $chaindata->GetBlockTransactionsCountByHash($params['hash']);
                    }
                break;

                case 'j4f_getBlockTransactionCountByNumber':
                    if (!isset($params['height']) || strlen($params['height']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $response_jsonrpc['result'] = $chaindata->GetBlockTransactionsCountByHeight($params['height']);
                    }
                break;

                case 'j4f_sendTransaction':

                    if (
                        (!isset($params['from']) || strlen($params['from']) == 0) ||
                        (!isset($params['password']) || strlen($params['password']) == 0) ||
                        (!isset($params['to']) || strlen($params['to']) == 0) ||
                        (!isset($params['amount']) || strlen($params['amount']) == 0)
                    ) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

						$password = ($params['password'] == 'null') ? '':$params['password'];

						$data = (isset($params['data'])) ? $params['data']:"";
						$gasLimit = (isset($params['gasLimit'])) ? $params['gasLimit']:21000;
						$gasPrice = (isset($params['gasPrice'])) ? $params['gasPrice']:"0.0000000001";

						//Instance the pointer to the chaindata
						$txnHash = Wallet::API_SendTransaction($params['from'],$password,$params['to'],$params['amount'],$data,$gasLimit,$gasPrice,$isTestnet);

                        //Check if transaction have error
                        if (strpos($txnHash,'Error') !== false) {
                            $response_jsonrpc['error'] = array(
                                'code'    => 100,
                                'message' => $txnHash
                            );
                        } else {
                            $response_jsonrpc['result'] = $txnHash;
                        }
                    }
                break;

				case 'j4f_calcGas':

                    if (
                        (!isset($params['to']) || strlen($params['to']) == 0) ||
                        (!isset($params['data']) || strlen($params['data']) == 0)
                    ) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

						$to = (isset($params['to'])) ? $params['to']:"";
						$data = (isset($params['data'])) ? $params['data']:"";

						//Instance the pointer to the chaindata

				        $chaindata = new DB();
						$gas = Gas::calculateGasTxn($chaindata,$to,$data);

                        //Check if transaction have error
                        if ($gas == null) {
                            $response_jsonrpc['error'] = array(
                                'code'    => 100,
                                'message' => $txnHash
                            );
                        } else {
                            $response_jsonrpc['result'] = $gas;
                        }
                    }
                break;

                case 'j4f_getBlockByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $withTransactions = false;
                        if (isset($params['transactions']) && ($params['transactions'] == true || $params['transactions'] == 1))
                            $withTransactions = true;

                        $block = $chaindata->GetBlockByHash($params['hash'],$withTransactions);
                        $blockInfo = @unserialize($block['info']);

                        if (!is_array($block) || empty($block)) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            $response_jsonrpc['result'] = array(
                                'height'            => $block['height'],
                                'hash'              => $block['block_hash'],
                                'parentHash'        => $block['block_previous'],
                                'nonce'             => $block['nonce'],
                                'merkleRoot'        => $block['root_merkle'],
                                'miner'             => $chaindata->GetMinerOfBlockByHash($block['block_hash']),
                                'difficulty'        => $block['difficulty'],
                                'maxDifficulty'     => $blockInfo['max_difficulty'],
                                'timestamp'         => $block['timestamp_end_miner'],
                                'transactions'      => $block['transactions']
                            );
                        }
                    }
                break;

                case 'j4f_getBlockByNumber':
                    if (!isset($params['height']) || strlen($params['height']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $withTransactions = false;
                        if (isset($params['transactions']) && ($params['transactions'] == true || $params['transactions'] == 1))
                            $withTransactions = true;

                        $block = $chaindata->GetBlockByHeight($params['height'],$withTransactions);
                        $blockInfo = @unserialize($block['info']);

                        if (!is_array($block) || empty($block)) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            $response_jsonrpc['result'] = array(
                                'height'            => $block['height'],
                                'hash'              => $block['block_hash'],
                                'parentHash'        => $block['block_previous'],
                                'nonce'             => $block['nonce'],
                                'merkleRoot'        => $block['root_merkle'],
                                'miner'             => $chaindata->GetMinerOfBlockByHash($block['block_hash']),
                                'difficulty'        => $block['difficulty'],
                                'maxDifficulty'     => $blockInfo['max_difficulty'],
                                'timestamp'         => $block['timestamp_end_miner'],
                                'transactions'      => $block['transactions']
                            );
                        }
                    }
                break;

                case 'j4f_getTransactionByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Get transaction
                        $transaction = $chaindata->GetTransactionByHash($params['hash']);

                        //Check if transaction data its ok
                        if (!is_array($transaction) || empty($transaction)) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            $response_jsonrpc['result'] = array(
                                'blockHash'         => $transaction['block_hash'],
                                'blockHeight'       => $chaindata->GetBlockHeightByHash($transaction['block_hash']),
                                'hash'              => $transaction['txn_hash'],
                                'from'              => ($transaction['wallet_from'] == "") ? 'REWARD_MINER':$transaction['wallet_from'],
                                'to'                => $transaction['wallet_to'],
                                'amount'            => $transaction['amount'],
								'gasLimit'          => $transaction['gasLimit'],
								'gasPrice'          => $transaction['gasPrice'],
                                'signature'         => $transaction['signature'],
								'data'         		=> $transaction['data']
                            );
                        }
                    }
                break;

                case 'j4f_sign':
                    if (
                        (!isset($params['wallet']) || strlen($params['wallet']) == 0) ||
                        (!isset($params['password']) || strlen($params['password']) == 0)
                    ) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
						//Check if have wallet

                        if (strlen($params['wallet']) < 59) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32602,
                                'message' => 'Invalid params'
                            );
                        } else {

							$infoWallet = Wallet::Load($params['wallet']);

                            if (!is_array($infoWallet) || empty($infoWallet)) {
                                $response_jsonrpc['error'] = array(
                                    'code'    => -32603,
                                    'message' => 'Internal error'
                                );
                            }
                            else {

                                if ($params['password'] == 'null')
                                    $params['password'] = '';

                                if ($sign = Pki::encrypt('', $infoWallet['private'],$params['password'])) {
                                    $response_jsonrpc['result'] = $sign;
                                } else {
                                    $response_jsonrpc['error'] = array(
                                        'code'    => 100,
                                        'message' => "Error, Can't sign with this password"
                                    );
                                }
                            }
                        }
                    }
                break;

				case 'j4f_parse':
                    if (!isset($params['data']) || strlen($params['data']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        //Parse string
                        $response_jsonrpc['result'] = Tools::str2hex($params['data']);
                    }
                break;

				case 'j4f_getContractByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Get contract
                        $contract = $chaindata->GetContractByHash($params['hash']);

                        //Check if contract data its ok
                        if (!is_array($contract) || empty($contract)) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {
                            $response_jsonrpc['result'] = array(
                                'txnHash'         	=> $contract['txn_hash'],
                                'contractHash'      => $contract['contract_hash'],
								'code'         		=> $contract['code']
                            );
                        }
                    }
                break;

				case 'j4f_callReadFunctionContractByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0 || !isset($params['data']) || strlen($params['data']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        //Get contract
                        $contract = $chaindata->GetContractByHash($params['hash']);

                        //Check if contract data its ok
                        if (!is_array($contract) || empty($contract)) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32603,
                                'message' => 'Internal error'
                            );
                        } else {

							$j4fvm_process = new J4FVMSubprocess('READ');

							//echo J4FVMTools::GetFunityVersion($contract['code']);

							//Set info for J4FVM
							$j4fvm_process->setContractHash($params['hash']);
							$j4fvm_process->setTxnHash('empty');
							$j4fvm_process->setVersion(J4FVMTools::GetFunityVersion($contract['code']));
							$j4fvm_process->setFrom('0');
							$j4fvm_process->setAmount('0');
							$j4fvm_process->setData($params['data']);

							//Run contract
							$statusRun = $j4fvm_process->run();
							if ($statusRun !== "1") {
								$response_jsonrpc['error'] = array(
	                                'code'    => -32604,
	                                'message' => 'Internal error'
	                            );
							}
							else {
								$outputCall = '';
								foreach ($j4fvm_process->output() as $line)
									$outputCall .= $line;
								$response_jsonrpc['result'] = $outputCall;
							}
                        }
                    }
                break;

                default:
                    $response_jsonrpc['error'] = array(
                        'code'    => -32601,
                        'message' => 'Method not found'
                    );
                break;
            }
        } else {
            $response_jsonrpc['error'] = array(
                'code'    => -32600,
                'message' => 'Invalid Request'
            );
        }
    } else {
        $response_jsonrpc['error'] = array(
            'code'    => -32700,
            'message' => 'Invalid Request'
        );
    }

    //Add request id to response
    $response_jsonrpc['id'] = $id;

} else {
    $response_jsonrpc['error'] = array(
        'code'    => -32700,
        'message' => 'Invalid Request'
    );
}

echo json_encode($response_jsonrpc);

?>
