<?php
// MIT License
//
// Copyright (c) 2018 MXCCoin
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
     * @param $from
     * @param $to
     * @param $amount
     * @param $privKey
     * @param string $password
     * @param int $tx_fee
     * @param bool $signed
     * @param null $hash
     * @param null $signature
     * @param null $timestamp
     */
    public function __construct($from,$to,$amount,$privKey,$password="",$tx_fee,$data='',$signed=false,$hash=null,$signature=null,$timestamp=null)
    {
        $this->tx_fee = ($tx_fee != null) ? $tx_fee:'';
        $this->from = $from;
        $this->to = $to;
		$this->amount = $amount;

        if ($signed) {
			$this->data = $data;
            $this->hash = $hash;
            $this->signature = $signature;
            $this->timestamp = $timestamp;
        } else {

			$this->data = Tools::str2bytesHex($data);
			
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
    public function message() {
        return PoW::hash($this->from.$this->to.$this->amount.$this->timestamp.$this->data);
    }

    /**
     * Check if transaction is valid
     *
     * @return bool
     */
    public function isValid() {
        return !$this->from || Pki::isValid($this->message(), $this->signature, $this->from);
    }
}
?>