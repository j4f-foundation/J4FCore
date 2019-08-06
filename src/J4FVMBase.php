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

class J4FVMBase {

	public static $var_types = array('address','uint256','int','uint','string','tokenId');
	public static $data = [];
	public static $txn_hash = '';
	public static $contract_hash = '';

	/**
     * Function that parse all funity functions
     *
     * @param string $code
     *
     * @return string
     */
	public static function _parseFunctions($code) {

		$code_parsed = $code;

		//Parse normal functions
		$matches = [];
		preg_match_all('/(\w*)\s*:\s*function\s*\((.*)\)\s*(?:(public|private)|)\s*(?:(returns\s*bool|returns\s*string|returns\s*uint256|returns\s*uint|returns\s*int|returns)|\s*)/',$code_parsed,$matches);
		if (!empty($matches[0])) {
			$i = 0;
			foreach ($matches[0] as $match) {
				foreach (self::$var_types as $type) $matches[2][$i] = str_replace($type,'',$matches[2][$i]);
				$code_parsed = str_replace($matches[0][$i],$matches[1][$i].': function('.$matches[2][$i].')',$code_parsed);
				$i++;
			}
		}

		return $code_parsed;
	}

	/**
     * Function that remove all code comments
     *
     * @param string $code
     *
     * @return string
     */
	public static function _parseComments($code) {
		$code_parsed = $code;
		$matches = [];
		preg_match_all('/\/\/.*/',$code_parsed,$matches);
		if (!empty($matches[0]))
			foreach ($matches[0] as $match)
				$code_parsed = str_replace($match,'',$code_parsed);
		return $code_parsed;
	}

	/**
     * Function that check special funity syntax
     *
     * @param string $code
     *
     * @return array
     */
	public static function _checkSyntaxError($code) {

		$errors = [];

		$code_parsed = self::_parseComments($code);

		if (strpos($code_parsed,'+') != false)
			$code_parsed = str_replace('+','++',$code_parsed);

		if (strpos($code_parsed,'-') != false)
			$code_parsed = str_replace('-','--',$code_parsed);

		//Get functions of contract with all info (params, returns, code)
		$functions = J4FVM::getFunctions($code,true);

		//Check if is a J4FRC10
		if (J4FVM::isJ4FRC10Standard($code)) {
			//Check if contract its a Token and have J4FRC-10 Standard
			$tokenInfo = J4FVM::getTokenDefine($code);
			if ($tokenInfo != null) {
				$isJ4FRC10Standard = J4FVM::CheckJ4FRC10Standard($code);
				if (strlen($isJ4FRC10Standard) > 0)
					$code_parsed .= $isJ4FRC10Standard;
			}
			else {
				$errors[] = 'error("<strong class=\"text-danger\">COMPILER_ERROR</strong> Not defines of token J4FRC10<br>");';
			}
		}

		if (J4FVM::isJ4FRC20Standard($code)) {
			//Check if contract its a Token and have J4FRC-10 Standard
			$tokenInfo = J4FVM::getTokenDefine($code);
			if ($tokenInfo != null) {
				$isJ4FRC20Standard = J4FVM::CheckJ4FRC20Standard($code);
				if (strlen($isJ4FRC20Standard) > 0)
					$code_parsed .= $isJ4FRC20Standard;
			}
			else {
				$errors[] = 'error("<strong class=\"text-danger\">COMPILER_ERROR</strong> Not defines of token J4FRC20<br>");';
			}
		}

		//Check if function have return definition but not have return code
		foreach ($functions as $functionsByType) {
			foreach ($functionsByType as $function=>$functionInfo) {
				//Comprobamos si tiene return
				if (strlen($functionInfo['return']) > 0) {
					$matches = [];
					preg_match_all('/return\(.*\)/',$functionInfo['code'],$matches);
					if (empty($matches[0])) {
						$errors[] = 'error("<strong class=\"text-danger\">COMPILER_ERROR</strong> Function <strong>'.$function.'()</strong> defined with returns but not have return code<br>");';
					}
				}
			}
		}

		return [$code_parsed,$errors];
	}

