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

use React\Socket\ConnectionInterface;

class Socket {

	/**
     * Send message to socket
     *
     * @param string $ip
     * @param string $port
     * @param array $data
	 * @return bool
     */

    public static function sendMessage(string $ip='127.0.0.1',string $port='6969', array $data = []) : bool {
		if (!self::isAlive($ip,$port))
			return false;

		$loop = React\EventLoop\Factory::create();
		$connector = new React\Socket\Connector($loop, array(
		    'timeout' => 5.0
		));

		$dataParsed = @json_encode($data);
		$promise = $connector->connect($ip.':'.$port)->then(function (React\Socket\ConnectionInterface $connection) use (&$dataParsed) {
			$connection->write($dataParsed);
			$connection->end();
		});
		$loop->run();
		$promise->cancel();
		return true;
    }

	/**
     * Send message to socket and get return message
     *
     * @param string $ip
     * @param string $port
     * @param array $data
	 * @param int $data
	 * @return array|null
     */
    public static function sendMessageWithReturn(string $ip='127.0.0.1', string $port='6969', array $data, int $timeout=5) : array {
		if (!self::isAlive($ip,$port))
			return ['socket server offline'];

		$loop = React\EventLoop\Factory::create();
		$connector = new React\Socket\Connector($loop, array(
		    'timeout' => $timeout
		));

		$dataParsed = @json_encode($data);
		$dataFromPeer = '';
		$return = [];

		//Delayed Stop Function
		$currentTime = 0;
		$breakSocket = false;
		$loop->addPeriodicTimer(1, function () use ($loop, &$currentTime, &$breakSocket, &$timeout) {
			$currentTime++;
			if ($currentTime >= $timeout || $breakSocket)
				$loop->stop();
		});

		$promise = $connector->connect($ip.':'.$port)->then(function (React\Socket\ConnectionInterface $connection) use (&$dataParsed, &$return, &$dataFromPeer,&$breakSocket) {
			$connection->write($dataParsed);

			$connection->on('data', function($rawData) use ($connection, &$dataFromPeer){
				$dataFromPeer .= $rawData;
			});
			$connection->on('close', function () use ($connection, &$dataFromPeer, &$return,&$breakSocket) {
				$return = @json_decode($dataFromPeer,true);
				$breakSocket = true;
			});

		});
		$loop->run();
		$promise->cancel();
		return (is_array($return)) ? $return:[];
    }

	/**
	 * Check if socket its alive
	 *
	 * @param string $ip
	 * @param string $port
	 * @return bool
	 */
	public static function isAlive(string $ip='127.0.0.1',string $port='6969', int $timeout = 2) : bool {
		$fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
	    if ($fp != null && @is_resource($fp)) {
			@fclose($fp);
			return true;
		}
	    else
			return false;
	}

}
?>
