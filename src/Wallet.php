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

class Wallet {

    /**
     * Load or create new wallet
     *
     * @param $account
     * @param $password
     * @return array|mixed
     */
    public static function LoadOrCreate($account,$password) {

        if ($password != null && $password == 'null')
            $password = null;

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
     * @param $account
     * @param $password
     * @return array|mixed
     */
    public static function Load($account) {

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
     * @return bool|mixed
     */
    public static function GetAccounts() {

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
     * @return bool|mixed
     */
    public static function GetCoinbase() {
        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR."coinbase.dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return false;
    }

    /**
     * Get wallet info
     *
     * @param $address
     * @return bool|mixed
     */
    public static function GetWallet($address) {
        $wallet_file = Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$address.".dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return false;
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return int|mixed
     */
    public static function GetBalance($address,$isTestNet=false) {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Blockchain it is not synchronized".PHP_EOL;

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalReceived = "0";
        $totalSpend = "0";

        $totalReceived_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$address."';");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSpended_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$address."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPending_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$address."';");
        if (!empty($totalSpendedPending_tmp)) {
            while ($txnInfo = $totalSpendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPendingToSend_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_from = '".$address."';");
        if (!empty($totalSpendedPendingToSend_tmp)) {
            while ($txnInfo = $totalSpendedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        return bcsub($totalReceived,$totalSpend,8);
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return int|mixed
     */
    public static function API_GetBalance($address,$isTestNet=false) {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalReceived = "0";
        $totalSpend = "0";

        $totalReceived_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$address."';");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSpended_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$address."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPending_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$address."';");
        if (!empty($totalSpendedPending_tmp)) {
            while ($txnInfo = $totalSpendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPendingToSend_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_from = '".$address."';");
        if (!empty($totalSpendedPendingToSend_tmp)) {
            while ($txnInfo = $totalSpendedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        return bcsub($totalReceived,$totalSpend,8);
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @param $isTestNet
     * @return int|mixed
     */
    public static function API_GetPendingBalance($address,$isTestNet=false) {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "ERROR: Blockchain it is not synchronized";

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalReceived = "0";

        $totalReceivedPending_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending WHERE wallet_to = '".$address."';");
        if (!empty($totalReceivedPending_tmp)) {
            while ($txnInfo = $totalReceivedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        $totalReceivedPendingToSend_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_to = '".$address."';");
        if (!empty($totalReceivedPendingToSend_tmp)) {
            while ($txnInfo = $totalReceivedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        return number_format($totalReceived,8);
    }

    /**
     * Get Balance of wallet
     *
     * @param DB $chaindata
     * @param $address
     * @return int|mixed
     */
    public static function GetBalanceWithChaindata(&$chaindata,$address) {

        if ($address == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $address = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        }

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalReceived = "0";
        $totalSend = "0";

        $totalReceived_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$address."';");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSended_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$address."';");
        if (!empty($totalSended_tmp)) {
            while ($txnInfo = $totalSended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSend = bcadd($totalSend, $txnInfo['amount'], 8);
            }
        }

        $totalSendedPending_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$address."';");
        if (!empty($totalSendedPending_tmp)) {
            while ($txnInfo = $totalSendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSend = bcadd($totalSend, $txnInfo['amount'], 8);
            }
        }

        return bcsub($totalReceived,$totalSend,8);
    }

    /**
     * Gets the wallet address of a public key
     *
     * @param $pubKey
     * @return mixed
     */
    public static function GetWalletAddressFromPubKey($pubKey) {
        $pubKey = self::ParsePubKey($pubKey);
        if (strlen($pubKey) == 451) {
			return "VTx".md5($pubKey);
			//return "wMx".hash('sha256', md5(hash('sha256', $pubKey)));
        } else {
            return null;
        }
    }

    /**
     * Return number of transactions and total amount sended by wallet
     *
     * @param $wallet
     * @param $isTestNet
     *
     * @return mixed
     */
    public static function GetSendedTransactionsCount($wallet,$isTestNet=false) {

        //Instantiate DB
        $chaindata = new DB();

        //Check if node is synchronized
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();
        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        $totalSended = "0";
        $totalTransactions = 0;

        $totalSpended_tmp = $chaindata->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSended = bcadd($totalSended, $txnInfo['amount'], 8);
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
     * @return mixed
     */
    public static function GetPendingSendedTransactionsCount($wallet,$isTestNet=false) {

        //Instantiate DB
        $chaindata = new DB();

        //Check if node is synchronized
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();
        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        $totalPendingSended = "0";
        $totalTransactions = 0;

        $totalSendedPending_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSendedPending_tmp)) {
            while ($txnInfo = $totalSendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalPendingSended = bcadd($totalPendingSended, $txnInfo['amount'], 8);
            }
        }

        $totalSendedPendingToSend_tmp = $chaindata->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSendedPendingToSend_tmp)) {
            while ($txnInfo = $totalSendedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalPendingSended = bcadd($totalPendingSended, $txnInfo['amount'], 8);
            }
        }

        return array($totalTransactions,$totalPendingSended);
    }

    /**
     * Parse public key
     *
     * @param $pubKey
     * @return null|string
     */
    public static function ParsePubKey($pubKey) {

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
     * @param $address
     * @return string
     */
    public static function GetWalletAddressFromAddress($address) {
        if (strpos("VTx",$address) === false)
            return "VTx".$address;
        else
            return $address;
    }

    /**
     * Enviamos una transaccion
     *
     * @param $wallet_from
     * @param $wallet_from_password
     * @param $wallet_to
     * @param $amount
     * @param $tx_fee
     * @param $isTestNet
     * @param $cli
     * @return string
     */
    public static function SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$tx_fee,$data,$isTestNet=false,$cli=true) {

        //Instance the pointer to the chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
		$lastBlockNum_Local = $chaindata->GetNextBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Blockchain it is not synchronized".PHP_EOL;

        if (bccomp($amount ,"0.00000001",8) == -1)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." Minium to send 0.00000001".PHP_EOL;

        if ($tx_fee == 3 && bccomp($amount ,"0.00001400",8) == -1)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

        if ($tx_fee == 2 && bccomp($amount ,"0.00000900",8) == -1)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

        if ($tx_fee == 1 && bccomp($amount ,"0.00000250",8) == -1)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

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
            // Get current balance of wallet
            $currentBalance = self::GetBalance($wallet_from,$isTestNet);

            // If have balance
            if (bccomp($currentBalance,$amount,8) == 0 || bccomp($currentBalance,$amount,8) == 1) {
                if ($tx_fee == 3)
                    $amount = bcsub($amount,"0.00001400",8);
                else if ($tx_fee == 2)
                    $amount = bcsub($amount,"0.00000900",8);
                else if ($tx_fee == 1)
                    $amount = bcsub($amount,"0.00000250",8);

                //Make transaction and sign
                $transaction = new Transaction($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password,$tx_fee,$data);

                // Check if transaction is valid
                if ($transaction->isValid()) {

                    //Instance the pointer to the chaindata
                    $chaindata = new DB();

                    //We add the pending transaction to send into our chaindata
                    $chaindata->addPendingTransactionToSend($transaction->message(),$transaction);

                    $return_message = "";
                    if ($cli) {
                        $return_message = "Transaction created successfully".PHP_EOL;
                        $return_message .= "TX: ".ColorsCLI::$FG_GREEN. $transaction->message().ColorsCLI::$FG_WHITE.PHP_EOL;
                    }
                    else {
                        $return_message = $transaction->message();
                    }
                    return $return_message;

                } else {
                    return "An error occurred while trying to create the transaction".PHP_EOL."The wallet_from password may be incorrect".PHP_EOL;
                }
            } else {
                return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;
            }
        } else {
            $return_message = "Could not find the ".ColorsCLI::$FG_RED."public/private key".ColorsCLI::$FG_WHITE." of wallet ".ColorsCLI::$FG_GREEN.$wallet_from.ColorsCLI::$FG_WHITE.PHP_EOL;
            $return_message .= "Please check that in the directory ".ColorsCLI::$FG_CYAN.Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.ColorsCLI::$FG_WHITE." there is the keystore of the wallet".PHP_EOL;
            return $return_message;
        }
    }

    public static function API_SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$tx_fee,$data,$isTestNet=false,$cli=true) {

        //Instance the pointer to the chaindata
        $chaindata = new DB();

        //Comprobamos si estamos sincronizados o no
        $lastBlockNum = BootstrapNode::GetLastBlockNum($chaindata,$isTestNet);
        $lastBlockNum_Local = $chaindata->GetNextBlockNum();

        if ($lastBlockNum != $lastBlockNum_Local)
            return "Error, Blockchain it is not synchronized";

        if (bccomp($amount ,"0.00000001",8) == -1)
            return "Error, Minium to send 0.00000001";

        if ($tx_fee == 3 && bccomp($amount ,"0.00001400",8) == -1)
            return "Error, There is not enough balance in the account";

        if ($tx_fee == 2 && bccomp($amount ,"0.00000900",8) == -1)
            return "Error, There is not enough balance in the account";

        if ($tx_fee == 1 && bccomp($amount ,"0.00000250",8) == -1)
            return "Error, There is not enough balance in the account";

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
            // Get current balance of wallet
            $currentBalance = self::GetBalance($wallet_from,$isTestNet);

            // If have balance
            if (bccomp($currentBalance,$amount,8) == 0 || bccomp($currentBalance,$amount,8) == 1) {
                if ($tx_fee == 3)
                    $amount = bcsub($amount,"0.00001400",8);
                else if ($tx_fee == 2)
                    $amount = bcsub($amount,"0.00000900",8);
                else if ($tx_fee == 1)
                    $amount = bcsub($amount,"0.00000250",8);

                //Make transaction and sign
                $transaction = new Transaction($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password,$tx_fee,$data);

                // Check if transaction is valid
                if ($transaction->isValid()) {

                    //Instance the pointer to the chaindata
                    $chaindata = new DB();

                    //We add the pending transaction to send into our chaindata
                    if ($chaindata->addPendingTransactionToSend($transaction->message(),$transaction)) {
                        return $transaction->message();
                    }
                    else {
                        return "Error, An error occurred while saving transaction to propagate";
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

}
?>