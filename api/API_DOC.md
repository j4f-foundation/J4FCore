**Contents**

- [JSON RPC API](#json-rpc-api)
  - [JSON-RPC Endpoint](#json-rpc-endpoint)
  - [Url Examples Explained](#url-info)
  - [JSON-RPC methods](#json-rpc-methods)
  - [JSON RPC API Reference](#json-rpc-api-reference)
      - [node_network](#node_network)
      - [node_version](#node_version)
      - [node_listening](#node_listening)
      - [node_peerCount](#node_peercount)
      - [node_syncing](#node_syncing)
      - [node_mining](#node_mining)
      - [node_hashrate](#node_hashrate)
      - [j4f_coinbase](#j4f_coinbase)
      - [j4f_accounts](#j4f_accounts)
      - [j4f_addAccount](#j4f_addAccount)
      - [j4f_blockNumber](#j4f_blocknumber)
      - [j4f_getBalance](#j4f_getbalance)
      - [j4f_getTransactionCount](#j4f_gettransactioncount)
      - [j4f_getBlockTransactionCountByHash](#j4f_getblocktransactioncountbyhash)
      - [j4f_getBlockTransactionCountByNumber](#j4f_getblocktransactioncountbynumber)
      - [j4f_sendTransaction](#j4f_sendtransaction)
      - [j4f_getBlockByHash](#j4f_getblockbyhash)
      - [j4f_getBlockByNumber](#j4f_getblockbynumber)
      - [j4f_getTransactionByHash](#j4f_gettransactionbyhash)
      - [j4f_sign](#j4f_sign)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

# JSON RPC API

[JSON](http://json.org/) is a lightweight data-interchange format. It can represent numbers, strings, ordered sequences of values, and collections of name/value pairs.

[JSON-RPC](http://www.jsonrpc.org/specification) is a stateless, light-weight remote procedure call (RPC) protocol. Primarily this specification defines several data structures and the rules around their processing. It is transport agnostic in that the concepts can be used within the same process, over sockets, over HTTP, or in many various message passing environments. It uses JSON ([RFC 4627](http://www.ietf.org/rfc/rfc4627.txt)) as data format.

## JSON-RPC Endpoint

Default JSON-RPC endpoints:

| Client | URL |
|-------|:------------:|
| HTTP |  http://NODE_IP:NODE_PORT/api/ |

### HTTP

You can start use the HTTP JSON-RPC visit url ex.
```js
http://NODE_IP:NODE_PORT/api/?method=net_version
http://NODE_IP:NODE_PORT/api/?method=getBalance&wallet=VTx00000000000000000000000000000000
```

## url Info

The examples also do not include the DOMAIN/IP & port combination which must be the last argument given to curl e.x.
127.0.0.1:6969
domain:6969

## JSON-RPC methods

* [node_network](#node_network)
* [node_version](#node_version)
* [node_listening](#node_listening)
* [node_peerCount](#node_peercount)
* [node_syncing](#node_syncing)
* [node_mining](#j4f_mining)
* [node_hashrate](#j4f_hashrate)
* [j4f_coinbase](#j4f_coinbase)
* [j4f_accounts](#j4f_accounts)
* [j4f_addAccount](#j4f_addAccount)
* [j4f_blockNumber](#j4f_blocknumber)
* [j4f_getBalance](#j4f_getbalance)
* [j4f_getTransactionCount](#j4f_gettransactioncount)
* [j4f_getBlockTransactionCountByHash](#j4f_getblocktransactioncountbyhash)
* [j4f_getBlockTransactionCountByNumber](#j4f_getblocktransactioncountbynumber)
* [j4f_sign](#j4f_sign)
* [j4f_sendTransaction](#j4f_sendtransaction)
* [j4f_getBlockByHash](#j4f_getblockbyhash)
* [j4f_getBlockByNumber](#j4f_getblockbynumber)
* [j4f_getTransactionByHash](#j4f_gettransactionbyhash)
* [j4f_sign](#j4f_sign)

## JSON RPC API Reference

#### node_version

Returns the current version of node.

##### Parameters
none

##### Returns

`INTEGER` - The current version.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_version","params":[],"id":1}'

// HTTP Request
http://NODE_IP:NODE_PORT/api/?id=1&method=node_version

// Result
{
  "id":1,
  "jsonrpc": "2.0",
  "result": "0.0.4"
}
```

***

#### node_network

Returns the current network.

##### Parameters
none

##### Returns

`String` - The current network.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_network","params":[],"id":2}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=2&method=node_network

// Result
{
  "id":2,
  "jsonrpc": "2.0",
  "result": "mainnet"
}
```

***

#### node_listening

Returns `true` if client is actively listening for network connections.

##### Parameters
none

##### Returns

`Boolean` - `true` when listening, otherwise `false`.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_listening","params":[],"id":3}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=3&method=node_listening

// Result
{
  "id":3,
  "jsonrpc":"2.0",
  "result":true
}
```

***

#### node_peerCount

Returns number of peers currently connected to the client.

##### Parameters
none

##### Returns

`INTEGER` - integer of the number of connected peers.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_peerCount","params":[],"id":4}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=4&method=node_peerCount

// Result
{
  "id":4,
  "jsonrpc": "2.0",
  "result": "5"
}
```

***

#### node_syncing

Returns an object with data about the sync status or `false`.

##### Parameters
none

##### Returns

`Object|Boolean`, An object with sync status data or `FALSE`, when not syncing:
  - `currentBlock`: `INTEGER` - The current block, same as j4f_blockNumber
  - `highestBlock`: `INTEGER` - The estimated highest block

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_syncing","params":[],"id":5}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=5&method=node_syncing
```

Result syncing
```js
{
  "id":5,
  "jsonrpc": "2.0",
  "result": {
    "currentBlock": '728',
    "highestBlock": '1202'
  }
}
```
Result Not syncing
```js
{
  "id":5,
  "jsonrpc": "2.0",
  "result": false
}
```

***

#### node_mining

Returns `true` if client is actively mining new blocks.

##### Parameters
none

##### Returns

`Boolean` - returns `true` of the client is mining, otherwise `false`.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_mining","params":[],"id":6}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=6&method=node_mining

// Result
{
  "id":6,
  "jsonrpc": "2.0",
  "result": true
}

```

***

#### node_hashrate

Returns the number of hashes per second that the node is mining with.

##### Parameters
none

##### Returns

`INTEGER` - number of hashes per second.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"node_hashrate","params":[],"id":7}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=7&method=node_hashrate

// Result
{
  "id":7,
  "jsonrpc": "2.0",
  "result": "1001231"
}

```

***

#### j4f_coinbase

Returns the client coinbase address.


##### Parameters
none

##### Returns

`String`, the current coinbase address.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_coinbase","params":[],"id":8}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=8&method=j4f_coinbase

// Result
{
  "id":8,
  "jsonrpc": "2.0",
  "result": "VTx00000000000000000000000000000000"
}
```

***

#### j4f_accounts

Returns a list of addresses owned by client.

##### Parameters
none

##### Returns

`Array of STRING`, - addresses owned by the client.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_accounts","params":[],"id":9}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=9&method=j4f_accounts

// Result
{
  "id":9,
  "jsonrpc": "2.0",
  "result": ["VTx00000000000000000000000000000000"]
}
```

***

#### j4f_addAccount

Create new wallet

##### Parameters
1. `STRING` - Password
```js
params: [
   "password":"example_password"
]
```

##### Returns

`Object` - A block object, or `null` when no block was found:

  - `wallet`: `STRING` - the wallet address.
  - `public`: `STRING`, Public key of wallet.
  - `private`: `STRING`, Private key of wallet.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_addAccount","params":["password":"example_password"],"id":10}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=10&method=j4f_addAccount&password=example_password

// Result
{
  "id":10,
  "jsonrpc": "2.0",
  "result": {
      "wallet":VTx00000000000000000000000000000000",
      "public":"PUBLIC KEY....",
      "private":"PRIVATE KEY...."
  }
}
```

***

#### j4f_blockNumber

Returns the number of most recent block.

##### Parameters
none

##### Returns

`INTEGER` - integer of the current block number the client is on.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_blockNumber","params":[],"id":11}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=11&method=j4f_blockNumber

// Result
{
  "id":11,
  "jsonrpc": "2.0",
  "result": "94"
}
```

***

#### j4f_getBalance

Returns the balance of the account of given address.

##### Parameters

1. `STRING` - address to check for balance.

```js
params: [
   "wallet":"VTx00000000000000000000000000000000"
]
```

##### Returns

`INTEGER` - integer of the current balance in wei.


##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getBalance","params":["wallet":"VTx00000000000000000000000000000000"],"id":12}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=12&method=j4f_getBalance&wallet=VTx00000000000000000000000000000000

// Result
{
  "id":12,
  "jsonrpc": "2.0",
  "result": "1236749512346779.12412440"
}
```

***

#### j4f_getPendingBalance

Returns the pending balance of the account of given address.

##### Parameters

1. `STRING` - address to check for balance.

```js
params: [
   "wallet":"VTx00000000000000000000000000000000"
]
```

##### Returns

`INTEGER` - integer of the current balance in wei.


##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getPendingBalance","params":["wallet":"VTx00000000000000000000000000000000"],"id":13}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=13&method=j4f_getPendingBalance&wallet=VTx00000000000000000000000000000000

// Result
{
  "id":13,
  "jsonrpc": "2.0",
  "result": "1236749512346779.12412440"
}
```

***

#### j4f_getTransactionCount

Returns the number of transactions *sent* from an address.

##### Parameters

1. `STRING` - address.

```js
params: [
   "wallet":"VTx00000000000000000000000000000000"
]
```

##### Returns

`Array`
    `INTEGER` - integer of the number of transactions send from this address.
    `STRING` - integer of total amount of transactions send from this address.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getTransactionCount","params":["wallet":"VTx00000000000000000000000000000000"],"id":14}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=14&method=j4f_getTransactionCount&wallet=VTx00000000000000000000000000000000

// Result
{
  "id":14,
  "jsonrpc": "2.0",
  "result": [32,"7.99977350"]
}
```

***

#### j4f_getBlockTransactionCountByHash

Returns the number of transactions in a block from a block matching the given block hash.

##### Parameters

1. `STRING` - hash of a block.

```js
params: [
   "hash":"00005cd741f6691aaece42e0ede9ec66bd68098a47d7d1f72f4f953e3fe02773"
]
```

##### Returns

`INTEGER` - integer of the number of transactions in this block.


##### Example
```js
// Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getBlockTransactionCountByHash","params":["hash":"00005cd741f6691aaece42e0ede9ec66bd68098a47d7d1f72f4f953e3fe02773"],"id":15}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=15&method=j4f_getBlockTransactionCountByHash&hash=00005cd741f6691aaece42e0ede9ec66bd68098a47d7d1f72f4f953e3fe02773

// Result
{
  "id":15,
  "jsonrpc": "2.0",
  "result": "15"
}
```

***

#### j4f_getBlockTransactionCountByNumber

Returns the number of transactions in a block matching the given block number.


##### Parameters

1. `INTEGER|TAG` - integer of a block number, or the string `"earliest"`, `"latest"` or `"pending"`, as in the [default block parameter](#the-default-block-parameter).

```js
params: [
   "height":"542"
]
```

##### Returns

`INTEGER` - integer of the number of transactions in this block.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getBlockTransactionCountByNumber","params":["height":"542"],"id":16}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=16&method=j4f_getBlockTransactionCountByNumber&height=542

// Result
{
  "id":16,
  "jsonrpc": "2.0",
  "result": "10"
}
```

***

#### j4f_sendTransaction

Creates new message call transaction

##### Parameters

1. `Object` - The transaction object
  - `from`: `STRING` - The address the transaction is send from.
  - `to`: `STRING` - The address the transaction is directed to.
  - `password`: `STRING` - The password address from.
  - `amount`: `INTEGER` - Integer of the value sent with this transaction
  - `fee`: `STRING` - Fee of transaction - Accepted values `high` `medium` `low`

```js
params: [{
  "from": "VTxed481ddab0bc5acefbaa67a2f35f8839",
  "to": "VTx31b9ad4ac95a8f4d4ba7f4c5bb908e20",
  "password": "PASSWORD_WALLET_FROM",
  "amount": "1",
  "fee": "high"
}]
```

##### Returns

`STRING` - the transaction hash, or the zero hash if the transaction is not yet available.

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_sendTransaction","params":[{see above}],"id":17}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=17&method=j4f_sendTransaction&params...[{see above}]

// Result
{
  "id":17,
  "jsonrpc": "2.0",
  "result": "f2f54a0e6beac9d117c9c1d3287512947c2c17329ae4dd8b5bb89900fc70e499"
}
```

***

#### j4f_getBlockByHash

Returns information about a block by hash.

##### Parameters

1. `STRING` - Hash of a block.
2. `Boolean` - If `true` it returns the full transaction objects, if `false` only the hashes of the transactions.

```js
params: [
   "hash":"0000cdd334c36ebc602462664b52c3d5df61f5e12b761695b164267bc789e0c5",
   "transactions":false
]
```

##### Returns

`Object` - A block object, or `null` when no block was found:

  - `height`: `INTEGER` - the block number. `null` when its pending block.
  - `hash`: `STRING`, hash of the block. `null` when its pending block.
  - `parentHash`: `STRING`, hash of the parent block.
  - `nonce`: `INTEGER`, Integer generated proof-of-work. `null` when its pending block.
  - `transactionsRoot`: `STRING`, the root of the transaction trie of the block.
  - `miner`: `STRING`, the address of the beneficiary to whom the mining rewards were given.
  - `difficulty`: `INTEGER` - integer of the difficulty for this block.
  - `totalDifficulty`: `INTEGER` - integer of the total difficulty of the chain until this block.
  - `timestamp`: `INTEGER` - the unix timestamp for when the block was collated.
  - `transactions`: `Array` - Array of transaction objects, or transaction hashes depending on the last given parameter.


##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getBlockByHash","params":["0000cdd334c36ebc602462664b52c3d5df61f5e12b761695b164267bc789e0c5", "transactions":false],"id":18}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=18&method=j4f_getBlockByHash&hash=0000cdd334c36ebc602462664b52c3d5df61f5e12b761695b164267bc789e0c5&transactions=0

// Result
{
"id":18,
"jsonrpc":"2.0",
"result": {
    "height": "100",
    "hash": "0000cdd334c36ebc602462664b52c3d5df61f5e12b761695b164267bc789e0c5",
    "parentHash": "null",
    "nonce": "13785",
    "transactionsRoot": "210059b75e34048962171df100572d45a912d27a7c3c44e5708d9a0fe1fac38b",
    "miner": "VTx31b9ad4ac95a8f4d4ba7f4c5bb908e20",
    "difficulty": "1420552106474293933196125558253485924058694448219036746811467852730196165",
    "totalDifficulty":  "1766847064778384329583297500742918515827483896875618958121606201292619775",
    "timestamp": "1545881112",
    "transactions": [{...},{ ... }]
  }
}
```

***

#### j4f_getBlockByNumber

Returns information about a block by block number.

##### Parameters

1. `INTEGER|TAG` - integer of a block number
2. `Boolean` - If `true` it returns the full transaction objects, if `false` only the hashes of the transactions.

```js
params: [
   "height":"100"
   "transactions":false
]
```

##### Returns

See [j4f_getBlockByHash](#j4f_getblockbyhash)

##### Example
```js
// JSON-RPC Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getBlockByNumber","params":["height":"100", "transactions":false],"id":19}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=19&method=j4f_getBlockByNumber&height=100&transactions=1
```

Result see [j4f_getBlockByHash](#j4f_getblockbyhash)

***

#### j4f_getTransactionByHash

Returns the information about a transaction requested by transaction hash.


##### Parameters

1. `STRING` - hash of a transaction

```js
params: [
   "hash":"7378fe81f12248f7759a0535601ef2b5c9398a9956f9b254b06d3810e456687f"
]
```

##### Returns

`Object` - A transaction object, or `null` when no transaction was found:

  - `blockHash`: `STRING`, hash of the block where this transaction was in. `null` when its pending.
  - `blockHeight`: `STRING`, height of the block where this transaction was in. `null` when its pending.
  - `hash`: `STRING`, hash of the transaction.
  - `from`: `STRING` address of the sender.
  - `to`: `STRING`, address of the receiver.
  - `fee`: `INTEGER` - transactions fee.
  - `amount`: `INTEGER` - value transferred.
  - `signature`: `STRING` - signature

##### Example
```js
// Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_getTransactionByHash","params":["hash":"7378fe81f12248f7759a0535601ef2b5c9398a9956f9b254b06d3810e456687f"],"id":20}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=20&method=j4f_getTransactionByHash&hash=7378fe81f12248f7759a0535601ef2b5c9398a9956f9b254b06d3810e456687f

// Result
{
  "jsonrpc":"2.0",
  "id":20,
  "result":{
    "blockHash":"0000cdd334c36ebc602462664b52c3d5df61f5e12b761695b164267bc789e0c5",
    "blockHeight":"1000",
    "hash":"7378fe81f12248f7759a0535601ef2b5c9398a9956f9b254b06d3810e456687f",
    "from":"VTx00000000000000000000000000000000",
    "to":"VTx00000000000000000000000000000000",
    "fee":"0.00000900",
    "amount":"429",
    "signature":"ILPjd7CjCJj36NfoCg2ojY8gZyXSUKYCy3iiKn1H52WT+TZyMmgIwrVZ+BKNv+qQ1Qo+dmA46wi5X72L9jthKFnhbfRs/7xpQP8W9sglbrIRBhTZ0HqB70R6yg5flHgTAIPKKuO7QKW+5PYiQKaSsSztMaeryMe619BRyMeQI7/qRScc/AZUzshQdDqedjkb+2eiVNdQzvNn0oKQ5A0kt7Txd117vRXYxkkdcHl7WMTnBiwlyM0luTBSe6hGZRXD5MqOSj8p5zGKveIPzyxdbwX77e8KB/bLNuw8rGaZwdg2jYj9VjK1ILaiRsyhWi6HWvjLCpECY0QNlynbO4MDBw=="
  }
}
```

#### j4f_sign

Check if cant sign this wallet with this password


##### Parameters

1. `STRING` - Wallet to check sign
2. `STRING` - Password of wallet

```js
params: [
   "wallet":"VTx00000000000000000000000000000000",
   "password":"EXAMPLE_PASSWORD"
]
```

##### Returns

`String` - Message informing if it was possible to sign

##### Example
```js
// Request
curl -X POST --data '{"jsonrpc":"2.0","method":"j4f_sign","params":["wallet":"VTx00000000000000000000000000000000","password":"EXAMPLE_PASSWORD"],"id":20}'

//HTTP Request
http://NODE_IP:NODE_PORT/api/?id=20&method=j4f_sign&wallet=VTx00000000000000000000000000000000&password=EXAMPLE_PASSWORD

// Result
{
  "jsonrpc":"2.0",
  "id":20,
  "result":{
    "ILPjd7CjCJj36NfoCg2ojY8gZyXSUKYCy3iiKn1H52WT+TZyMmgIwrVZ+BKNv+qQ1Qo+dmA46wi5X72L9jthKFnhbfRs/7xpQP8W9sglbrIRBhTZ0HqB70R6yg5flHgTAIPKKuO7QKW+5PYiQKaSsSztMaeryMe619BRyMeQI7/qRScc/AZUzshQdDqedjkb+2eiVNdQzvNn0oKQ5A0kt7Txd117vRXYxkkdcHl7WMTnBiwlyM0luTBSe6hGZRXD5MqOSj8p5zGKveIPzyxdbwX77e8KB/bLNuw8rGaZwdg2jYj9VjK1ILaiRsyhWi6HWvjLCpECY0QNlynbO4MDBw=="
  }
}
```