	/**
     * Function that clear funity code
     *
     * @param string $code
     *
     * @return string
     */
	public static function _parse($code,$debug=false) {

		//Check Syntax Error
		$returnCheckSyntax = self::_checkSyntaxError($code);
		if (!empty($returnCheckSyntax[1]))
			return implode(" ",$returnCheckSyntax[1]);
		$code_parsed = $returnCheckSyntax[0];

		//Check if have Contract define struct
		$matches = [];
		preg_match("/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC10|is J4FRC20|)\s{0,}\{/",$code_parsed,$matches);
		if (!empty($matches))
			$code_parsed = str_replace($matches[0],'var '.$matches[1].' = {',$code_parsed);

		//Class
		$matches = [];
		preg_match_all("/[Cc]lass\s{0,}+([a-zA-Z_\-]*)\s{0,}{/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				if (!empty($matches[0]))
					$code_parsed = str_replace($matches[0][$i],'var '.$matches[1][$i].' = {',$code_parsed);
		}

		//Parse functions Funity to JS
		$code_parsed = self::_parseFunctions($code_parsed);

		//Parse prints
		if ($debug === false) {
			$matches = [];
			preg_match_all("/print\((.*)\);/",$code_parsed,$matches);
			foreach ($matches as $match) {
				if (count($match) > 0)
					if (strpos($match[0],'print') !== false)
						$code_parsed = str_replace($match,'',$code_parsed);
			}
		}

		//Special ..
		$code_parsed = str_replace('..','+',$code_parsed);

		//mapping(address => uint256) balances,
		$matches = [];
		preg_match_all("/mapping\(address => uint256\) (.*),/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				$code_parsed = str_replace($matches[0][$i],$matches[1][$i].': contract.table_uint256("'.$matches[1][$i].'"),',$code_parsed);
		}

		//Special unmapping::balances(address => uint256)
		$matches = [];
		preg_match_all("/unmapping::(.*)\(address => uint256\);/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				$code_parsed = str_replace($matches[0][$i],'contract.table_set("'.$matches[1][$i].'",this.'.$matches[1][$i].');',$code_parsed);
		}

		//Special set::$var
		$matches = [];
		preg_match_all("/set::(.*) (.*);/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				$code_parsed = str_replace($matches[0][$i],'contract.set("'.$matches[1][$i].'",'.$matches[2][$i].');',$code_parsed);
		}

		//Special get::$var
		$matches = [];
		preg_match_all("/get::([a-zA-Z]{0,})/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				$code_parsed = str_replace($matches[0][$i],'contract.get("'.$matches[1][$i].'")',$code_parsed);
		}

		//Special wrapping(address => uint256) balances {receiver};
		$matches = [];
		preg_match_all("/wrapping\(address => uint256\) (.*) {(.*)};/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++) {

				$replace = '
				var checkBalance = math.comp(this.'.$matches[1][$i].'['.$matches[2][$i].'],"0");
		        if (checkBalance != 1 && checkBalance != 0) {
		            this.'.$matches[1][$i].'['.$matches[2][$i].'] = math.parse("0");
		        }';
				$code_parsed = str_replace($matches[0][$i],$replace,$code_parsed);
			}
		}

