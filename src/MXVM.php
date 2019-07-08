<?php
class MXVM {

	public static $data = array();

	// Clear input code
	public static function _parse($code) {

		$code_parsed = $code;

		//Parse prints
		$matches = array();
		preg_match_all("/print\((.*)\);/",$code,$matches);
		foreach ($matches as $match) {
			if (count($match) > 0)
				if (strpos($match[0],'print') !== false)
					$code_parsed = str_replace($match,'',$code_parsed);
		}


		//mapping(address => doublePrecision) balances,
		$matches = array();
		preg_match_all("/mapping\(address => doublePrecision\) (.*),/",$code,$matches);
		if (!empty($matches[0])) {
			$code_parsed = str_replace($matches[0][0],$matches[1][0].': contract.table("'.$matches[1][0].'"),',$code_parsed);
		}

		//Special unmapping::balances(address => doublePrecision)
		$matches = array();
		preg_match_all("/unmapping::(.*)\(address => doublePrecision\);/",$code,$matches);
		if (!empty($matches[0])) {
			$code_parsed = str_replace($matches[0][0],'contract.table_set("'.$matches[1][0].'",this.'.$matches[1][0].');',$code_parsed);
		}

		//Special set::$var
		$matches = array();
		preg_match_all("/set::(.*) (.*);/",$code,$matches);
		if (!empty($matches[0])) {
			$code_parsed = str_replace($matches[0][0],'contract.set("'.$matches[1][0].'",'.$matches[2][0].');',$code_parsed);
		}

		//Special get::$var
		$matches = array();
		preg_match_all("/get::([a-zA-Z]{0,})/",$code,$matches);
		if (!empty($matches[0])) {
			$code_parsed = str_replace($matches[0][0],'contract.get("'.$matches[1][0].'")',$code_parsed);
		}

		//Special wrapping(address => doublePrecision) balances {receiver};
		$matches = array();
		preg_match_all("/wrapping\(address => doublePrecision\) (.*) {(.*)}/",$code,$matches);
		if (!empty($matches[0])) {
			$i = 0;
			foreach ($matches[0] as $match) {
				$code_parsed = str_replace($matches[0][$i],'this.'.$matches[1][$i].'['.$matches[2][$i].'] = (this.'.$matches[1][$i].'['.$matches[2][$i].'] > 0) ? parseFloat(this.'.$matches[1][$i].'['.$matches[2][$i].']):0',$code_parsed);
				$i++;
			}
		}

		//Special define::var
		$matches = array();
		preg_match_all("/define::([a-zA-Z]{0,})([;\)])/",$code_parsed,$matches);
		if (!empty($matches[0])) {

			//Get token Info
			$token = MXVM::getTokenDefine($code);

			$i = 0;
			foreach ($matches[0] as $match) {
				if (isset($token[$matches[1][$i]])) {
					$code_parsed = str_replace($matches[0][$i],$token[$matches[1][$i]].$matches[2][$i],$code_parsed);
				}
				else {
					$code_parsed = str_replace($matches[0][$i],'0'.$matches[2][$i],$code_parsed);
				}
				$i++;
			}
		}

		//Comment MXDity vars
		$code_parsed = str_replace('#pragma mxdity',		'//pragma mxdity',		$code_parsed);
		$code_parsed = str_replace('#define Token',			'//define Token',		$code_parsed);
		$code_parsed = str_replace('#define Name',			'//define Name',		$code_parsed);
		$code_parsed = str_replace('#define MaxSupply',		'//define MaxSupply',	$code_parsed);
		$code_parsed = str_replace('#define Precision',		'//define Precision',	$code_parsed);

		return $code_parsed;

	}

	// Parse CALL Contract
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

	public static function _init($code) {

		MXVM::$data = array();

		//Parse code
		$code_parsed = MXVM::_parse($code);

		//Check if have Contract define struct
		$matches = array();
		preg_match("/[Cc]ontract (.*) = {/",$code_parsed,$matches);
		if (count($matches) < 2) {
			return 'Error parsing Contract struct name';
		}

		//Add init function to start Contract
		$code_parsed = $code_parsed . '
'.$matches[1].'.'.$matches[1].'();';

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return str_replace($matches[0],str_replace('Contract','var',$matches[0]),$code_parsed);
	}

