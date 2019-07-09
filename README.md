![Just4Fun](https://j4f.dev/images/just4fun_96_new.png) Just4Fun

PHP cryptocurrency from scratch

```
PoW sha3-256d + sha3-512 
Total coins: 56,240,067 J4F
Blocks every: Mainnet 8 min (average) | Testnet: 20 sec (average)
Halving: every 250000 blocks decrease reward by half
```

# Requisites

- Time synchronized with NTP ([NTP Pool](https://www.pool.ntp.org)) 
(How sync time [See more](https://www.digitalocean.com/community/tutorials/how-to-set-up-time-synchronization-on-ubuntu-16-04))
- Open ports for p2p connection
- Apache web server
- OpenSSL
- MySQL Server
- PHP 7.1 or higher
- PHP Extensions:
  - php_mysqli
  - php_bcmath
  - php_curl
  
# TODO
- [x] Migrate form SQLite to MySQL/MariaDB
- [x] Blockchain Explorer
- [x] API JSON-RPC / HTTP
- [x] Multithread miner
- [x] Testnet
- [x] CLI Wallet
- [x] GUI Wallet
- [ ] Improve Smart Contract and Funity Language
- [ ] Improve peer system
- [ ] Improve sanity system

# Links
[Explorer TESTNET](https://testnet.j4f.dev)

[Wallet Web](https://wallet.j4f.dev)

[JSON-RPC/HTTP API](https://github.com/j4f-foundation/J4FCore/wiki/API-JSON-RPC-HTTP)

# Discord

If you want to talk about the development of currency or need support to run a node, connect to Discord Server!

[Discord](https://discord.gg/kcSGSaa)

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
Anyone is welcome to contribute to J4FCore! 
If you have a fix or code change, feel free to submit it as a pull request directly to the "master" branch.

# Cryptocurrency under construction
Currency is still in early development phase, it may not be stable 100%
```
The mainnet has not yet been released.
The forecast for its launch is 4-6 months
```

# Donations
`ETH` 0x33c6cea9136d30071c1b015cc9a2b4d1ad17848d