		//Special define::var
		$token = J4FVM::getTokenDefine($code);
		$matches = [];
		preg_match_all("/define::([a-zA-Z]{0,})([;\)])/",$code_parsed,$matches);
		//echo '<pre>'.print_r($matches,true).'</pre>';
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				if (isset($token[$matches[1][$i]]))
					$code_parsed = str_replace($matches[0][$i],"'".trim($token[$matches[1][$i]])."'".$matches[2][$i],$code_parsed);
				else
					$code_parsed = str_replace($matches[0][$i],'0'.$matches[2][$i],$code_parsed);
			}
		}

		//Special return('message')
		$matches = [];
		preg_match_all("/[^\w]return\((.*)\)/",$code_parsed,$matches);
		//echo '<pre>'.print_r($matches,true).'</pre>';
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				if ($debug === false) {
					$code_parsed = str_replace($matches[0][$i],'return '.$matches[1][$i],$code_parsed);
				}
				else {
					$code_parsed = str_replace($matches[0][$i],'j4f_return('.$matches[1][$i].'); return null',$code_parsed);
				}
			}
		}

		//Special error('message')
		$matches = [];
		preg_match_all("/[^\w]error\((.*)\)/",$code_parsed,$matches);
		//echo '<pre>'.print_r($matches,true).'</pre>';
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				if ($debug === false) {
					$code_parsed = str_replace($matches[0][$i],'return null',$code_parsed);
				}
				else {
					$code_parsed = str_replace($matches[0][$i],'j4f_error('.$matches[1][$i].'); return null',$code_parsed);
				}
			}
		}

		//Special address(0)
		$matches = [];
		preg_match_all("/address\(0\)/",$code_parsed,$matches);
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++)
				$code_parsed = str_replace($matches[0][$i],'"J4F00000000000000000000000000000000000000000000000000000000"',$code_parsed);
		}

		//Parse Funity & Token vars
		$code_parsed = str_replace('#pragma funity',		'//pragma funity',		$code_parsed);
		$code_parsed = str_replace('#define Token',			'//define Token',		$code_parsed);
		$code_parsed = str_replace('#define Name',			'//define Name',		$code_parsed);
		$code_parsed = str_replace('#define TotalSupply',	'//define TotalSupply',	$code_parsed);
		$code_parsed = str_replace('#define Precision',		'//define Precision',	$code_parsed);

		return $code_parsed;
	}

	/**
     * Function that parse special chars from string
     *
     * @param string $string
     *
     * @return string
     */
	public static function parseSpecialChars($string) {
		$string = str_replace('(','',$string);
		$string = str_replace(')','',$string);
		$string = str_replace('"','',$string);
		$string = str_replace("'",'',$string);
		$string = str_replace("`",'',$string);
		$string = str_replace(";",'',$string);
		$string = str_replace(",",'',$string);
		$string = str_replace("+",'',$string);
		$string = str_replace("-",'',$string);
		$string = str_replace("_",'',$string);
		$string = str_replace("?",'',$string);
		$string = str_replace("/",'',$string);
		$string = str_replace("\\",'',$string);
		$string = str_replace("$",'',$string);
		$string = str_replace("#",'',$string);
		$string = str_replace(" ",'',$string);
		return $string;
	}

	/**
     * Function that parse call contract
     *
     * @param string $callCode
     *
     * @return string
     */
	public static function _parseCall($callCode) {

		$call_parsed = array(
			'func' => '',
			'func_params' => array()
		);
		$e_callCode = explode(' ',$callCode);

		//Save function to call
		$call_parsed['func'] = $e_callCode[0];

		//Save parameters
		for ($i=1;$i<count($e_callCode);$i++) {
			$call_parsed['func_params'][] = $e_callCode[$i];
		}

		return $call_parsed;

	}

	/**
     * Function that return storedData of contract
     *
     * @param string $key
     *
     * @return mixed
     */
	public static function _get($key) {
		if (isset(self::$data[$key]))
			return self::$data[$key];
		return 'null';
	}

	/**
     * Function that set storedData in contract
     *
     * @param string $key
	 * @param string $value
     */
	public static function _set($key,$value) {
		self::$data[$key] = $value;
	}

	/**
     * Function that called from Funity to get storedData from contract
     *
     * @param string $str
	 *
	 * @return string
     */
	public static function js_get($str) {
		return js_str(self::_get(php_str($str)));
	}

	/**
     * Function that called from Funity to set storedData of contract
     *
     * @param string $str
	 * @param string $value
	 *
	 * @return string
     */
	public static function js_set($str,$value) {
		return js_str(self::_set(php_str($str),php_str($value)));
	}

	/**
     * Function that called from Funity to set array data in storedData of contract
     *
     * @param string $index
	 * @param array $value
     */
	public static function js_table_set($index,$value) {
		$index = php_str($index);
		$array_value =  php_array($value);
		self::$data[$index] = $array_value;
	}

	/**
     * Function that called from Funity to set sub_array data in storedData of contract
     *
     * @param string $index
	 * @param array $value
	 * @param string $subindex
     */
	public static function js_table_set_sub($index,$value,$subindex) {

		$index = php_str($index);
		$subindex = php_str($subindex);
		$array_value =  php_array($value);

		self::$data[$index][$subindex] = $array_value;
	}

	/**
     * Function that called from Funity to get array data in storedData of contract
     *
     * @param string $table
	 *
	 * @return array
     */
	public static function js_table($table) {
		$table = php_str($table);

		if (isset(self::$data[$table]))
			$object = js_object(self::$data[$table]);
		else
			$object = js_object(null);

		return $object;
	}

	/**
     * Function that called from Funity to get data of array data in storedData of contract
     *
     * @param string $table
	 * @param string $index
	 *
	 * @return mixed
     */
	public static function js_table_get($table,$index) {

		$table = php_str($table);
		$index = php_str($index);

		return js_str(self::$data[$table][$index]);
	}

	/**
     * Function that called from Funity to get data of array uint256 in storedData of contract
     *
     * @param string $table
	 * @param string $index
	 *
	 * @return mixed
     */
	public static function js_table_uint256($table) {
		$table = php_str($table);

		if (isset(J4FVM::$data[$table])) {
			$object = js_object(J4FVM::$data[$table]);
		}
		else
			$object = js_object(null);

		return $object;
	}

	/**
     * Function that called from Funity to get sub_data of array data in storedData of contract
     *
     * @param string $table
	 * @param string $index
	 * @param string $subindex
	 *
	 * @return mixed
     */
	public static function js_table_get_sub($table,$index,$subindex) {

		$table = php_str($table);
		$index = php_str($index);
		$subindex = php_str($subindex);

		return js_str(self::$data[$table][$index][$subindex]);
	}

	/**
	 * Blockchain Function
     * Write Internal Transaction of contract
     *
     * @param string $sender
     * @param string $receiver
     * @param float $amount
     * @return bool
     */
	public static function blockchain_transfer($sender,$receiver,$amount) {

		if (self::$contract_hash != null && strlen(self::$contract_hash) == 128) {
			//Parsing jsvars to phpvars
			$sender = php_str($sender);
			$receiver = php_str($receiver);
			$amount = php_str($amount);

			//Check if have txn_hash for this J4VM
			if (self::$txn_hash != '') {

				//Instance DB
				$db = new DB();

				if ($db != null) {

					//Check param formats
					$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
					if (preg_match($REGEX_Address,$sender) && preg_match($REGEX_Address,$receiver) && is_numeric($amount)) {

						//write Internal Transaction on blockchain (local)
						$db->addInternalTransaction(self::$txn_hash,self::$contract_hash,$sender,$receiver,$amount);
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Blockchain Function
     * Write Internal Transaction of contract J4FRC20
     *
     * @param string $sender
     * @param string $receiver
     * @param float $amount
     * @return bool
     */
	public static function blockchain_transfer_token($sender,$receiver,$tokenId) {

		echo 'blockchain_transfer_token';

		//Check if have txn_hash for this J4VM
		if (self::$contract_hash != null && strlen(self::$contract_hash) == 128) {

			//Parsing jsvars to phpvars
			$sender = php_str($sender);
			$receiver = php_str($receiver);
			$tokenId = php_str($tokenId);

			//Instance DB
			$db = new DB();

			if ($db != null) {

				//Check param formats
				$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
				if (preg_match($REGEX_Address,$sender) && preg_match($REGEX_Address,$receiver) && is_numeric($tokenId)) {

					//write Internal Transaction on blockchain (local)
					$db->addInternalTransactionToken(self::$txn_hash,self::$contract_hash,$sender,$receiver,$tokenId);
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Contract Function (withdraw)
     * Write Internal Transaction of contract
     *
     * @param string $receiver
     * @param float $amount
     * @return bool
     */
	public static function blockchain_transferWithdraw($receiver=null) {

		if (self::$contract_hash != null && strlen(self::$contract_hash) == 128) {
			if (self::$txn_hash != '' && strlen(self::$txn_hash) == 128) {

				//Parsing jsvars to phpvars
				if ($receiver != null && !is_string($receiver))
					$receiver = php_str($receiver);

				return SmartContract::Withdraw(self::$txn_hash,self::$contract_hash,$receiver);
			}
		}
		return false;
	}

	/**
	 * Destruct Function
     */
	public static function contract_destruct($receiver) {

		if (self::$contract_hash != null && strlen(self::$contract_hash) == 128) {
			if (self::$txn_hash != '' && strlen(self::$txn_hash) == 128) {
				//Parsing jsvars to phpvars
				if ($receiver != null && !is_string($receiver))
					$receiver = php_str($receiver);

				return SmartContract::Destruct(self::$txn_hash,self::$contract_hash,$receiver);
			}
		}
		return false;
	}

	/**
	 * Null Function
     * Need this function for make contracts and dont crash
     *
     * @param string $sender
     * @param string $receiver
     * @param float $amount
     * @return bool
     */
	public static function blockchain_transfer_compiler($sender,$receiver,$amount) {
		return '';
	}

	//UINT256 MATHS
	public static function uint256_parse($num1) {
		return js_str(uint256::parse(@number_format(php_str($num1),0,null,'')));
	}

	public static function uint256_toDec($num1) {
		return js_str(uint256::toDec(@number_format(php_str($num1),0,null,'')));
	}

	public static function uint256_add($num1,$num2) {
		return js_str(uint256::add(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_sub($num1,$num2) {
		return js_str(uint256::sub(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_compare($num1,$num2) {
		return js_str(uint256::comp(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_mul($num1,$num2) {
		return js_str(uint256::mul(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_div($num1,$num2) {
		return js_str(uint256::div(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_pow($num1,$num2) {
		return js_str(uint256::pow(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_mod($num1,$num2) {
		return js_str(uint256::mod(@number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,'')));
	}

	public static function uint256_sqrt($num1) {
		return js_str(uint256::sqrt(@number_format(php_str($num1),0,null,'')));
	}

	public static function uint256_powmod($num1,$num2,$mod) {
		return js_str(uint256::powmod(number_format(php_str($num1),0,null,''),@number_format(php_str($num2),0,null,''),@number_format(php_str($mod),0,null,'')));
	}

	//TABLE
	public static function table_count($table) {
		$table = php_str($table);
		return (isset(self::$data[$table])) ? js_str(count(self::$data[$table])):js_str("0");
	}

	//MATHS
	public static function math_parse($num1) {
		return js_str(uint256::parse(bcadd(php_str($num1),"0",18)));
	}
	public static function math_add($num1,$num2) {
		return js_str(uint256::parse(@bcadd(php_str($num1),php_str($num2),18)));
	}

	public static function math_sub($num1,$num2) {
		return js_str(uint256::parse(@bcsub(php_str($num1),php_str($num2),18)));
	}

	public static function math_compare($num1,$num2) {
		$num1 = php_str($num1);
		$num2 = php_str($num2);
		return js_int(@bccomp($num1,$num2));
	}

	public static function math_mul($num1,$num2) {
		return js_str(uint256::parse(@bcmul(php_str($num1),php_str($num2),18)));
	}

	public static function math_div($num1,$num2) {
		return js_str(uint256::parse(@bcdiv(php_str($num1),php_str($num2),18)));
	}

	public static function math_pow($num1,$num2) {
		return js_str(uint256::parse(@bcpow(php_str($num1),php_str($num2),18)));
	}

	public static function math_mod($num1,$num2) {
		return js_str(uint256::parse(@bcmod(php_str($num1),php_str($num2),18)));
	}

	public static function math_sqrt($num1) {
		return js_str(self::math_parse(@bcsqrt(php_str($num1),18)));
	}

	public static function math_powmod($num1,$num2,$mod) {
		return js_str(self::math_parse(@bcpowmod(php_str($num1),php_str($num2),php_str($mod))));
	}

	public static function math_random($length=32) {
		if (is_numeric($length))
			$randomNum = substr(Tools::hex2dec(PoW::hash(time().rand())),0,$length);
		else
			$randomNum = substr(Tools::hex2dec(PoW::hash(time().rand())),0,php_str($length));
		return js_str($randomNum);
	}

	//JSON
	public static function json_stringify($array) {
		return js_str(@json_encode(php_array($array)));
	}

	public static function json_parse($jsonString) {
		$json = @json_encode(php_str($jsonString));
		if (is_array($json) && !empty($json))
			$object = js_object($json);
		else
			$object = js_object(null);
		return $object;
	}

	//CRYPTO
	public static function js_sha3($str) {
		return js_str(PoW::hash(php_str($str)));
	}
}
?>
