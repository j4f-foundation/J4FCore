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

class SmartContract {

	/**
     * Make Smart Contracts of this block
     * All nodes create smart contracts on blockchain (local)
     *
     * @param DB $chaindata
     * @param Block $block
     */
    public static function Make(&$chaindata,$block) {

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Check if transaction is for make new contract
				if ($transaction->to == 'J4F00000000000000000000000000000000000000000000000000000000' && $transaction->data != "0x") {

					//Parse txn::data (contract code) to string
					$contract_code = $transaction->data;
					$code = Tools::hex2str($contract_code);
					if (strlen($code) == 0)
						return;

					//Merge code into MXDity::MakeContract
					$code_parsed = J4FVM::_init($code);

					//Define sender Object
					js::define("msg",
						array(),
						array(
							"sender"=> Wallet::GetWalletAddressFromPubKey($transaction->from),
							"amount"=> $transaction->amount,
						)
					);

					//Define blockchain Object
					js::define("blockchain",
						array(
							"Transfer" => "J4FVM::blockchain_transfer",
						),
						array()
					);

					//Define math Object
					js::define("math",
						array(
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
							"toDec" => "J4FVMJ4FVM::math_parse",
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
						),
						array()
					);

					js::define("table",
						array(
							"count" => "J4FVM::table_count",
						),
						array()
					);

					//Get Contract Hash
					$contractHash = PoW::hash($contract_code.$transaction->from.$transaction->timestamp.$transaction->signature);

					#Contract status - Default not created
					$run_status = 0;

					//Check if parsedCode have COMPILER_ERROR
					if (strpos($code_parsed,'J4FVM_COMPILER_ERROR') === false) {
						try {

							//Set TXN that created contract
							J4FVM::$txn_hash = $transaction->hash;
							J4FVM::$contract_hash = $contractHash;

							//Run code
							ob_start();
							js::run($code_parsed,$contractHash);
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
						$chaindata->addSmartContract($contractHash,$transaction->hash,$contract_code,$contractData);
					}
				}
			}
		}
	}

	/**
	 * Call a function of Contracts
	 *
	 * @param DB $chaindata
	 * @param Block $lastBlock
	 * @param Block $blockMinedByPeer
	 */
	public static function CallFunction(&$chaindata,$block) {

		//Obteemos todas las transacciones del bloque
		//Si alguna transaccion va dirigida a 00000

		foreach ($block->transactions as $transaction) {

			//Check if transaction is valid
			if ($transaction->isValid()) {

				//Txn to Contract
				if ((strlen($transaction->to) > 64) && $transaction->data != "0x") {
					$contract = $chaindata->GetContractByHash($transaction->to);
					if ($contract != null) {

						//Parse txn::data (call code) to string
						$call_code_hex = $transaction->data;
						$call_code = Tools::hex2str($call_code_hex);
						if (strlen($call_code) == 0)
							return;

						//Parse CALL Code
						$code_call_info = J4FVM::_parseCall($call_code);

						//Parse contract code to string
						$code_contract = Tools::hex2str($contract['code']);
						if (strlen($code_contract) == 0)
							return;

						//Parse code Funity::Call_Contract
						$code_parsed = J4FVM::call($code_contract,$code_call_info['func'],$code_call_info['func_params']);

						//Define msg sender Object
						js::define("msg",
							array(),
							array(
								"sender"=> Wallet::GetWalletAddressFromPubKey($transaction->from),
								"amount"=> $transaction->amount,
							)
						);

						//Define blockchain Object
						js::define("blockchain",
							array(
								"Transfer" => "J4FVM::blockchain_transfer",
							),
							array()
						);

						//Define math Object
						js::define("math",
							array(
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
								"toDec" => "J4FVMJ4FVM::math_parse",
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
							),
							array()
						);

						js::define("table",
							array(
								"count" => "J4FVM::table_count",
							),
							array()
						);

						//Contract status - Default not created
						$run_status = 0;

						//Check if parsedCode have COMPILER_ERROR
						if (strpos($code_parsed,'J4FVM_COMPILER_ERROR') === false) {
							try {

								//Set TXN that call contract
								J4FVM::$txn_hash = $transaction->hash;
								J4FVM::$contract_hash = $contract['contract_hash'];

								//Set data of contract (last snapshot)
								$stateMachine = SmartContractStateMachine::store($contract['contract_hash'],Tools::GetBaseDir().'data'.DIRECTORY_SEPARATOR.'db');
								J4FVM::$data = @json_decode(Tools::hex2str($stateMachine->last()['state']),true);

								//Run code
								ob_start();
								js::run($code_parsed,$contract['contract_hash'].rand());
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
							$chaindata->updateStoredDataContract($contract['contract_hash'],$transaction->hash,$contractData);
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
	 * @param array $contract
	 *
	 * @return string
	 */
	public static function CallReadFunction(&$chaindata,$contract,$callFunctionHex) {

		//Obteemos todas las transacciones del bloque
		//Si alguna transaccion va dirigida a 00000
		if ($contract != null) {
			//Display::_printer('CONTRACT HASH: ' . $contract->contract_hash);

			//Parse txn::data (call code) to string
			$callCode = Tools::hex2str($callFunctionHex);
			if (strlen($callCode) == 0)
				return;

			//Parse CALL Code
			$callInfo = J4FVM::_parseCall($callCode);

			//Parse contract code to string
			$code_contract = Tools::hex2str($contract['code']);
			if (strlen($code_contract) == 0)
				return 'Error reading source code of Smart Contract';

			//Parse code Funity::Call_Contract
			$code_parsed = J4FVM::readCall($code_contract,$callInfo['func'],$callInfo['func_params'],true);

			//Define msg sender Object
			js::define("msg",
				array(),
				array(
					"sender"=> '',
					"amount"=> '0',
				)
			);

			//Define blockchain Object
			js::define("blockchain",
				array(
					"Transfer" => "J4FVM::blockchain_transfer_compiler",
				),
				array()
			);

			//Define math Object
			js::define("math",
				array(
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
					"parse" => "J4FVM::uint256_parse",
					"toDec" => "J4FVM::uint256_toDec",
					"add" => "J4FVM::uint256_add",
					"sub" => "J4FVM::uint256_sub",
					"mul" => "J4FVM::uint256_mul",
					"div" => "J4FVM::uint256_div",
					"pow" => "J4FVM::uint256_pow",
					"mod" => "J4FVM::uint256_mod",
					"sqrt" => "J4FVM::uint256_sqrt",
					"powmod" => "J4FVM::uint256_powmod",
					"comp" => "J4FVM::uint256_compare",
				),
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
				),
				array()
			);

			js::define("table",
				array(
					"count" => "J4FVM::table_count",
				),
				array()
			);

			//Contract status - Default not created
			$output = '';

			//echo $code_parsed;

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
					js::run($code_parsed,PoW::hash($contract['contract_hash'].rand()));
					$output = ob_get_contents();
					ob_end_clean();

				} catch (Exception $e) {

					$output = $code_parsed;
				}
			}

			//echo '<pre>'.print_r($code_parsed,true).'</pre>';


			return Tools::str2hex($output);
		}
	}

}
