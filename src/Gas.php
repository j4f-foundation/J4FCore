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

class Gas {

	/**
	 * Function that calc needed gas for a contract
	 *
	 * @param DB $chaindata
	 * @param string $contractHash
	 * @param string $txnData
	 *
	 * @return int
	 */
	public static function calculateGasTxn(DB &$chaindata, string $contractHash, string $txnData) : int {

		//Default, use 21000 of gas
		$totalGasUsed = 21000;

		//Check if this txn run a contract
		if (@strlen($contractHash) != 128 && $contractHash != "J4F00000000000000000000000000000000000000000000000000000000")
			return $totalGasUsed;

		//Check if have data
		if (@strlen($txnData) == 0 || $txnData == '0x')
			return $totalGasUsed;

		//Get contract
		$contractInfo = $chaindata->GetContractByHash($contractHash);

		//Check if have info about this contract
		if (@is_array($contractInfo) && !@empty($contractInfo)) {

			//Parse contractCode and callCode
			$code = Tools::hex2str($contractInfo['code']);
			$callCode = @json_decode(Tools::hex2str($txnData),true);

			//Check if callCode parsed its OK
			if (@is_array($callCode) && !@empty($callCode)) {

				//Get functionName from callCode
				$functionToCall = J4FVMTools::getFunctionFromMethod($code,$callCode['Method']);

				//if found this function
				if (@strlen($functionToCall) > 0) {

					//Get all functions from contract
					$functions = J4FVMTools::getFunctions($code,true);

					//Get gas from callFunction (Recursive)
					$totalGasUsed = self::GetGas($functionToCall,$functions,$totalGasUsed);
				}
			}
		}
		//New contract
		else {
			$code = $callCode = Tools::hex2str($txnData);
			$functions = J4FVMTools::getFunctions($code,true);
			$contractName = J4FVMTools::GetContractName($code);
			$totalGasUsed = self::GetGas($contractName,$functions,$totalGasUsed);
			$totalGasUsed *= 10;
			$totalGasUsed = intval(number_format($totalGasUsed,0,"",""));
		}

		return $totalGasUsed;
	}


	/**
	 * Function that calc needed gas for a function
	 *
	 * @param string $funcCalled
	 * @param array $functions
	 * @param int $totalGasUsed
	 *
	 * @return int
	 */
	private static function GetGas(string $funcCalled,array $functions,int $totalGasUsed) : int {

		$function = null;

		// Funcion publica
		if (@isset($functions['public'][$funcCalled])) {
			$function = $functions['public'][$funcCalled];
		}
		else if (@isset($functions['private'][$funcCalled])) {
			$function = $functions['private'][$funcCalled];
		}

		if ($function != null) {

			//Params
			$totalGasUsed += (@count($function['params']) * 500);

			//Get vars
			@preg_match_all('/\s{1,}get::/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 256);
			}

			//Set vars
			@preg_match_all('/\s{1,}set::/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 512);
			}

			//Get vars
			@preg_match_all('/contract\.(get|table)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 1200);
			}

			//Set vars
			@preg_match_all('/contract\.(set|table_set)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 2400);
			}

			//Maths
			@preg_match_all('/(math|uint256)\.(add|sub|mul|div|mod|pow|sqrt|powmod|comp|float|parse)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 4500);
			}

			//Random number
			@preg_match_all('/(math|uint256)\.(random)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 5500);
			}

			//Substr
			@preg_match_all('/substr\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 1200);
			}

			//Transfer/TransferToken/Withdraw
			@preg_match_all('/(blockchain|contract)\.(Transfer|TransferToken|withdraw)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 6750);
			}

			//Class
			@preg_match_all('/Class\s{0,}(.*)\s{0,}{/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 1500);
			}

			//Interface +150 gas
			@preg_match_all('/Interface\s{0,}(.*)\s{0,}{/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 45000);
			}

			//-- Recursive --
			//Every function call recursive
			foreach ($functions['public'] as $func=>$_) {
				@preg_match_all('/'.$func.'\(/',$function['code'],$matches);
				if (!@empty($matches) && !@empty($matches[0])) {
					$totalGasUsed += (@count($matches[0]) * 2500);
					$totalGasUsed = self::GetGas($func,$functions,$totalGasUsed);
				}
			}
			foreach ($functions['private'] as $func=>$_) {
				@preg_match_all('/'.$func.'\(/',$function['code'],$matches);
				if (!@empty($matches) && !@empty($matches[0])) {
					$totalGasUsed += (@count($matches[0]) * 2500);
					$totalGasUsed = self::GetGas($func,$functions,$totalGasUsed);
				}
			}
		}

		return $totalGasUsed;
	}
}
