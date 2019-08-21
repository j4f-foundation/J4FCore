<?php
class REGEX {

	const FunityVersion = '/#pragma funity ([0-9]{0,}\.[0-9]{0,}\.[0-9]{0,})/m';

	const DefineToken = '/[Dd]efine Token (.*)/m';
	const DefineName = '/[Dd]efine Name (.*)/m';
	const DefineSupply = '/[Dd]efine TotalSupply (.*)/m';
	const DefinePrecision = '/[Dd]efine Precision (.*)/m';

	//CONTRACT
	const ContractName = '/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC10|is J4FRC20|)\s{0,}\{/m';
	const isJ4FRC10 = '/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC10|)\s{0,}\{/m';
	const isJ4FRC20 = '/[Cc]ontract\s{0,}([a-zA-Z0-9]*)\s{0,}(is J4FRC20|)\s{0,}\{/m';

	const ContractCode = '/^Contract\s*([a-zA-Z0-9]{1,})\s({(?>"(?:[^"\\\\]*+|\\\\.)*"|\'(?:[^\'\\\\]*+|\\\\.)*\'|\/\/.*$|\/\*[\s\S]*?\*\/|#.*$|<<<\s*["\']?(\w+)["\']?[^;]+\3;$|[^{}<\'"\/#]++|[^{}]++|(?2))*})/m';

	const ContractFunctions = '/(\w*)\s*:\s*function\s*\((.*)\)\s*(?:(public|private)|)\s*(?:(returns\s*bool|returns\s*string|returns\s*uint256|returns\s*int|returns)|)\s*\K({((?>"(?:[^"\\\\]*+|\\\\.)*"|\'(?:[^\'\\\\]*+|\\\\.)*\'|\/\/.*$|\/\*[\s\S]*?\*\/|#.*$|<<<\s*["\']?(\w+)["\']?[^;]+\3;$|[^{}<\'"\/#]++|[^{}]++|(?5))*)})/m';

	const ContractFunctionsSimple = '/(\w*)\s*:\s*function\s*\((.*)\)\s*(?:(public|private)|)\s*(?:(returns\s*bool|returns\s*string|returns\s*uint256|returns\s*uint|returns\s*int|returns)|\s*)/';

	// INTERFACES
	const InterfaceCode = '/^Interface\s*([a-zA-Z0-9]{1,})\s({(?>"(?:[^"\\\\]*+|\\\\.)*"|\'(?:[^\'\\\\]*+|\\\\.)*\'|\/\/.*$|\/\*[\s\S]*?\*\/|#.*$|<<<\s*["\']?(\w+)["\']?[^;]+\3;$|[^{}<\'"\/#]++|[^{}]++|(?2))*})\;/m';

	const InterfaceFunctions = '/^\s*function\s{0,}([a-zA-Z0-9]{0,})\((.*)\)\s*public\s*returns\s*([a-zA-Z0-9]*);/m';

	//CLASS
	const ClassName = "/^[Cc]lass\s{0,}+([a-zA-Z_\-]*)\s{0,}{/m";

	//FUNITY
	const PrintCode = '/print\((.*)\);/m';
	const Mapping = '/mapping\(address => uint256\) (.*),/m';
	const Unmapping = '/unmapping::(.*)\(address => uint256\);/m';
	const Wrapping = '/wrapping\(address => uint256\) (.*) {(.*)};/m';
	const Set = '/set::(.*) (.*);/m';
	const Get = '/get::([a-zA-Z]{0,})/m';
	const Define = '/define::([a-zA-Z]{0,})([;\)])/m';
	const Return = '/[^\w]return\((.*)\)/m';
	const Error = '/[^\w]error\((.*)\)/m';
	const Address0 = '/address\(0\)/m';
	const FloatValue = '/\.([1-9].*|(?:0{0,})[1-9].*|)/m';
	const Comments = '/\/\/.*/m';

}
?>
