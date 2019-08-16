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
	public static function calculateGasTxn(&$chaindata, $contractHash, $txnData) {

		//Default, use 21000 of gas
		$totalGasUsed = 21000;

		//Check if this txn run a contract
		if (@strlen($contractHash) == 128) {

			//Check if have data
			if (@strlen($txnData) > 0 && $txnData != '0x') {
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
			}
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
	private static function GetGas($funcCalled,$functions,$totalGasUsed) {

		$function = null;

		// Funcion publica
		if (@isset($functions['public'][$funcCalled])) {
			$function = $functions['public'][$funcCalled];
		}
		else if (@isset($functions['private'][$funcCalled])) {
			$function = $functions['private'][$funcCalled];
		}

		if ($function != null) {

			//Every param +50 gas
			$totalGasUsed += (@count($function['params']) * 50);

			//Every get/set +120 gas
			@preg_match_all('/contract\.(get|table)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 120);
			}

			//Every table get/set +240 gas
			@preg_match_all('/contract\.(set|table_set)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 240);
			}

			//Every math|uint256.X +450 gas
			@preg_match_all('/(math|uint256)\.(add|sub|mul|div|mod|pow|sqrt|powmod|comp|float)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 450);
			}

			//Every math.random +550 gas
			@preg_match_all('/(math|uint256)\.(random)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 550);
			}

			//Every substr +120 gas
			@preg_match_all('/substr\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 120);
			}

			//Every math +675 gas
			@preg_match_all('/(blockchain|contract)\.(Transfer|TransferToken|withdraw)\(/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 675);
			}

			//Every Class +150 gas
			@preg_match_all('/Class\s{0,}(.*)\s{0,}{/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 150);
			}

			//Every Class +150 gas
			@preg_match_all('/Interface\s{0,}(.*)\s{0,}{/',$function['code'],$matches);
			if (!@empty($matches) && !@empty($matches[0])) {
				$totalGasUsed += (@count($matches[0]) * 4500);
			}

			//-- Recursive --
			//Every function call recursive +250 gas
			foreach ($functions['public'] as $func=>$_) {
				@preg_match_all('/'.$func.'\(/',$function['code'],$matches);
				if (!@empty($matches) && !@empty($matches[0])) {
					$totalGasUsed += (@count($matches[0]) * 250);
					$totalGasUsed = self::GetGas($func,$functions,$totalGasUsed);
				}
			}
			foreach ($functions['private'] as $func=>$_) {
				@preg_match_all('/'.$func.'\(/',$function['code'],$matches);
				if (!@empty($matches) && !@empty($matches[0])) {
					$totalGasUsed += (@count($matches[0]) * 250);
					$totalGasUsed = self::GetGas($func,$functions,$totalGasUsed);
				}
			}
		}

		return $totalGasUsed;
	}
}
