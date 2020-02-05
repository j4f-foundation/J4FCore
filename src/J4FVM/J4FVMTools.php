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

class J4FVMTools {

	/**
	 * Function that return funity version of contract
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function GetFunityVersion(string $code) : string {

		$isCodeEncrypted = strpos($code,'0x');
		if ($isCodeEncrypted !== false && $isCodeEncrypted == 0)
			$code = Tools::hex2str($code);

		$matches = [];
		preg_match(REGEX::FunityVersion,$code,$matches);
		if (!empty($matches))
			return (isset($matches[0])) ? $matches[1]:'0.0.1';
		else
			return '0.0.1';
	}

	/**
     * Function that return contract name
     *
     * @param string $code
     *
     * @return string
     */
	public static function GetContractName(string $code) : string {
		$matches = [];
		preg_match(REGEX::ContractName,$code,$matches);
		if (!empty($matches))
			return (isset($matches[0])) ? $matches[1]:'';
		else
			return '';
	}

	/**
     * Function that get token info from code
     *
     * @param string $code
	 *
	 * @return array
     */
	public static function getTokenDefine(string $code) : array {

		$token = [];

		if (strpos($code,'define Token') !== false || strpos($code,'define Name') !== false) {

			$token = array(
				'Token'=>'',
				'Name'=>'',
				'TotalSupply'=>100,
				'Precision'=>8
			);

			//Check if have define Token
			$matches = [];
			preg_match(REGEX::DefineToken,$code,$matches);
			if (count($matches) < 2) {
				return ['error' => 'Error parsing Contract struct name'];
			}
			$token['Token'] = $matches[1];

			//Check if have define Name
			$matches = [];
			preg_match(REGEX::DefineName,$code,$matches);
			if (count($matches) < 2) {
				return ['error' => 'Error parsing Contract struct name'];
			}
			$token['Name'] = $matches[1];

			//Check if have define totalSupply
			$matches = [];
			preg_match(REGEX::DefineSupply,$code,$matches);
			if (count($matches) >= 2) {
				$token['TotalSupply'] = $matches[1];
				if ($token['TotalSupply'] > 1000000000000000) {
					return ['error' => '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>TotalSupply</strong> max value: <strong>1000000000000000</strong>'];
				}
				else if ($token['TotalSupply'] < 1) {
					return ['error' => '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>TotalSupply</strong> min value: <strong>1</strong>'];
				}
			}

			//Check if have define Precision
			$matches = [];
			preg_match(REGEX::DefinePrecision,$code,$matches);
			if (count($matches) >= 2) {
				$token['Precision'] = $matches[1];
				if ($token['Precision'] > 18) {
					return ['error' => '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> max value: <strong>18</strong>'];
				}
				if ($token['Precision'] < 0) {
					return ['error' => '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> min value: <strong>0</strong>'];
				}
			}
		}

		if (J4FVMTools::isJ4FRC20Standard($code)) {
			$token['TotalSupply'] = '~';
			$token['Precision'] = 0;
		}

		return $token;
	}

	/**
	 * Function that check required functions for J4FRC-10 Standard Token
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public static function isJ4FRC10Standard(string $code) : bool {
		$matches = [];
		preg_match(REGEX::isJ4FRC10,$code,$matches);
		if (!empty($matches))
			if (trim($matches[2]) == 'is J4FRC10')
				return true;
		return false;
	}

	/**
	 * Function that check required functions for J4FRC-20 Standard Token
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public static function isJ4FRC20Standard(string $code) : bool {
		$matches = [];
		preg_match(REGEX::isJ4FRC20,$code,$matches);
		if (!empty($matches))
			if ($matches[2] == 'is J4FRC20')
				return true;
		return false;
	}

	/**
	 * Function that return function name from MethodId
	 *
	 * @param string $code
	 * @param string $functionToCall
	 *
	 * @return string
	 */
	public static function getFunctionFromMethod(string $code, string $functionToCall) : string {
		$functions = self::getFunctions($code,false);
		if (!empty($functions['public'])) {
			foreach ($functions['public'] as $function=>$params) {
				if ('0x'.substr(PoW::hash(trim($function)),0,8) == $functionToCall) {
					return $function;
				}
			}
		}
		return "";
	}

	/**
	 * Function that get functions of contract
	 *
	 * @param string $code
	 *
	 * @return array
	 */
	public static function getFunctions(string $code,bool $withCode=false) : array {

		$functions = array(
			'public' => array(),
			'private' => array(),
		);

		//Parse normal functions
		$matches = array();
		preg_match_all(REGEX::ContractFunctions,$code,$matches);
		if (!empty($matches[1])) {
			$i = 0;
			foreach ($matches[1] as $match) {
				$funcName = $matches[1][$i];
				$funcType = 'private';
				if (trim($matches[3][$i]) == 'public')
					$funcType = 'public';

				//Strip params if have
				$parameters = array();
				$e_params = array($matches[2][$i]);
				if (strpos($matches[2][$i],',') !== false)
					$e_params = explode(',',$matches[2][$i]);

				foreach ($e_params as $param) {
					$strip_param = explode(' ',trim($param));
					if (count($strip_param) == 2) {
						$parameters[$strip_param[1]] = $strip_param[0];
					}
				}

				$functions[$funcType][$funcName]['params'] = $parameters;
				$functions[$funcType][$funcName]['return'] = $matches[4][$i];
				if ($withCode)
					$functions[$funcType][$funcName]['code'] = $matches[5][$i];

				$i++;
			}
		}

		return $functions;
	}

}
