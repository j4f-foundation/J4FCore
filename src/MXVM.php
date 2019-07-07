<?php
class MXVM {

	public static $data = array();

	// Clear input code
	public static function _parse($code) {

		$code_parsed = $code;

		$matches = array();
		preg_match_all("/print\((.*)\);/",$code,$matches);
		foreach ($matches as $match) {

			if (strpos($match[0],'print') !== false)
				$code_parsed = str_replace($match,'',$code_parsed);
		}

		//Comment MXDity vars
		$code_parsed = str_replace('#pragma mxdity',		'//pragma mxdity',		$code_parsed);
		$code_parsed = str_replace('#define Token',			'//define Token',		$code_parsed);
		$code_parsed = str_replace('#define Name',			'//define Name',		$code_parsed);
		$code_parsed = str_replace('#define MaxSupply',		'//define MaxSupply',	$code_parsed);
		$code_parsed = str_replace('#define Precision',		'//define Precision',	$code_parsed);

		return $code_parsed;

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
		$code_parsed = $code_parsed . $matches[1].'.'.$matches[1].'();';

		//Replace Contract Keyword to var (Javascript dont accept Contract type var)
		return str_replace($matches[0],str_replace('Contract','var',$matches[0]),$code_parsed);
	}

	public static function call($code,$function,$params=array(),$storedData=array()) {

		MXVM::$data = $storedData;

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
		$code_parsed = $code . $matches[1].'.'.$function.'('.$param.');';

		//Comment pragma line
		$code_parsed = str_replace('pragma mxdity','//pragma mxdity',$code_parsed);

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