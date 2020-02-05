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

class Pki {
    /**
     * We generate a public and private key
     *
     * @param $password
     * @return array
     */
    public static function generateKeyPair(string $password) : array {
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
     * @param string $message
     * @param string $privKey
     * @param string $password
     * @return bool|string
     */
    public static function encrypt(string $message,string $privKey, string $password="") : string {

        if (strlen($password) > 0) {
            if (@openssl_pkey_get_private($privKey, $password)) {
                @openssl_private_encrypt($message, $crypted, @openssl_pkey_get_private($privKey, $password));
            }
            else {
                return "";
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
    public static function decrypt(string $crypted,string $pubKey) {
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
    public static function isValid(string $message,string $crypted,string $pubKey) {
        return $message == self::decrypt($crypted,$pubKey);
    }

}
?>
