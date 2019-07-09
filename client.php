<?php
// MIT License
//
// Copyright (c) 2019 Just4Fun
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

include(__DIR__.'/src/init.inc.php');

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

    $gossip = new Gossip($db, $arguments['user'],$arguments['ip'],$arguments['port'], $enable_mine, $make_genesis, $bootstrap_node, $isTestNet);
    if (isset($arguments['peer-ip']) && isset($arguments['peer-port'])) {
        $gossip->_addPeer($arguments['peer-ip'],$arguments['peer-port']);
	}
	
	$sanityBlockchain = false;
	if (isset($arguments['sanity'])) {
		$sanityBlockchain = true;
		$gossip->SanityFromBlockHeight($arguments['sanity']);
	}

	//Ejecutamos el Loop del Gossip
	if (!$sanityBlockchain)
		$gossip->loop();
		
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