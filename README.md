# MXCoin
> just for fun

PHP cryptocurrency from scratch

- PoW sha256d + sha512
- Total coins: 56,240,067 MXC
- Blocks every: Mainnet 8 min (average) | Testnet: 20 sec (average)
- Halving: every 250000 blocks decrease reward by half

# Requisites

- Time synchronized with NTP ([NTP Pool](https://www.pool.ntp.org)) 
(How sync time [See more](https://www.digitalocean.com/community/tutorials/how-to-set-up-time-synchronization-on-ubuntu-16-04))
- Open ports for p2p connection
- Apache web server
- OpenSSL
- MySQL Server
- PHP 7.0 or higher
- PHP Extensions:
  - php_mysqli
  - php_bcmath
  - php_curl
  
# TODO
- ~~Migrate form SQLite to MySQL~~
- ~~Add Merkle Tree~~
- ~~Improve Explorer~~
- ~~API JSON-RPC / HTTP~~
- ~~Make testnet~~
- ~~Multithread~~
- ~~CLI Wallet~~
- ~~GUI Wallet~~
- Improve Smart Contract with MXDity
- Improve peer system
- Improve sanity system
  
# Links
[Explorer TESTNET](https://testnet.mataxetos.es)

[Wallet Web](https://wallet.mataxetos.es)

[JSON-RPC/HTTP API](https://github.com/mataxetos/MXC/wiki/API-JSON-RPC-HTTP)

# IRC

If you want to talk about the development of currency or need support to run a node, connect to the IRC network!

- Server: irc.mataxetos.es
- Channel: #mxc

[WebChat IRC](https://kiwiirc.com/nextclient/irc.mataxetos.es/mxc/)

# How run
- Clone repository on root website folder
- Create a MySQL database UTF8
- Edit CONFIG.php and set MySQL info & PHP Run command
- Edit apache2.conf (Default: /etc/apache2/apache2.conf) and change:
```
    <Directory /var/www/>
    ...
    AllowOverride None -> AllowOverride All
    ...
    </Directory>
```

- Navigate into bin folder

For miner node:
```
./node_miner.sh
```

For viewer node:
```
./node_viewer.sh
```
  
# CLIENT available arguments
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
php client.php -u USER1 -ip 0.0.0.0 -port 8080
php client.php -u USER1 -ip 0.0.0.0 -port 8080 -miner
php client.php -u USER1 -ip 0.0.0.0 -port 8080 -miner -testnet
php client.php -u USER1 -ip 0.0.0.0 -port 8080 -testnet -sanity 100
```

# Contribute
Anyone is welcome to contribute to MXCoin! 
If you have a fix or code change, feel free to submit it as a pull request directly to the "master" branch.

# Cryptocurrency under construction
Currency is still in early development phase, it may not be stable 100%

# Donations
ETH: 0x33c6cea9136d30071c1b015cc9a2b4d1ad17848d