	public static function call($code,$function,$params=array()) {

		//Parse code
		$code_parsed = MXVM::_parse($code);

		//Check if have Contract define struct
		$matches = array();
		preg_match("/[Cc]ontract (.*) = {/",$code,$matches);
		if (count($matches) < 2) {
			return 'Error parsing Contract struct name';
		}

		//Add all params
		$param = '';
		foreach ($params as $p) {
			if (strlen($param) > 0) $param .= ',';
			if (is_numeric($p))
				$param .= $p;
			else
				$param .= "'".$p."'";
		}

		//Add run function of contract
		$code_parsed = $code_parsed . '
'.$matches[1].'.'.$function.'('.$param.');';

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return str_replace($matches[0],str_replace('Contract','var',$matches[0]),$code_parsed);
	}

	public static function _get($key) {
		if (isset(MXVM::$data[$key]))
			return MXVM::$data[$key];
		return 'null';
	}

	public static function _set($key,$value) {
		MXVM::$data[$key] = $value;
	}

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
			$matches = array();
			preg_match("/[Dd]efine Token (.*)/",$code,$matches);
			if (count($matches) < 2) {
				return 'Error parsing Contract struct name';
			}
			$token['Token'] = $matches[1];

			//Check if have define Name
			$matches = array();
			preg_match("/[Dd]efine Name (.*)/",$code,$matches);
			if (count($matches) < 2) {
				return 'Error parsing Contract struct name';
			}
			$token['Name'] = $matches[1];

			//Check if have define MaxSupply
			$matches = array();
			preg_match("/[Dd]efine MaxSupply (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['MaxSupply'] = $matches[1];
				if ($token['MaxSupply'] > 1000000000000000) {
					return '<strong class="text-danger">MXVM_DEFINE_ERROR</strong> parsing <strong>MaxSupply</strong> max value: <strong>1000000000000000</strong>';
				}
				else if ($token['MaxSupply'] < 1) {
					return '<strong class="text-danger">MXVM_DEFINE_ERROR</strong> parsing <strong>MaxSupply</strong> min value: <strong>1</strong>';
				}
			}

			//Check if have define Precision
			$matches = array();
			preg_match("/[Dd]efine Precision (.*)/",$code,$matches);
			if (count($matches) >= 2) {
				$token['Precision'] = $matches[1];
				if ($token['Precision'] > 18) {
					die('<strong class="text-danger">MXVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> max value: <strong>18</strong>');
				}
				if ($token['Precision'] < 0) {
					die('<strong class="text-danger">MXVM_DEFINE_ERROR</strong> parsing <strong>Precision</strong> min value: <strong>0</strong>');
				}
			}
		}
		return $token;
	}
}

function js_get($str) {
	return js_str(MXVM::_get(php_str($str)));
}

function js_set($str,$value) {
	return js_str(MXVM::_set(php_str($str),php_str($value)));
}

function js_table_set($index,$value) {

	$index = php_str($index);
	$array_value =  php_array($value);

	MXVM::$data[$index] = $array_value;
}

function js_table_set_sub($index,$value,$subindex) {

	$index = php_str($index);
	$subindex = php_str($subindex);
	$array_value =  php_array($value);

	MXVM::$data[$index][$subindex] = $array_value;
}

function js_table($table) {
	$table = php_str($table);

	if (isset(MXVM::$data[$table]))
		$object = js_object(MXVM::$data[$table]);
	else
		$object = js_object(null);

	return $object;
}

function js_table_get($table,$index) {

	$table = php_str($table);
	$index = php_str($index);

	return js_str(MXVM::$data[$table][$index]);
}

function js_table_get_sub($table,$index,$subindex) {

	$table = php_str($table);
	$index = php_str($index);
	$subindex = php_str($subindex);

	return js_str(MXVM::$data[$table][$index][$subindex]);
}
?>