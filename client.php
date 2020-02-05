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

include(__DIR__.'/src/init.inc.php');

require __DIR__ . '/vendor/autoload.php';

use React\Socket\ConnectionInterface;

if (count($argv) > 1) {

    $argvParser = new ArgvParser();
    $parse_argv = "";
    foreach ($argv as $value) {
        $parse_argv .= " ".$value;
    }
    $arguments = $argvParser->parseConfigs($parse_argv);

    if (!isset($arguments['user']))
        exit('You must specify a username');

    if (!isset($arguments['ip']))
        exit('You must specify the IP');

    if (!isset($arguments['port']))
        exit('You must specify a port');

    //This argument enable minerSubprocess
    $enable_mine = false;
    if (isset($arguments['miner']))
        $enable_mine = true;

    //Define if make genesis block
    $make_genesis = false;
    if (isset($arguments['genesis']))
        $make_genesis = true;

    //Define if this node its a bootstrapNode
    $bootstrap_node = false;
    if (isset($arguments['bootstrap_node']))
        $bootstrap_node = true;

    //Check if use testnet
    $isTestNet = false;
    if (isset($arguments['testnet']))
        $isTestNet = true;

	//Check if want sanity blockchain
	$sanityBlockchain = -1;
	if (isset($arguments['sanity'])) {
		$sanityBlockchain = $arguments['sanity'];
	}

    $gossip = new Gossip($db, $arguments['user'],$arguments['ip'],$arguments['port'], $enable_mine, $make_genesis, $bootstrap_node, $isTestNet, $sanityBlockchain);
    if (isset($arguments['peer-ip']) && isset($arguments['peer-port'])) {
        $gossip->_addPeer($arguments['peer-ip'],$arguments['peer-port']);
	}

} else {
    Display::ClearScreen();
    echo "Available arguments:".PHP_EOL.PHP_EOL;
    echo "user          Set the node user name                  - REQUIRED".PHP_EOL;
    echo "ip            Set the IP that the node will use       - REQUIRED".PHP_EOL;
    echo "port          Set the port that the node will use     - REQUIRED".PHP_EOL;
    echo "peer-ip       Set an IP of a nearby node".PHP_EOL;
    echo "peer-port     Set the port of a nearby node".PHP_EOL;
	echo "miner         Activate mining mode".PHP_EOL.PHP_EOL;
	echo "sanity        Sanity Blockchain and remove X blocks".PHP_EOL.PHP_EOL;

    echo "Examples of use: ".PHP_EOL;
    echo "php client.php -u user -ip 0.0.0.0 -port 8080".PHP_EOL;
	echo "php client.php -u user -ip 0.0.0.0 -port 8080 -mine".PHP_EOL;
	echo "php client.php -u user -ip 0.0.0.0 -port 8080 -sanity 100".PHP_EOL;
}
?>
