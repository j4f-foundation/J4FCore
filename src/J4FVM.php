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

class J4FVM extends J4FVMBase {

	/**
     * Function that parse code for init contract
     *
     * @param string $code
     *
     * @return string
     */
	public static function _init($code,$debug=false) {

		self::$data = [];

		//Parse code
		$code_parsed = self::_parse($code,$debug);

		//Check if have Contract define struct
		$contractName = self::GetContractName($code);

		//Add init function to start Contract
		$code_parsed = $code_parsed . $contractName.'.'.$contractName.'();';

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return $code_parsed;
	}

	/**
     * Function that parse code and call contract
     *
     * @param string $code
	 * @param string $function
	 * @param array $params
	 * @param bool $debug
     *
     * @return string
     */
	public static function call($code,$function,$params=array(),$debug=false) {

		//Parse code
		$code_parsed = self::_parse($code);

		//Check if have Contract define struct
		$contractName = self::GetContractName($code);

		if (J4FVM::canCallThisFunction($code,$function)) {
			//Add all params
			$param = '';
			foreach ($params as $p) {
				if (strlen($param) > 0) $param .= ',';
				$param .= "'".$p."'";
			}

			if ($debug) {
				if (isset($_SESSION['compiler_data'])) {
					J4FVM::$data = $_SESSION['compiler_data'];
				}
				//Add run function of contract
				$code_parsed .= $contractName.'.'.$contractName.'();';
			}

			//Add run function of contract
			$code_parsed .= $contractName.'.'.$function.'('.$param.');';
		}

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return $code_parsed;
	}

	/**
     * Function that return contract name
     *
     * @param string $code
     *
     * @return string
     */
	public static function GetContractName($code) {
		//Check if have Contract define struct
		$matches = [];
		preg_match("/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}=\s{0,}\{/",$code,$matches);
		return (isset($matches[1])) ? $matches[1]:'';
	}

	/**
     * Function that check if can call a function contract
     *
     * @param string $code
	 * @param string $functionToCall
     *
     * @return string
     */
	public static function canCallThisFunction($code,$functionToCall) {
		$functions = self::getFunctions($code);
		if (!empty($functions['public'])) {
			foreach ($functions['public'] as $function=>$params) {
				if ($function == $functionToCall) {
					return true;
				}
			}
		}
		return false;
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
				'MaxSupply'=>100,
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

			//Check if have define MaxSupply
			$matches = [];
			preg_match("/[Dd]efine MaxSupply (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['MaxSupply'] = $matches[1];
				if ($token['MaxSupply'] > 1000000000000000) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>MaxSupply</strong> max value: <strong>1000000000000000</strong>';
				}
				else if ($token['MaxSupply'] < 1) {
					return '<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>MaxSupply</strong> min value: <strong>1</strong>';
				}
			}

			//Check if have define Precision
			$matches = [];
			preg_match("/[Dd]efine Precision (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['Precision'] = $matches[1];
				if ($token['Precision'] > 18) {
					die('<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> max value: <strong>18</strong>');
				}
				if ($token['Precision'] < 0) {
					die('<strong class="text-danger">J4FVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> min value: <strong>0</strong>');
				}
			}
		}
		return $token;
	}

	/**
     * Function that get functions of contract
     *
     * @param string $code
	 *
	 * @return array
     */
	public static function getFunctions($code) {

		$functions = array(
			'public' => array(),
			'private' => array(),
		);

		//Parse normal functions
		$matches = array();
		preg_match_all('/(\w*)\s*:\s*function\s*\((.*)\)\s*(?:(public|private)|\s)/',$code,$matches);
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

				$functions[$funcType][$funcName] = $parameters;

				$i++;
			}
		}

		return $functions;
	}

}
?>
