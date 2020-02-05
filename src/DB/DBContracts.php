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

class DBContracts extends DBTransactions {

	/**
     * Add a Smart Contract in the chaindata
     *
     * @param string $contractHash
     * @param string $txn_hash
	 * @param string $codeHexBytes
	 * @param string $dataHexBytes
     * @return bool
     */
    public function addSmartContract(string $contractHash,string $txn_hash,string $codeHexBytes,string $dataHexBytes) : bool {

        $error = false;

        $info_contract_chaindata = $this->db->query("SELECT contract_hash FROM smart_contracts WHERE contract_hash = '".$contractHash."';")->fetch_assoc();
        if (empty($info_contract_chaindata)) {

            //Start MySQL Transaction
            $this->db->begin_transaction();

            //SQL Insert Contract
            $sqlInsertContract = "INSERT INTO smart_contracts (contract_hash,txn_hash,code)
            VALUES ('".$contractHash."','".$txn_hash."','".$codeHexBytes."');";

            //Add contract into blockchain
            if (!$this->db->query($sqlInsertContract)) {
                $error = true;
            }
        }

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, contract added
        else {
            $this->db->commit();

			//Save Contract State
			$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
			$stateMachine->insert($txn_hash,["state" => $dataHexBytes]);
            return true;
        }
	}

	/**
     * Remove a Smart Contract from chaindata
     *
     * @param string $contractHash
     * @return bool
     */
	public function removeSmartContract(string $contractHash) : bool {
		$error = false;

        $info_contract_chaindata = $this->db->query("SELECT contract_hash FROM smart_contracts WHERE contract_hash = '".$contractHash."';")->fetch_assoc();
        if (!empty($info_contract_chaindata)) {

            //Start MySQL Transaction
            $this->db->begin_transaction();

            //SQL Remove Contract
            $sqlRemoveContract = "DELETE FROM smart_contracts WHERE contract_hash = '".$contractHash."';";

            //Remove contract from blockchain
            if (!$this->db->query($sqlRemoveContract)) {
                $error = true;
            }
        }

        //If have error, rollback action
        if ($error) {
            $this->db->rollback();
            return false;
        }

        //No errors, contract added
        else {
            $this->db->commit();

			//Remove Contract States
			$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
			$stateMachine->deleteStates();
            return true;
        }
	}

	/**
     * Update storedData of Contract in StateMachine
     *
     * @param string $contractHash
	 * @param string $txnHash
	 * @param string $dataHexBytes
     * @return bool
     */
    public function updateStoredDataContract(string $contractHash,string $txnHash,string $dataHexBytes) : bool {
		//NoSQL Update storedData of Contract
		$stateMachine = SmartContractStateMachine::store($contractHash,Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
		$stateMachine->insert($txnHash,["state" => $dataHexBytes]);
		return true;
	}

    /**
     * Returns a contract given a transaction hash
     *
     * @param string $txn_hash
     * @return array
     */
    public function GetContractByTxn(string $txn_hash) : array {

        $sql = "SELECT * FROM smart_contracts WHERE txn_hash = '".$txn_hash."';";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract;
        }
        return [];
	}

