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

Class J4FVMSubprocess {

	public $contractHash;
	public $txnHash;

	public $txnData;
	public $txnFrom;
	public $txnAmount;

	public $version;
	public $output;
	public $typeCall;

	public function __construct(string $typeCall='READ') {

		if (strtoupper($typeCall) == 'MAKE')
			$this->typeCall = 'MAKE';

		else if (strtoupper($typeCall) == 'WRITE')
			$this->typeCall = 'WRITE';

		else if (strtoupper($typeCall) == 'READ')
			$this->typeCall = 'READ';
	}

	public function setTxnHash(string $txnHash) : void {
		$this->txnHash = $txnHash;
	}

	public function setContractHash(string $contractHash) : void {
		$this->contractHash = $contractHash;
	}

	public function setFrom(string $from) : void {
		$this->txnFrom = $from;
	}

	public function setAmount(string $amount) : void {
		$this->txnAmount = $amount;
	}

	public function setVersion(string $version) : void {
		$this->version = $version;
	}

	public function setData(string $data) : void {
		$this->txnData = $data;
	}

	public function run() : string {

		if ($this->contractHash == null)
			return 'Error, ContractHash not defined';

		if ($this->txnData == null)
			return 'Error, TXN Data not defined';

		if ($this->txnHash == null)
			return 'Error, TXN Hash not defined';

		if ($this->txnFrom == null)
			return 'Error, TXN From not defined';

		if ($this->txnAmount == null)
			return 'Error, TXN Amount not defined';

		//Default use latest versionfd
		if ($this->version == null)
			$this->version = 'latest';

        //Make params for j4fvm
        $params = $this->typeCall.' '.$this->contractHash.' '.$this->version.' '.$this->txnData.' '.$this->txnHash.' '.$this->txnFrom.' '.$this->txnAmount;

		try {

			$directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;
			$prefixCommand = (IS_WIN) ? 'start /B cmd /C ':'';
            $handle = @popen($prefixCommand.PHP_RUN_COMMAND.' '.$directoryProcessFile.'j4fvm.php ' . $params, 'r');

			//Get Output
			$outputLines = [];
			while (!@feof($handle))
			    $outputLines[] = @fgets($handle);
			@pclose($handle);

			//Save output
			$this->output = $outputLines;

			return true;
		}
		catch (Exception $e) {
			return $e->getMessage();
		}
	}

	public function output() : array {
		if (is_string($this->output))
			return [$this->output];
		else
			return $this->output;
	}
}
?>
