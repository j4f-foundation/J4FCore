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

class Transaction {

    public $hash;
    public $from;
    public $to;
    public $amount;
    public $signature;
    public $timestamp;
	public $tx_fee;
	public $data;

    /**
     * Transaction constructor.
     * @param string $from
     * @param string $to
     * @param string $amount
     * @param string $privKey
     * @param string $password
     * @param int $tx_fee
     * @param bool $signed
     * @param string $hash
     * @param string $signature
     * @param string $timestamp
     */
    public function __construct(string $from,string $to, string $amount,string $privKey,string $password="",string $tx_fee,string $data='',bool $signed=false,string $hash=null,string $signature=null,string $timestamp=null)
    {
        $this->tx_fee = ($tx_fee != null) ? bcadd($tx_fee,"0",18):bcadd("0","0",18);
		$this->from = ($from == "") ? null:$from;
        $this->to = $to;
		$this->amount = bcadd($amount,"0",18);

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
            $this->timestamp = Tools::GetGlobalTime();
            if ($sign = Pki::encrypt($this->message(), $privKey,$password)) {
                $this->signature = $sign;
                $this->hash = $this->message();
            } else {
                $this->signature = "unknown";
            }
        }
    }

    /**
     * Get hash transaction
     *
     * @return string
     */
    public function message() : string {
        return PoW::hash($this->from.$this->to.$this->amount.$this->tx_fee.$this->timestamp.$this->data);
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
