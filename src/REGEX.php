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
