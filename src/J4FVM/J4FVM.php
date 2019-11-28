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

class J4FVM extends J4FVMBase {

	/**
     * Function that parse code for init contract
     *
     * @param string $code
	 * @param bool $debug
     *
     * @return string
     */
	public static function _init(string $code, bool $debug=false) : string {

		self::$data = [];

		//Parse code
		$code_parsed = self::_parse($code,$debug);

		//Check if have Contract define struct
		$contractName = J4FVMTools::GetContractName($code);

		//Add init function to start Contract
		$code_parsed = $code_parsed . $contractName.'.'.$contractName.'();';

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return $code_parsed;
	}

	/**
     * Function that parse code and call contract
     *
     * @param string $code
	 * @param string $callCode
	 * @param bool $debug
     *
     * @return string
     */
	public static function call(string $code,string $callCode,bool $debug=false) : string {

		//Decrypt/parse call code
		$callCode = @json_decode(Tools::hex2str($callCode),true);

		//Parse code
		$code_parsed = self::_parse($code,$debug);

		if (is_array($callCode)) {
			//Check if have Contract define struct
			$contractName = J4FVMTools::GetContractName($code);
			if (strlen($contractName) > 0) {
				if (isset($callCode['Method'])) {
					if (self::canCallThisFunction($code,$callCode['Method'])) {

						$function = J4FVMTools::getFunctionFromMethod($code,$callCode['Method']);
						if (strlen($function) > 0) {

							//Set params
							$param = '';
							if (count($callCode['Params']) > 0) {
								foreach ($callCode['Params'] as $p) {
									if (strlen($param) > 0) $param .= ',';
									$param .= "'".str_replace("'","`",Tools::hex2str($p))."'";
								}
							}

							//Set run function of contract
							$code_parsed .= $contractName.'.'.$function.'('.$param.');';
						}
					}
				}
			}
		}
		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return $code_parsed;
	}

	/**
     * Function that parse code and call contract
     *
     * @param string $code
	 * @param string $callCode
	 * @param bool $debug
     *
     * @return string
     */
	public static function readCall(string $code,string $callCode,bool $debug=false) : string {

		//Decrypt/parse call code
		$callCode = @json_decode(Tools::hex2str($callCode),true);

		//Parse code
		$code_parsed = self::_parse($code,$debug);

		if (is_array($callCode)) {
			//Check if have Contract define struct
			$contractName = J4FVMTools::GetContractName($code);
			if (strlen($contractName) > 0) {
				if (isset($callCode['Method'])) {
					if (self::canCallThisFunction($code,$callCode['Method'])) {

						$function = J4FVMTools::getFunctionFromMethod($code,$callCode['Method']);
						if (strlen($function) > 0) {

							//Set params
							$param = '';
							if (count($callCode['Params']) > 0) {
								foreach ($callCode['Params'] as $p) {
									if (strlen($param) > 0) $param .= ',';
									$param .= "'".str_replace("'","`",Tools::hex2str($p))."'";
								}
							}

							//Set run function of contract
							$code_parsed .= $contractName.'.'.$function.'('.$param.');';
						}
					}
				}
			}
		}
		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return $code_parsed;
	}

	/**
     * Function that check if can call a function contract
     *
     * @param string $code
	 * @param string $functionToCall
     *
     * @return bool
     */
	public static function canCallThisFunction(string $code,string $functionToCall) : bool {
		$functions = J4FVMTools::getFunctions($code,false);
		if (!empty($functions['public'])) {
			foreach ($functions['public'] as $function=>$params) {
				if ('0x'.substr(PoW::hash(trim($function)),0,8) == $functionToCall) {
					return true;
				}
			}
		}
		return false;
	}

	/**
     * Function that check required functions for J4FRC-10 Standard Token
     *
     * @param string $code
	 *
	 * @return string
     */
	public static function CheckJ4FRC10Standard(string $code) : string {

		$return = '';

		//balanceOf(address)
		$matches = [];
		preg_match_all('/balanceOf:\s*function\(\s*address\s*(.*)\)\s*public\s*returns\s*uint256/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>balanceOf(address) public returns uint256</strong> Required for <strong>J4FRC-10 Token</strong>");';

		//transfer(address,uint256)
		$matches = [];
		//preg_match_all('/transferFrom:\s*function\((.*),(.*),(.*)\)\s*public/',$code,$matches);
		preg_match_all('/transfer:\s*function\(\s*address\s(.*)\s*,\s*uint256\s*(.*)\)\s*public/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>transfer(address,uint256)</strong> Required for <strong>J4FRC-10 Token</strong>");';

		//transferFrom(address,uint256)
		$matches = [];
		preg_match_all('/transferFrom:\s*function\(\s*address\s*(.*),\s*address\s*(.*),\s*uint256\s*(.*)\)\s*public/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>transferFrom(address,address,uint256)</strong> Required for <strong>J4FRC-10 Token</strong>");';

		return $return;
	}

	/**
	 * Function that check required functions for J4FRC-20 Standard Token
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function CheckJ4FRC20Standard(string $code) : string {

		$return = '';

		//inventoryOf(address)
		$matches = [];
		preg_match_all('/inventoryOf:\s*function\(\s*address\s*(.*)\)\s*public\s*returns\s*string/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>inventoryOf(address) public returns array</strong> Required for <strong>J4FRC-20 Token</strong><br>");';

		//transferToken(address,tokenId)
		$matches = [];
		preg_match_all('/transferToken:\s*function\(\s*address\s(.*)\s*,\s*tokenId\s*(.*)\)\s*public/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>transferToken(address,tokenId)</strong> Required for <strong>J4FRC-20 Token</strong><br>");';

		//transferTokenFrom(address,address,tokenId)
		$matches = [];
		preg_match_all('/transferTokenFrom:\s*function\(\s*address\s*(.*),\s*address\s*(.*),\s*tokenId\s*(.*)\)\s*public/',$code,$matches);
		if (empty($matches[0]))
			$return .= 'print("<strong class=\"text-danger\">J4FVM_COMPILER_ERROR</strong> Function <strong>transferTokenFrom(address,address,tokenId)</strong> Required for <strong>J4FRC-20 Token</strong><br>");';

		return $return;
	}
}
?>
