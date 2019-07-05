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

include('../CONFIG.php');
include('../src/DB.php');
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
include('../src/Transaction.php');
include('../src/GenesisBlock.php');
include('../src/Peer.php');
include('../src/Miner.php');

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

                    $response_jsonrpc['result'] = $syncing;
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

                case 'mxc_coinbase':
                    $wallet = "";

                    $walletCoinBase = Wallet::GetCoinbase();
                    if (is_array($walletCoinBase) && !empty($walletCoinBase)) {
                        $walletcb = Wallet::GetWalletAddressFromPubKey($walletCoinBase['public']);
                        if ($walletcb != null)
                            $wallet = $walletcb;
                    }

                    $response_jsonrpc['result'] = $wallet;
                break;

                case 'mxc_accounts':
                    $response_jsonrpc['result'] = Wallet::GetAccounts();
                break;

                case 'mxc_addAccount':
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

                case 'mxc_blockNumber':
                    $response_jsonrpc['result'] = $chaindata->GetLastBlock(false)['height'];
                break;

                case 'mxc_getBalance':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 40) {
                            //Get wallet from Public key
                            $wallet = Wallet::GetWalletAddressFromPubKey($params['wallet']);
                        } else {
                            $wallet = $params['wallet'];
                        }

                        //Check if have wallet
                        if (strlen($wallet) < 35) {
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

                case 'mxc_getPendingBalance':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 40) {
                            //Get wallet from Public key
                            $wallet = Wallet::GetWalletAddressFromPubKey($params['wallet']);
                        } else {
                            $wallet = $params['wallet'];
                        }

                        //Check if have wallet
                        if (strlen($wallet) < 35) {
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

                case 'mxc_getTransactionCount':
                    if (!isset($params['wallet']) || strlen($params['wallet']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {

                        //Check if wallet is a pubKey
                        if (strlen($params['wallet']) > 40) {
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

                case 'mxc_getBlockTransactionCountByHash':
                    if (!isset($params['hash']) || strlen($params['hash']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $response_jsonrpc['result'] = $chaindata->GetBlockTransactionsCountByHash($params['hash']);
                    }
                break;

                case 'mxc_getBlockTransactionCountByNumber':
                    if (!isset($params['height']) || strlen($params['height']) == 0) {
                        $response_jsonrpc['error'] = array(
                            'code'    => -32602,
                            'message' => 'Invalid params'
                        );
                    } else {
                        $response_jsonrpc['result'] = $chaindata->GetBlockTransactionsCountByHeight($params['height']);
                    }
                break;

                case 'mxc_sendTransaction':

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

                        //Default fee = normal
                        $fee = 2;

                        //Check if fee is sended and its ok
                        if (isset($params['fee']) && strlen($params['fee']) == 0) {
                            $response_jsonrpc['error'] = array(
                                'code'    => -32602,
                                'message' => 'Invalid params'
                            );
                        }
                        else {
                            if (isset($params['fee'])) {
                                if (strtolower($params['fee']) == 'high')
                                    $fee = 3;
                                else if (strtolower($params['fee']) == 'medium')
                                    $fee = 2;
                                else if (strtolower($params['fee']) == 'low')
                                    $fee = 1;
                                else
                                    $fee = 2;
                            }
                        }

                        $password = ($params['password'] == 'null') ? '':$params['password'];

                        $txnHash = Wallet::API_SendTransaction($params['from'],$password,$params['to'],$params['amount'],$fee,$isTestnet);

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

                case 'mxc_getBlockByHash':
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

                case 'mxc_getBlockByNumber':
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

                case 'mxc_getTransactionByHash':
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
                                'fee'               => $transaction['tx_fee'],
                                'amount'            => $transaction['amount'],
                                'signature'         => $transaction['signature']
                            );
                        }
                    }
                break;

                case 'mxc_sign':
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
                        if (strlen($params['wallet']) < 35) {
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