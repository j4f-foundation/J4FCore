![Just4Fun](https://j4f.dev/images/j4ficon.png) Just4Fun

PHP Blockchain from scratch with Smart Contracts

```
PoW sha3-256d + sha3-512
Total coins: Unlimited
Blocks every: Mainnet ?? (average) | Testnet: 5 sec (average)
```

![php >= 7.3](https://img.shields.io/badge/php-%3E%3D%207.3-blue)
![GPL 3.0](https://img.shields.io/badge/license-GPL%203.0-orange)

# Requirements

- Time synchronized with NTP ([NTP Pool](https://www.pool.ntp.org))
([How sync time](https://www.digitalocean.com/community/tutorials/how-to-set-up-time-synchronization-on-ubuntu-16-04))
- Open ports for p2p connection
- ReactPHP [See more](https://reactphp.org/)
- OpenSSL [Windows: OpenSSL v1.1.1e](https://slproweb.com/products/Win32OpenSSL.html)
- MariaDB Server (Recommended with [RocksDB Engine](https://github.com/j4f-foundation/J4FCore/blob/master/RocksDB.md))
- PHP 7.3 or higher
- PHP Extensions:
  - php_mysqli
  - php_bcmath

# Links
[Explorer TESTNET](https://testnet.j4f.dev)

[Wallet Web](https://wallet.j4f.dev)

[Smart Contract Funity Compiler](https://wallet.j4f.dev/compiler)

[JSON-RPC/HTTP API](https://github.com/j4f-foundation/J4FCore/wiki/API-JSON-RPC-HTTP)

# Discord

If you want to talk about the development of blockchain or need support to run a node, connect to Discord Server!

[Discord](https://discord.gg/kcSGSaa)

# How run
- Clone repository
- Install react with composer
	- composer require react/react:^1.0
- Create a MySQL database UTF8 RocksDB
- Edit CONFIG.php and set MySQL info & PHP Run command
- Navigate into bin folder and start node

For miner node:
```
./node_miner.sh
```

For viewer node:
```
./node_viewer.sh
```

# NODE available arguments
|ARGUMENT   	|Description   							|
|---			|---									|
|user <*name*>   		|Set the node name   				|
|ip <*ip*>   			|Set the IP that the node will use   	|
|port <*port*>   		|Set the port that the node will use   	|
|miner   		|Activate mining mode   				|
|testnet   		|Connect to TESTNET network   			|
|sanity <*num blocks to remove*>   		|Sanity Blockchain			   			|

Examples of use:
```
php client.php -u USER1 -ip 0.0.0.0 -port 6969
php client.php -u USER1 -ip 0.0.0.0 -port 6969 -testnet
php client.php -u USER1 -ip 0.0.0.0 -port 6969 -miner
php client.php -u USER1 -ip 0.0.0.0 -port 6969 -miner -testnet
php client.php -u USER1 -ip 0.0.0.0 -port 6969 -testnet -sanity 100
```

# CLI CLIENT

Examples of use:
```
php cli.php
```

# Contribute
Anyone is welcome to contribute to J4FCore!
If you have a fix or code change, feel free to submit it as a pull request directly to the "master" branch.

# Blockchain under construction
Currency is still in early development phase, it may not be stable 100%
```
The mainnet has not yet been released.
```

# Development Fund
`ETH` 0x33c6cea9136d30071c1b015cc9a2b4d1ad17848d
