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

class Wallet {

    /**
     * Load or create new wallet
     *
	 * @param string $account
     * @param string $password
     * @return array|mixed
     */
    public static function LoadOrCreate(string $account,string $password) : array {

        if ($password != null && $password == 'null')
            $password = '';

        //By default, the file we want to check is the name of the account
        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$account.".dat";

        //If the wallet exists, we load it
        if (strlen($account) > 0 && @file_exists($wallet_file)) {
            return @unserialize(@file_get_contents($wallet_file));
        } else {
            //There is no wallet so we generate the public and private key
            $keys = Pki::generateKeyPair($password);

            //If the account we want to create is different from the coinbase account, we will save the information with the name of the address file
            if ($account != "coinbase")
                $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.Wallet::GetWalletAddressFromPubKey($keys['public']).".dat";

            //We keep the keys
            @file_put_contents($wallet_file, serialize($keys));

            return $keys;
        }
    }

    /**
     * Load or create new wallet
     *
     * @param string $account
     * @return array|mixed
     */
    public static function Load(string $account) : array {

        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$account.".dat";

        //If the wallet exists, we load it
        if (strlen($account) > 0 && @file_exists($wallet_file)) {
            return @unserialize(@file_get_contents($wallet_file));
        }
        return null;
    }

    /**
     * Get all wallets
     *
     * @return array
     */
    public static function GetAccounts() : array {

        $accounts = array();

        $files = scandir(Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'wallets'.DIRECTORY_SEPARATOR);
        foreach($files as $file) {
            if ($file == '.' || $file == '..')
                continue;

            $accountKeys = @unserialize(@file_get_contents(Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'wallets'.DIRECTORY_SEPARATOR.$file));

            if (is_array($accountKeys) && !empty($accountKeys)) {
                $walletAccount = Wallet::GetWalletAddressFromPubKey($accountKeys['public']);
                if ($walletAccount != null)
                    $accounts[] = $walletAccount;
            }
        }

        return $accounts;
    }

    /**
     * Get coinbase info
     *
     * @return array
     */
    public static function GetCoinbase() : array {
        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR."coinbase.dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return null;
    }

    /**
     * Get wallet info
     *
     * @param $address
     * @return array
     */
    public static function GetWallet(string $address) : array {
        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$address.".dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return null;
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return string
     */
    public static function GetBalance(string $address,bool $isTestNet=false) : string {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }
		else {
			//Check walletFrom Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (!@preg_match($REGEX_Address,$address)) {
            	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Address have bad format".PHP_EOL;
			}
		}

        $chaindata = new DB();

        //Check if are synched
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();

		//Have same num blocks
        if ($lastBlockNum != $lastBlockNum_Local)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Blockchain it is not synchronized".PHP_EOL;

		$current = 0;
		$walletInfo = $chaindata->GetWalletInfo($address);
		if (!empty($walletInfo)) {
			$current = $walletInfo['current'];
		}

