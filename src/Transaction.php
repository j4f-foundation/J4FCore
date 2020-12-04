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

class Transaction {

	//General vars
    public $hash;
    public $from;
    public $to;
    public $amount;
    public $signature;
    public $timestamp;
	public $data;
	public $version;
	public $gasLimit;
	public $gasPrice;

	public function __construct($version) {
		$this->version = $version;
	}

	/**
     * Create transaction using old system (with fees)
     * @param string $from
     * @param string $to
     * @param string $amount
     * @param string $privKey
     * @param string $password
     * @param bool $signed
     * @param string $hash
     * @param string $signature
     * @param string $timestamp
     */
    public static function withGas(string $from, string $to, string $amount,string $privKey,string $password="",string $data='', int $gasLimit, string $gasPrice, bool $signed=false,string $hash=null,string $signature=null,string $timestamp=null)
    {
		$instanceTXN = new self("0.0.1");
		$instanceTXN->makeTxnWithGas($from,$to,$amount,$privKey,$password,$data,$gasLimit,$gasPrice,$signed,$hash,$signature,$timestamp);
		return $instanceTXN;
    }

	/**
     * Create transaction using old system (with fees)
     * @param string $from
     * @param string $to
     * @param string $amount
     * @param string $privKey
     * @param string $password
     * @param int $gasLimit
	 * @param string $gasPrice
     * @param bool $signed
     * @param string $hash
     * @param string $signature
     * @param string $timestamp
     */
	protected function makeTxnWithGas(string $from,string $to, string $amount,string $privKey,string $password="", string $data='', int $gasLimit, string $gasPrice, bool $signed=false, string $hash=null, string $signature=null, string $timestamp=null)
    {
		$this->from = ($from == "") ? null:$from;
        $this->to = $to;
		$this->amount = bcadd($amount,"0",18);
		$this->gasLimit = $gasLimit;
		$this->gasPrice = bcadd("0",$gasPrice,18);

		// Check if data is parsed
		$data = trim($data);
		$isDataParsed = strpos($data, '0x');
		if ($isDataParsed === false)
			$data = Tools::str2hex($data);
		else if ($isDataParsed > 0)
			$data = Tools::str2hex($data);

		$this->data = $data;

        if ($signed) {
            $this->hash = $hash;
            $this->signature = $signature;
            $this->timestamp = $timestamp;
        } else {
            //Guardamos el tiempo en el que se crea la transaccion
            $this->timestamp = Tools::GetGlobalMilitime();
            if ($sign = Pki::encrypt($this->message(), $privKey,$password)) {
                $this->signature = $sign;
                $this->hash = $this->message();
            } else {
                $this->signature = "unknown";
            }
        }
    }

	/**
     * Get fee transaction
     *
     * @return string
     */
	public function GetFee(DB &$chaindata) {
		$txnGas = Gas::calculateGasTxn($chaindata,$this->to,$this->data);
		if ($txnGas <= $this->gasLimit)
			$fees = @bcmul($txnGas,$this->gasPrice,18);
		else
			$fees = @bcmul($this->gasLimit,$this->gasPrice,18);
		return $fees;
	}

    /**
     * Get hash transaction
     *
     * @return string
     */
    public function message() : string {
		return PoW::hash($this->from.$this->to.$this->amount.$this->timestamp.$this->data.$this->gasLimit.$this->gasPrice);
    }

    /**
     * Check if transaction is valid
     *
     * @return bool
     */
    public function isValid() : bool {
        return !$this->from || Pki::isValid($this->message(), $this->signature, $this->from);
    }
}
?>
