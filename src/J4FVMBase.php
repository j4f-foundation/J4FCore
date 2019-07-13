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

class J4FVMBase {

	public static $var_types = array('address','uint256','string');
	public static $data = [];
	public static $txn_hash = '';

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
		preg_match_all('/(\w*)\s*:\s*function\s*\((.*)\)\s*(?:(public|private)|\s)/',$code_parsed,$matches);
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
	 * @param bool $debug
     *
     * @return string
     */
	public static function _checkSyntaxError($code) {
		$code_parsed = self::_parseComments($code);

		if (strpos($code_parsed,'+') != false)
			$code_parsed = str_replace('+','++',$code_parsed);

		if (strpos($code_parsed,'-') != false)
			$code_parsed = str_replace('-','--',$code_parsed);

		return $code_parsed;
	}

	/**
     * Function that clear funity code
     *
     * @param string $code
     *
     * @return string
     */
	public static function _parse($code) {

		$code_parsed = self::_checkSyntaxError($code);

		$code_parsed = self::_parseFunctions($code_parsed);

		//Check if have Contract define struct
		$matches = [];
		preg_match("/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}=\s{0,}\{/",$code_parsed,$matches);
		$code_parsed = str_replace($matches[0],str_replace('Contract','var',$matches[0]),$code_parsed);

		//Parse prints
		$matches = [];
		preg_match_all("/print\((.*)\);/",$code_parsed,$matches);
		foreach ($matches as $match) {
			if (count($match) > 0)
				if (strpos($match[0],'print') !== false)
					$code_parsed = str_replace($match,'',$code_parsed);
		}

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
				var checkBalance = math.comp(this.'.$matches[1][$i].'['.$matches[2][$i].'],0);
		        if (checkBalance != 1 && checkBalance != 0) {
		            this.'.$matches[1][$i].'['.$matches[2][$i].'] = math.parse(0);
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
					if (is_numeric($token[$matches[1][$i]]))
						$code_parsed = str_replace($matches[0][$i],$token[$matches[1][$i]].$matches[2][$i],$code_parsed);
					else
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
				$code_parsed = str_replace($matches[0][$i],'return '.$matches[1][$i],$code_parsed);
			}
		}

		//Special error('message')
		$matches = [];
		preg_match_all("/[^\w]error\((.*)\)/",$code_parsed,$matches);
		//echo '<pre>'.print_r($matches,true).'</pre>';
		if (!empty($matches[0])) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				$code_parsed = str_replace($matches[0][$i],'return null',$code_parsed);
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
		$code_parsed = str_replace('#define MaxSupply',		'//define MaxSupply',	$code_parsed);
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
				if (preg_match($REGEX_Address,$sender) && preg_match($REGEX_Address,$receiver) && preg_match('/\.{0,}\d/',$receiver)) {

					//write Internal Transaction on blockchain (local)
					$db->addInternalTransaction(self::$txn_hash,$sender,$receiver,$amount);
					return true;
				}
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

	//MATHS
	public static function math_parse($num1) {
		return js_str(uint256::parse(php_str($num1)));
	}

	public static function math_toDec($num1) {
		return js_str(uint256::toDec(php_str($num1)));
	}

	public static function math_add($num1,$num2) {
		return js_str(uint256::add(php_str($num1),php_str($num2)));
	}

	public static function math_sub($num1,$num2) {
		return js_str(uint256::sub(php_str($num1),php_str($num2)));
	}

	public static function math_compare($num1,$num2) {
		return js_str(uint256::comp(php_str($num1),php_str($num2)));
	}

	public static function math_mul($num1,$num2) {
		return js_str(uint256::mul(php_str($num1),php_str($num2)));
	}

	public static function math_div($num1,$num2) {
		return js_str(uint256::div(php_str($num1),php_str($num2)));
	}

	public static function math_pow($num1,$num2) {
		return js_str(uint256::pow(php_str($num1),php_str($num2)));
	}

	public static function math_mod($num1,$num2) {
		return js_str(uint256::mod($num1,$num2));
	}

	public static function math_sqrt($num1) {
		return js_str(uint256::sqrt($num1));
	}

	public static function math_powmod($num1,$num2,$mod) {
		return js_str(uint256::powmod(php_str($num1),php_str($num2),php_str($mod)));
	}
}
?>
