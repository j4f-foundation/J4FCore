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

class J4FVMTools {

	/**
	 * Function that return funity version of contract
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function GetFunityVersion($code) {

		$isCodeEncrypted = strpos($code,'0x');
		if ($isCodeEncrypted !== false && $isCodeEncrypted == 0)
			$code = Tools::hex2str($code);

		$matches = [];
		preg_match("/#pragma funity ([0-9]{0,}\.[0-9]{0,}\.[0-9]{0,})/",$code,$matches);
		if (!empty($matches))
			return (isset($matches[0])) ? $matches[1]:'-1';
		else
			return '-1';
	}

	/**
     * Function that return contract name
     *
     * @param string $code
     *
     * @return string
     */
	public static function GetContractName($code) {
		$matches = [];
		preg_match("/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC10|is J4FRC20|)\s{0,}\{/",$code,$matches);
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
	public static function getTokenDefine($code) {

		$token = null;

		if (strpos($code,'define Token') !== false || strpos($code,'define Name') !== false) {

			$token = array(
				'Token'=>'',
				'Name'=>'',
				'TotalSupply'=>100,
				'Precision'=>8
			);

			//Check if have define Token
			$matches = [];
			preg_match("/[Dd]efine Token (.*)/",$code,$matches);
			if (count($matches) < 2) {
				return 'Error parsing Contract struct name';
			}
			$token['Token'] = $matches[1];

			//Check if have define Name
			$matches = [];
			preg_match("/[Dd]efine Name (.*)/",$code,$matches);
			if (count($matches) < 2) {
				return 'Error parsing Contract struct name';
			}
			$token['Name'] = $matches[1];

			//Check if have define totalSupply
			$matches = [];
			preg_match("/[Dd]efine TotalSupply (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['TotalSupply'] = $matches[1];
				if ($token['TotalSupply'] > 1000000000000000) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>TotalSupply</strong> max value: <strong>1000000000000000</strong>';
				}
				else if ($token['TotalSupply'] < 1) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>TotalSupply</strong> min value: <strong>1</strong>';
				}
			}

			//Check if have define Precision
			$matches = [];
			preg_match("/[Dd]efine Precision (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['Precision'] = $matches[1];
				if ($token['Precision'] > 18) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> max value: <strong>18</strong>';
				}
				if ($token['Precision'] < 0) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> min value: <strong>0</strong>';
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
	public static function isJ4FRC10Standard($code) {
		$matches = [];
		preg_match('/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC10|)\s{0,}\{/',$code,$matches);
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
	public static function isJ4FRC20Standard($code) {
		$matches = [];
		preg_match('/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC20|)\s{0,}\{/',$code,$matches);
		if (!empty($matches))
			if ($matches[2] == 'is J4FRC20')
				return true;
		return false;
	}

}
