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

class SmartContract {

	/**
     * Make Smart Contracts of this block
     * All nodes create smart contracts on blockchain (local)
     *
     * @param DB $chaindata
     * @param Block $block
     */
    public static function Make(DB &$chaindata,Block $block) : void {

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Check if transaction is for make new contract
				if ($transaction->to == 'J4F00000000000000000000000000000000000000000000000000000000' && $transaction->data != "0x") {

					//Check OutOfGas
					$gasTxn = Gas::calculateGasTxn($chaindata,$transaction->to,$transaction->data);
					if ($transaction->gasLimit < $gasTxn) {
						continue;
					}

					//Make ContractHash
					$contractHash = PoW::hash($transaction->data.$transaction->from.$transaction->timestamp.$transaction->signature);

					if (!J4FVM_USE_SUBPROCESS) {
						self::_Make($chaindata,$contractHash,$transaction->hash,Tools::str2hex($transaction->from),$transaction->amount,$transaction->data);
					}
					else {
						$j4fvm_process = new J4FVMSubprocess('MAKE');

						//Set info for J4FVM
						$j4fvm_process->setContractHash($contractHash);
						$j4fvm_process->setTxnHash($transaction->hash);
						$j4fvm_process->setVersion(J4FVMTools::GetFunityVersion($transaction->data));
						$j4fvm_process->setFrom(Tools::str2hex($transaction->from));
						$j4fvm_process->setAmount($transaction->amount);
						$j4fvm_process->setData($transaction->data);

						//Run subprocess
						$j4fvm_process->run();
					}
				}
			}
		}
	}

	/**
	 * Make Smart Contracts of this transaction
	 * All nodes create smart contracts on blockchain (local)
	 *
	 * @param DB $chaindata
	 * @param string $contractHash
	 * @param string $txnHash
	 * @param string $txnFrom
	 * @param string $txnAmount
	 * @param string $txnData
	 */
	public static function _Make(DB &$chaindata,string $contractHash,string $txnHash,string $txnFrom,string $txnAmount,string $txnData) : void {

		//Parse txn::data (contract code) to string
		$contract_code = $txnData;
		$code = Tools::hex2str($contract_code);
		if (strlen($code) == 0)
			return;

		//Merge code into MXDity::MakeContract
		$code_parsed = J4FVM::_init($code);

		//Define sender Object
		js::define("msg",
			array(),
			array(
				"sender"=> Wallet::GetWalletAddressFromPubKey($txnFrom),
				"amount"=> $txnAmount,
			)
		);

		//Make funity defines
		self::MakeFunityDefines();

		#Contract status - Default not created
		$run_status = 0;

		//Check if parsedCode have COMPILER_ERROR
		if (strpos($code_parsed,'J4FVM_COMPILER_ERROR') === false) {
			try {

				//Set TXN that created contract
				J4FVM::$txn_hash = $txnHash;
				J4FVM::$contract_hash = $contractHash;

				//Run code
				ob_start();
				js::run($code_parsed,PoW::hash($contractHash.rand().time()));
				$output = ob_get_contents();
				ob_end_clean();

				//Contract status - OK
				$run_status = 1;

			} catch (Exception $e) {
				//Contract status - Error
				$run_status = -1;
			}
		}

		// If status contract its OK, save this contract
		if ($run_status == 1) {
			//Get data contract
			$contractData = Tools::str2hex(@json_encode(J4FVM::$data));

			//Add SmartContract on blockchain (local)
			$chaindata->addSmartContract($contractHash,$txnHash,$contract_code,$contractData);
		}
	}

	/**
	 * Call a function of Contracts
	 *
	 * @param DB $chaindata
	 * @param Block $block
	 */
	public static function CallFunction(DB &$chaindata, Block $block) : void {

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Txn to Contract
				if ((strlen($transaction->to) > 64) && $transaction->data != "0x") {

					$contract = $chaindata->GetContractByHash($transaction->to);

					if ($contract != null) {

						//Check OutOfGas
						$gasTxn = Gas::calculateGasTxn($chaindata,$transaction->to,$transaction->data);
						if ($transaction->gasLimit < $gasTxn) {
							continue;
						}

						if (!J4FVM_USE_SUBPROCESS) {
							self::_CallFunction($chaindata,$transaction->to,$transaction->hash,Tools::str2hex($transaction->from),$transaction->amount,$transaction->data);
						}
						else {
							$j4fvm_process = new J4FVMSubprocess('WRITE');

							//Set info for J4FVM
							$j4fvm_process->setContractHash($transaction->to);
							$j4fvm_process->setTxnHash($transaction->hash);
							$j4fvm_process->setVersion(J4FVMTools::GetFunityVersion($contract['code']));
							$j4fvm_process->setFrom(Tools::str2hex($transaction->from));
							$j4fvm_process->setAmount($transaction->amount);
							$j4fvm_process->setData($transaction->data);

							//Run contract
							$j4fvm_process->run();
						}
					}
				}
			}
		}
	}

	/**
	 * Call a function of Contracts
	 *
	 * @param DB $chaindata
	 * @param Array $contract
	 * @param string $txnHash
	 * @param string $txnFrom
	 * @param string $txnAmount
	 * @param string $txnData
	 */
	public static function _CallFunction(DB &$chaindata,array $contract,string $txnHash,string $txnFrom,string $txnAmount,string $txnData) : string {

		if (is_array($contract) && !empty($contract)) {

			//Parse txn::data (call code) to string
			$call_code = Tools::hex2str($txnData);
			if (strlen($call_code) == 0)
				return "";

			//Parse contract code to string
			$code_contract = Tools::hex2str($contract['code']);
			if (strlen($code_contract) == 0)
				return "";

			//Parse code Funity::Call_Contract
			$code_parsed = J4FVM::call($code_contract,$txnData);

			//Define msg sender Object
			js::define("msg",
				array(),
				array(
					"sender"=> Wallet::GetWalletAddressFromPubKey($txnFrom),
					"amount"=> $txnAmount,
				)
			);

			//Make funity defines
			self::MakeFunityDefines();

			//Contract Output
			$output = '';

			//Contract status - Default not created
			$run_status = 0;

			//Check if parsedCode have COMPILER_ERROR
			if (strpos($code_parsed,'J4FVM_COMPILER_ERROR') === false) {
				try {

					//Set TXN that call contract
					J4FVM::$txn_hash = $txnHash;
					J4FVM::$contract_hash = $contract['contract_hash'];

					//Set data of contract (last snapshot)
					$stateMachine = SmartContractStateMachine::store($contract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
					J4FVM::$data = @json_decode(Tools::hex2str($stateMachine->last()['state']),true);

					//Run code
					ob_start();
					js::run($code_parsed,PoW::hash($contract['contract_hash'].rand().time()));
					$output = ob_get_contents();
					ob_end_clean();

					//Contract status - OK
					$run_status = 1;

				} catch (Exception $e) {

					//Contract status - Error
					$run_status = -1;
				}
			}

			// If status contract its OK, update contract storedata
			if ($run_status == 1) {

				//Get data contract
				$contractData = Tools::str2hex(@json_encode(J4FVM::$data));

				//Update StoredData of Smart Contract on blockchain (local)
				$chaindata->updateStoredDataContract($contract['contract_hash'],$txnHash,$contractData);
			}

			return $output;

		}
	}

	/**
	 * Call a function of Contracts
	 *
	 * @param DB $chaindata
	 * @param array $contract
	 * @param string $callFunctionHex
	 *
	 * @return string
	 */
	public static function CallReadFunction(DB &$chaindata,array $contract,string $callFunctionHex) : string {

		if (empty($contract))
			return "Error, Contract null";

		//Parse txn::data (call code) to string
		$callCode = Tools::hex2str($callFunctionHex);
		if (strlen($callCode) == 0)
			return "Error, no function call";

		//Parse contract code to string
		$code_contract = Tools::hex2str($contract['code']);
		if (strlen($code_contract) == 0)
			return 'Error reading source code of Smart Contract';

		//Parse code Funity::Call_Contract
		$code_parsed = J4FVM::readCall($code_contract,$callFunctionHex,true);

		//Define msg sender Object
		js::define("msg",
			array(),
			array(
				"sender"=> '',
				"amount"=> '0',
			)
		);

		//Make funity defines
		self::MakeFunityDefines();

		//Contract Output
		$output = '';

		//Check if parsedCode have COMPILER_ERROR
		if (strpos($code_parsed,'J4FVM_COMPILER_ERROR') === false) {
			try {

				//Set TXN that call contract
				J4FVM::$txn_hash = null;
				J4FVM::$contract_hash = $contract['contract_hash'];

				//Set data of contract
				$stateMachine = SmartContractStateMachine::store($contract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
				J4FVM::$data = @json_decode(Tools::hex2str($stateMachine->last()['state']),true);

				//Run code
				ob_start();
				js::run($code_parsed,PoW::hash($contract['contract_hash'].rand().time()));
				$output = ob_get_contents();
				ob_end_clean();

			} catch (Exception $e) {
				$output = 'undefined';
			}
		}
		return Tools::str2hex($output);
	}

	/**
	 * Contract Function (withdraw)
     * Write Internal Transaction of contract
     *
     * @param string $txnHash
	 * @param string $contractHash
	 * @param string $receiver
     * @return bool
     */
	public static function Withdraw(string $txnHash,string $contractHash,string $receiver=null) : bool {

		if ($contractHash != null && strlen($contractHash) == 128) {

			//Check if have txn_hash for this Withdraw
			if ($txnHash != '' && strlen($txnHash) == 128) {

				//Instance DB
				$db = new DB();
				if ($db != null) {

					//Check if receiver is defined
					if ($receiver == null)
						$receiver = $db->GetOwnerContractByHash($contractHash);

					//Get contract balance
					$contractBalance = $db->GetWalletInfo($contractHash)['current'];

					//Check param formats
					$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
					if (@preg_match($REGEX_Address,$receiver) && @is_numeric($contractBalance) && @bccomp($contractBalance,0,18) == 1) {

						//write Internal Transaction on blockchain (local)
						$db->addInternalTransaction($txnHash,$contractHash,$contractHash,$receiver,$contractBalance);
						return true;
					}
				}

				$db->db->close();
			}
		}
		return false;
	}

	/**
     * Remove contract and all information and withdraw to owner
     *
	 * @param string $txnHash
	 * @param string $contractHash
	 * @param string $receiver
     */
	public static function Destruct(string $txnHash,string $contractHash,string $receiver=null) : void {
		//Instance DB
		$db = new DB();
		if ($db != null) {

			//Withdraw contract balance to contract owner or receiver
			self::Withdraw($txnHash,$contractHash,$receiver);

			//Remove contract and all states
			$db->removeSmartContract($contractHash);
		}
	}

	/**
     * Make Funity defines for contract
     */
	public static function MakeFunityDefines() : void {
		//Define blockchain Object
		js::define("blockchain",
			array(
				"Transfer" => "J4FVM::blockchain_transfer",
				"TransferToken" => "J4FVM::blockchain_transfer_token",
			),
			array()
		);

		//Define math Object
		js::define("math",
			array(
				"parse" => "J4FVM::math_parse",
				"random" => "J4FVM::math_random",
				"add" => "J4FVM::math_add",
				"sub" => "J4FVM::math_sub",
				"mul" => "J4FVM::math_mul",
				"div" => "J4FVM::math_div",
				"pow" => "J4FVM::math_pow",
				"mod" => "J4FVM::math_mod",
				"sqrt" => "J4FVM::math_sqrt",
				"powmod" => "J4FVM::math_powmod",
				"comp" => "J4FVM::math_compare",
			),
			array()
		);

		//Define uint256 math Object
		js::define("uint256",
			array(
				"parse" => "J4FVM::math_parse",
				"toDec" => "J4FVM::math_parse",
				"add" => "J4FVM::math_add",
				"sub" => "J4FVM::math_sub",
				"mul" => "J4FVM::math_mul",
				"div" => "J4FVM::math_div",
				"pow" => "J4FVM::math_pow",
				"mod" => "J4FVM::math_mod",
				"sqrt" => "J4FVM::math_sqrt",
				"powmod" => "J4FVM::math_powmod",
				"comp" => "J4FVM::math_compare",
			),
			//Propiedades
			array()
		);

		//Define contract Object
		js::define("contract",
			array(
				"get" => "J4FVM::js_get",
				"set" => "J4FVM::js_set",
				"table" => "J4FVM::js_table",
				"table_set" => "J4FVM::js_table_set",
				"table_get" => "J4FVM::js_table_get",
				"table_get_sub" => "J4FVM::js_table_get_sub",
				"table_uint256" => "J4FVM::js_table_uint256",
				"withdraw" => "J4FVM::blockchain_transferWithdraw",
			),
			array()
		);

		js::define("table",
			array(
				"count" => "J4FVM::table_count",
			),
			array()
		);

		js::define("self",
			array(
				"destruct" => "J4FVM::contract_destruct",
			),
			array()
		);

		js::define("utils",
			array(
				"sha3" => "J4FVM::js_sha3",
			),
			array()
		);

		js::define("JSON",
			array(
				"stringify" => "J4FVM::json_stringify",
				"parse" => "J4FVM::json_parse",
			),
			array()
		);

		js::define("External",
			array(
				"CallContract" => "J4FVM::external_callContract",
				"CheckIfExistsContract" => "J4FVM::external_existsContract",
			),
			array()
		);

	}
}