    /**
     * Returns a contract given a hash
     *
     * @param string $txn_hash
     * @return array
     */
    public function GetContractByHash(string $contract_hash) : array {

        $sql = "SELECT * FROM smart_contracts WHERE contract_hash = '".$contract_hash."';";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract;
        }
        return [];
	}

	/**
     * Returns a owner of contract given a hash
     *
     * @param string $txn_hash
     * @return string
     */
    public function GetOwnerContractByHash(string $contract_hash) : string {

        $sql = "
		SELECT t.wallet_from
		FROM smart_contracts as sC
		LEFT JOIN transactions AS t ON t.txn_hash = sC.txn_hash
		WHERE contract_hash = '".$contract_hash."';
		";
        $info_contract = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_contract)) {
            return $info_contract['wallet_from'];
        }
        return "";
	}

    /**
     * Save Internal TXN of SmartContract J4FRC10 in Blockchain
     *
     * @param string $txn_hash
	 * @param string $contract_hash
	 * @param string $wallet_from
	 * @param string $wallet_to
	 * @param float $amount
     * @return bool
     */
	public function addInternalTransaction(string $txn_hash,string $contract_hash,string $wallet_from,string $wallet_to,float $amount) : bool {

		$error = false;

		//Start Internal Transaction
		$this->db->begin_transaction();

		$timestamp = Tools::GetGlobalTime();
		$sqlInternalTxn = "INSERT INTO smart_contracts_txn (txn_hash, contract_hash, wallet_from, wallet_to, amount, timestamp)
			VALUES ('" . $txn_hash . "','" . $contract_hash . "','" . $wallet_from . "','" . $wallet_to . "','".$amount."','".$timestamp."');";

		//Commit Internal Transaction
		if (!$this->db->query($sqlInternalTxn)) {
			$error = true;
		}

		//Update Account FROM
		if (!$error) {

			//Token TXN
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (preg_match($REGEX_Address,$wallet_from)) {
				if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
					$sql_updateAccountFrom = "
					INSERT INTO accounts_j4frc10 (hash,contract_hash,sended,received)
					VALUES ('".$wallet_from."','".$contract_hash."','".$amount."',0)
					ON DUPLICATE KEY UPDATE sended = sended + VALUES(sended);
					";
					if (!$this->db->query($sql_updateAccountFrom)) {
						$error = true;
					}
				}
				//Update Account TO
				if (strlen($wallet_to) > 0) {
					$sql_updateAccountTo = "
					INSERT INTO accounts_j4frc10 (hash,contract_hash,sended,received)
					VALUES ('".$wallet_to."','".$contract_hash."',0,'".$amount."')
					ON DUPLICATE KEY UPDATE received = received + VALUES(received);
					";

					if (!$this->db->query($sql_updateAccountTo)) {
						$error = true;
					}
				}
			}
			//J4F Txn (Withdraw from contract)
			else {
				//Update Account FROM
				if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
					$sql_updateAccountFrom = "
					INSERT INTO accounts (hash,sended,received,mined)
					VALUES ('".$wallet_from."','".$amount."',0,0)
					ON DUPLICATE KEY UPDATE sended = sended + ".$amount.";
					";
					if (!$this->db->query($sql_updateAccountFrom)) {
						$error = true;
					}
				}
				//Update Account TO
				if (strlen($wallet_to) > 0) {
					$sql_updateAccountTo = "
					INSERT INTO accounts (hash,sended,received,mined)
					VALUES ('".$wallet_to."',0,'".$amount."',0)
					ON DUPLICATE KEY UPDATE received = received + VALUES(received);
					";

					if (!$this->db->query($sql_updateAccountTo)) {
						$error = true;
					}
				}
			}
		}


		//Commit Internal Transaction
		if (!$error) {
			$this->db->commit();
			return true;
		}

		//Rollback Internal Transaction
		else
			$this->db->rollback();

        return false;
	}

	//addInternalTransactionToken
	/**
     * Save Internal TXN of SmartContract J4FRC20 in Blockchain
     *
     * @param string $txn_hash
	 * @param string $contract_hash
	 * @param string $wallet_from
	 * @param string $wallet_to
	 * @param int $tokenId
     * @return bool
     */
	public function addInternalTransactionToken(string $txn_hash,string $contract_hash,string $wallet_from,string $wallet_to,int $tokenId) : bool {

		$error = false;

		//Start Internal Transaction
		$this->db->begin_transaction();

		$timestamp = Tools::GetGlobalTime();
		$sqlInternalTxn = "INSERT INTO smart_contracts_txn_token (txn_hash, contract_hash, wallet_from, wallet_to, tokenId, timestamp)
			VALUES ('" . $txn_hash . "','" . $contract_hash . "','" . $wallet_from . "','" . $wallet_to . "','".$tokenId."','".$timestamp."');";

		//Commit Internal Transaction
		if (!$this->db->query($sqlInternalTxn)) {
			$error = true;
		}

		//Update Account FROM
		if (!$error) {

			//Token TXN
			$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
			if (preg_match($REGEX_Address,$wallet_from)) {
				if (strlen($wallet_from) > 0 && $wallet_from != 'J4F00000000000000000000000000000000000000000000000000000000') {
					$sql_updateAccountFrom = "
					DELETE FROM accounts_j4frc20 WHERE hash = '".$wallet_from."' AND contract_hash = '".$contract_hash."' AND tokenId = '".$tokenId."';
					";
					if (!$this->db->query($sql_updateAccountFrom)) {
						$error = true;
					}
				}
				//Update Account TO
				if (strlen($wallet_to) > 0) {
					$sql_updateAccountTo = "
					INSERT INTO accounts_j4frc20 (hash,contract_hash,tokenId) VALUES ('".$wallet_to."','".$contract_hash."','".$tokenId."');
					";

					if (!$this->db->query($sql_updateAccountTo)) {
						$error = true;
					}
				}
			}
		}


		//Commit Internal Transaction
		if (!$error) {
			$this->db->commit();
			return true;
		}

		//Rollback Internal Transaction
		else
			$this->db->rollback();

        return false;
	}

	/**
     * Returns a internal transaction given a txn hash and contract hash
     *
	 * @param string $contract_hash
     * @param string $txn_hash
     * @return mixed
     */
    public function GetInternalTransactionByTxnHash(string $contract_hash,string $txn_hash) : array {

        $sql = "SELECT * FROM smart_contracts_txn WHERE txn_hash = '".$txn_hash."' AND contract_hash = '".$contract_hash."';";
        $info_internalTxn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_internalTxn)) {
            return $info_internalTxn;
        }
        return [];
	}

	/**
     * Returns a internal transaction token given a txn hash and contract hash
     *
	 * @param string $contract_hash
     * @param string $txn_hash
     * @return mixed
     */
    public function GetInternalTransactionTokenByTxnHash(string $contract_hash,string $txn_hash) : array {

        $sql = "SELECT * FROM smart_contracts_txn_token WHERE txn_hash = '".$txn_hash."' AND contract_hash = '".$contract_hash."';";
        $info_internalTxn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_internalTxn)) {
            return $info_internalTxn;
        }
        return [];
	}
}

?>
