<?php
//DATABASE INFO
define('DB_HOST',                      'localhost');
define('DB_PORT',                      '3306');
define('DB_USER',                      'root');
define('DB_PASS',                      '');
define('DB_NAME',                      'blockchain');

//PEERS
define('PEERS_REQUIRED',                1);
define('PEERS_MAX',                     10);

//MINER INFO
define('MINER_MAX_SUBPROCESS',          5);
define('MINER_TIMEOUT_CLOSE',           30);
define('SHOW_INFO_SUBPROCESS',          false);
define('MIN_GAS_PRICE_TO_MINE',			0);

//PHP RUN
define('PHP_RUN_COMMAND',               'php');
//define('PHP_RUN_COMMAND',             'C:\php\php.exe');

//DEBUG
define('DISPLAY_DEBUG',                 false);
define('DISPLAY_DEBUG_LEVEL',           4); //Levels: 1-4

//DATABASE ENGINE
define('FORCE_USE_ROCKSDB',				false);

// J4F BOOTSTRAP NODE INFO
define('NODE_BOOTSTRAP',                '137.74.50.40');
define('NODE_BOOSTRAP_PORT',            6969);
define('NODE_BOOTSTRAP_TESTNET',        '137.74.50.40');
define('NODE_BOOSTRAP_PORT_TESTNET',    6969);

//Define if use subprocess or mainprocess to run J4FVM
define('J4FVM_USE_SUBPROCESS',true);

//OS INFO
define('IS_WIN',                        (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? true:false);
?>