		return $current;
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return string
     */
    public static function API_GetBalance(string $address,bool $isTestNet=false) : string {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }
		else {
			//Check walletFrom Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (!@preg_match($REGEX_Address,$address)) {
				return "Error, address have bad format".PHP_EOL;
			}
		}

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

		$current = 0;
		$walletInfo = $chaindata->GetWalletInfo($address);
		if (!empty($walletInfo)) {
			$current = $walletInfo['current'];
		}

		return $current;
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return float
     */
    public static function API_GetPendingBalance(string $address,bool $isTestNet=false) : float {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "ERROR: Blockchain it is not synchronized";

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalPending = "0";

        $totalReceivedPending_tmp = $chaindata->db->query("SELECT amount FROM txnpool WHERE wallet_to = '".$address."';");
        if (!empty($totalReceivedPending_tmp)) {
            while ($txnInfo = $totalReceivedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalPending = @bcadd($totalPending, $txnInfo['amount'], 18);
            }
        }

        return uint256::parse($totalPending);
    }

    /**
     * Get Balance of wallet
     *
     * @param DB $chaindata
     * @param string $address
     * @return string
     */
    public static function GetBalanceWithChaindata(DB &$chaindata,string $address) : string {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

		$current = 0;
		$walletInfo = $chaindata->GetWalletInfo($address);
		if (!empty($walletInfo)) {
			$current = $walletInfo['current'];
		}
		return $current;
    }

    /**
     * Gets the wallet address of a public key
     *
     * @param string $pubKey
     * @return string
     */
    public static function GetWalletAddressFromPubKey($pubKey) : string {
        $pubKey = self::ParsePubKey($pubKey);
        if (strlen($pubKey) == 451) {
			return "J4F".hash('sha3-224',$pubKey);
        } else {
            return null;
        }
    }

    /**
     * Return number of transactions and total amount sended by wallet
     *
     * @param string $wallet
     * @param bool $isTestNet
     *
     * @return array
     */
    public static function GetSendedTransactionsCount(string $wallet,bool $isTestNet=false) : array {

        //Instantiate DB
        $chaindata = new DB();

        //Check if node is synchronized
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();
        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        $totalSended = "0";
        $totalTransactions = 0;

        $totalSpended_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSended = bcadd($totalSended, $txnInfo['amount'], 18);
                $totalTransactions++;
            }
        }

        return array($totalTransactions,$totalSended);
    }

    /**
     * Return number of transactions and total amount pending to send by wallet
     *
     * @param $wallet
     * @param $isTestNet
     *
     * @return array
     */
    public static function GetPendingSendedTransactionsCount(string $wallet,bool $isTestNet=false) : array {

        //Instantiate DB
        $chaindata = new DB();

        //Check if node is synchronized
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();
        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        $totalPendingSended = "0";
        $totalTransactions = 0;

        $totalSendedPending_tmp = $chaindata->db->query("SELECT amount FROM txnpool WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSendedPending_tmp)) {
            while ($txnInfo = $totalSendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalPendingSended = bcadd($totalPendingSended, $txnInfo['amount'], 18);
            }
        }

        return array($totalTransactions,$totalPendingSended);
    }

    /**
     * Parse public key
     *
     * @param string $pubKey
     * @return string
     */
    public static function ParsePubKey(string $pubKey) : string {

        //Clear PUBLIC START END KEY
        $pubKey = str_replace('-----BEGIN PUBLIC KEY-----','',$pubKey);
        $pubKey = str_replace('-----END PUBLIC KEY-----','',$pubKey);

        //Clear break lines
        $pubKey = str_replace("\r",'',$pubKey);
        $pubKey = str_replace("\n",'',$pubKey);
        $pubKey = trim($pubKey);

        //If pubkey if sended by get need parse spaces
        $pubKey = str_replace(" ",'+',$pubKey);

        $parsedPubKey = "-----BEGIN PUBLIC KEY-----\n";
        $parsedPubKey .= trim(substr($pubKey,0,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,64,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,128,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,192,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,256,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,320,64))."\n";
        $parsedPubKey .= trim(substr($pubKey,384,8))."\n";
        $parsedPubKey .= "-----END PUBLIC KEY-----\n";

        if (strlen($parsedPubKey) == 451)
            return $parsedPubKey;
        else
            return null;

    }

    /**
     * Given a direction, get the wallet
     *
     * @param string $address
     * @return string
     */
    public static function GetWalletAddressFromAddress(string $address) : string {
        if (strpos("J4F",$address) === false)
            return "J4F".$address;
        else
            return $address;
    }

    /**
     * Send a transaction
     *
     * @param string $wallet_from
     * @param string $wallet_from_password
     * @param string $wallet_to
     * @param string $amount
	 * @param string $data
     * @param bool $isTestNet
     * @param bool $cli
     * @return string
     */
    public static function SendTransaction(string $wallet_from,string $wallet_from_password,string $wallet_to,string $amount,string $data, int $gasLimit = 21000, string $gasPrice = "0.0000000001", bool $isTestNet=false,bool $cli=true) : string {

        //Instance the pointer to the chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
		$lastBlockNum_Local = $chaindata->GetCurrentBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Blockchain it is not synchronized".PHP_EOL;

        if (@bccomp($amount ,"0",18) == -1)
			if ($cli)
            	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Minium to send 0".PHP_EOL;
			else
				return "Error, Minium to send 0";

		if ($data != null) {
			$isDataParsed = strpos($data,'0x');
			if ($isDataParsed === false) {
				return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Input data malformed".PHP_EOL;
			}
			else if ($isDataParsed > 0) {
				return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Input data malformed".PHP_EOL;
			}
		}

        if ($wallet_from == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $wallet_from = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        } else {
            $wallet_from_info = self::GetWallet($wallet_from);
        }

        if ($wallet_to == "coinbase")
            $wallet_to = self::GetWalletAddressFromPubKey(self::GetCoinbase()['public']);

        // If have wallet from info
        if ($wallet_from_info !== false) {

			//Check walletFrom Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (!@preg_match($REGEX_Address,$wallet_from)) {
				if ($cli)
                	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Wallet_from have bad format".PHP_EOL;
				else
					return "Error, Wallet_from have bad format".PHP_EOL;
			}

			//Check walletTo Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			$REGEX_Contract = '/[a-fA-F0-9]{64}/';
			if (!@preg_match($REGEX_Address,$wallet_to) && !@preg_match($REGEX_Contract,$wallet_to)) {
				if ($cli)
                	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Wallet_to have bad format".PHP_EOL;
				else
					return "Error, Wallet_to have bad format".PHP_EOL;
			}

            // Get current balance of wallet
            $currentBalance = self::GetBalance($wallet_from,$isTestNet);

			//Calculate amount + fees
			$feeGas = @bcmul($gasLimit,$gasPrice,18);
			$amountWithFees = @bcadd($amount,$feeGas,18);

            // If have balance amounts + fees
            if (@bccomp($currentBalance,$amountWithFees,18) == 0 || @bccomp($currentBalance,$amountWithFees,18) == 1) {

                //Make transaction and sign
				$transaction = Transaction::withGas($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password,$data,$gasLimit,$gasPrice);

                // Check if transaction is valid
                if ($transaction->isValid()) {

                    //We add the pending transaction to send into our chaindata
                    if ($chaindata->addTxnToPool($transaction->message(),$transaction)) {

						//Send transaction to network
						self::sendTxnToNetwork($chaindata,$transaction);

						//Close DB Instance
						$chaindata->db->close();

	                    $return_message = "";
	                    if ($cli) {
	                        $return_message = "Transaction created successfully".PHP_EOL;
	                        $return_message .= "TX: ".ColorsCLI::$FG_GREEN. $transaction->message().ColorsCLI::$FG_WHITE.PHP_EOL;
	                    }
	                    else {
	                        $return_message = $transaction->message();
	                    }
	                    return $return_message;
					}
					else {
						return "Error, An error occurred while trying to create the transaction. Try again";
					}
                } else {
                    return "An error occurred while trying to create the transaction".PHP_EOL."The wallet_from password may be incorrect".PHP_EOL;
                }
            } else {
				if ($cli)
                	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;
				else
					return "Error, There is not enough balance in the account".PHP_EOL;
            }
        } else {
            $return_message = "Could not find the ".ColorsCLI::$FG_RED."public/private key".ColorsCLI::$FG_WHITE." of wallet ".ColorsCLI::$FG_GREEN.$wallet_from.ColorsCLI::$FG_WHITE.PHP_EOL;
            $return_message .= "Please check that in the directory ".ColorsCLI::$FG_CYAN.Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.ColorsCLI::$FG_WHITE." there is the keystore of the wallet".PHP_EOL;
            return $return_message;
        }
    }

	/**
     * Send a transaction called by API
     *
     * @param string $wallet_from
     * @param string $wallet_from_password
     * @param string $wallet_to
     * @param string $amount
	 * @param string $data
	 * @param int $gasLimit
	 * @param string $gasPrice
     * @param bool $isTestNet
     * @param bool $cli
     * @return string
     */
    public static function API_SendTransaction(string $wallet_from,string $wallet_from_password,string $wallet_to,string $amount,string $data, int $gasLimit = 21000, string $gasPrice = "0.0000000001",bool $isTestNet=false,bool $cli=true) : string {

        //Instance the pointer to the chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetCurrentBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        if (bccomp($amount ,"0",18) == -1)
            return "Error, Minium to send 0";

		if ($data != null) {
			$isDataParsed = strpos($data,'0x');
			if ($isDataParsed === false) {
				return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Input data malformed".PHP_EOL;
			}
			else if ($isDataParsed > 0) {
				return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Input data malformed".PHP_EOL;
			}
		}

        //Check if wallet from its coinbase
        if ($wallet_from == "coinbase")
            return "Error, Cannot use coinbase from API";
        else
            $wallet_from_info = self::GetWallet($wallet_from);

        //Check if wallet to its coinbase
        if ($wallet_to == "coinbase")
            $wallet_to = self::GetWalletAddressFromPubKey(self::GetCoinbase()['public']);

        // If have wallet from info
        if ($wallet_from_info !== false) {

			//Check walletFrom Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (!@preg_match($REGEX_Address,$wallet_from)) {
				if ($cli)
                	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Wallet_from have bad format".PHP_EOL;
				else
					return "Error, Wallet_from have bad format".PHP_EOL;
			}

			//Check walletTo Format
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			$REGEX_Contract = '/[a-fA-F0-9]{64}/';
			if (!@preg_match($REGEX_Address,$wallet_to) && !@preg_match($REGEX_Contract,$wallet_to)) {
				if ($cli)
                	return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Wallet_to have bad format".PHP_EOL;
				else
					return "Error, Wallet_to have bad format".PHP_EOL;
			}

            // Get current balance of wallet
            $currentBalance = self::GetBalance($wallet_from,$isTestNet);

			//Calculate amount + fees
			$feeGas = @bcmul($gasLimit,$gasPrice,18);
			$amountWithFees = @bcadd($amount,$feeGas,18);

            // If have balance
            if (@bccomp($currentBalance,$amountWithFees,8) == 0 || @bccomp($currentBalance,$amountWithFees,8) == 1) {

				//Make transaction and sign
				$transaction = Transaction::withGas($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password,$data,$gasLimit,$gasPrice);

                // Check if transaction is valid
                if ($transaction->isValid()) {

					//We add the pending transaction to send into our chaindata
                    if ($chaindata->addTxnToPool($transaction->message(),$transaction)) {

						//Send transaction to network
						self::sendTxnToNetwork($chaindata,$transaction);

						//Close DB Instance
						$chaindata->db->close();

	                    return $transaction->message();
					}
					else {
						return "Error, An error occurred while trying to create the transaction. Try again";
					}
                }
                else {
                    return "Error, An error occurred while trying to create the transaction. The wallet_from password may be incorrect";
                }
            }
            else {
                return "Error, There is not enough balance in the account";
            }
        }
        else {
            $return_message = "Could not find the public/private key of wallet ".$wallet_from.".";
            return $return_message;
        }
    }

	/**
     * Propagate txn to network
     *
     * @param DB $chaindata
     * @param Transaction $transaction
     */
	public static function sendTxnToNetwork(DB &$chaindata,Transaction $transaction) : void {
		$peers = $chaindata->GetAllPeers();
		$config = $chaindata->GetAllConfig();

		$txnFromPool = $chaindata->GetTxnFromPoolByHash($transaction->hash);

		foreach ($peers as $peer) {

			$myPeerID = Tools::GetIdFromIpAndPort($config['node_ip'],$config['node_port']);
			$peerID = Tools::GetIdFromIpAndPort($peer['ip'],$peer['port']);

			if ($myPeerID != $peerID && is_array($txnFromPool)) {
				$infoToSend = array(
					'action' => 'ADDPENDINGTRANSACTIONS',
					'txs' => @serialize(array($txnFromPool))
				);
				Socket::sendMessage($peer['ip'],$peer['port'],$infoToSend);
			}
		}
	}

}
?>
