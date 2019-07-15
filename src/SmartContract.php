<?php
// MIT License
//
// Copyright (c) 2019 Just4Fun
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

class SmartContract {

	/**
     * Make Smart Contracts of this block
     * All nodes create smart contracts on blockchain (local)
     *
     * @param DB $chaindata
     * @param Block $block
	 *
     * @return bool
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

					//Display::_printer($code_parsed);

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

					js::define("math",
						//Funciones de php ejecutadas desde JS
						array(
							"parse" => "J4FVM::math_parse",
							"toDec" => "J4FVM::math_toDec",
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

					//Get Contract Hash
					$contractHash = PoW::hash($contract_code.$transaction->from.$transaction->timestamp.$transaction->signature);

					#Contract status - Default not created
					$run_status = 0;

					try {

						//Set TXN that created contract
						J4FVM::$txn_hash = $transaction->hash;
						J4FVM::$contract_hash = $contractHash;

						//Run code
						js::run($code_parsed,$contractHash);

						//Contract status - OK
						$run_status = 1;

					} catch (Exception $e) {

						var_dump($e->getMessage());

						//Contract status - Error
						$run_status = -1;
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
	 * @return bool
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
						//Display::_printer('CONTRACT HASH: ' . $contract->contract_hash);

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

						js::define("math",
							//Funciones de php ejecutadas desde JS
							array(
								"parse" => "J4FVM::math_parse",
								"toDec" => "J4FVM::math_toDec",
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

						//Contract status - Default not created
						$run_status = 0;

						try {

							//Set TXN that call contract
							J4FVM::$txn_hash = $transaction->hash;
							J4FVM::$contract_hash = $contract['contract_hash'];

							//Set data of contract
							J4FVM::$data = @json_decode(Tools::hex2str($contract['data']),true);

							//Run code
							js::run($code_parsed,$contract['contract_hash']);

							//Contract status - OK
							$run_status = 1;

						} catch (Exception $e) {

							//Contract status - Error
							$run_status = -1;
						}

						// If status contract its OK, update contract storedata
						if ($run_status == 1) {

							//Get data contract
							$contractData = Tools::str2hex(@json_encode(J4FVM::$data));

							//Update StoredData of Smart Contract on blockchain (local)
							$chaindata->updateStoredDataContract($contract['contract_hash'],$contractData);
						}

					}
				}
			}
		}
	}

}
