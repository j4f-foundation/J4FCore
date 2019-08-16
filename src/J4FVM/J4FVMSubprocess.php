<?php
Class J4FVMSubprocess {

	public $contractHash;
	public $txnHash;

	public $txnData;
	public $txnFrom;
	public $txnAmount;

	public $version;
	public $output;
	public $typeCall;

	public function __construct($typeCall='READ') {

		if (strtoupper($typeCall) == 'MAKE')
			$this->typeCall = 'MAKE';

		else if (strtoupper($typeCall) == 'WRITE')
			$this->typeCall = 'WRITE';

		else if (strtoupper($typeCall) == 'READ')
			$this->typeCall = 'READ';
	}

	public function setTxnHash($txnHash) {
		$this->txnHash = $txnHash;
	}

	public function setContractHash($contractHash) {
		$this->contractHash = $contractHash;
	}

	public function setFrom($from) {
		$this->txnFrom = $from;
	}

	public function setAmount($amount) {
		$this->txnAmount = $amount;
	}

	public function setVersion($version) {
		$this->version = $version;
	}

	public function setData($data) {
		$this->txnData = $data;
	}

	public function run() {

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

	public function output() {
		return $this->output;
	}
}
?>
