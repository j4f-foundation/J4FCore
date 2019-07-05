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

class Pki {
    /**
     * We generate a public and private key
     *
     * @param $password
     * @return array
     */
    public static function generateKeyPair($password) {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        if ($password != null)
            @openssl_pkey_export($res,$privKey, $password);
        else
            @openssl_pkey_export($res,$privKey);

        return ['private'=>$privKey, 'public'=>@openssl_pkey_get_details($res)['key']];
    }

    /**
     * We encrypt a message with the private key
     *
     * @param $message
     * @param $privKey
     * @param string $password
     * @return bool|string
     */
    public static function encrypt($message,$privKey,$password="") {

        if (strlen($password) > 0) {
            if (@openssl_pkey_get_private($privKey, $password)) {
                @openssl_private_encrypt($message, $crypted, @openssl_pkey_get_private($privKey, $password));
            }
            else {
                return false;
            }
        }
        else
            @openssl_private_encrypt($message,$crypted, $privKey);

        return base64_encode($crypted);
    }

    /**
     * We decrypt a message with the public key
     *
     * @param $crypted
     * @param $pubKey
     * @return mixed
     */
    public static function decrypt($crypted,$pubKey) {
        @openssl_public_decrypt(base64_decode($crypted),$decrypted,$pubKey);
        return $decrypted;
    }

    /**
     * We validate if the message can be decrypted
     *
     * @param $message
     * @param $crypted
     * @param $pubKey
     * @return bool
     */
    public static function isValid($message,$crypted,$pubKey) {
        return $message == self::decrypt($crypted,$pubKey);
    }

}
?>