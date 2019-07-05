<?php

include('CONFIG.php');
include('src/DB.php');
include('src/ColorsCLI.php');
include('src/Display.php');
include('src/Subprocess.php');
include('src/BootstrapNode.php');
include('src/ArgvParser.php');
include('src/Tools.php');
include('src/Wallet.php');
include('src/Block.php');
include('src/Blockchain.php');
include('src/Gossip.php');
include('src/Key.php');
include('src/Pki.php');
include('src/PoW.php');
include('src/Transaction.php');
include('src/GenesisBlock.php');
include('src/Peer.php');
include('src/Miner.php');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60" >
    <title>Most eXpensive Coin Node</title>
    <style>body, h1, html{margin:0;padding:0;} h1{font-size:100%;font-weight:400;} html{box-sizing:border-box;background-color:#fff;font-size:16px;min-width:300px;overflow:hidden;text-rendering:optimizeLegibility;-webkit-text-size-adjust:100%;-moz-text-size-adjust:100%;-ms-text-size-adjust:100%;text-size-adjust:100%;} body{font-family:Helvetica, Arial, sans-serif;color:#4a4a4a;font-size:1rem;font-weight:500;line-height:1.5;} *,::after,::before{box-sizing:inherit;} section{display:block;} span{font-style:inherit;font-weight:inherit;} small{font-style:inherit;font-weight:inherit;color:#868e96;font-size:1rem;} strong{color:#363636;font-weight:700;} .title:not(:last-child){margin-bottom:1.5rem;} .container{position:relative;margin:0 auto;} .field.is-grouped{display:flex;justify-content:flex-start;} .field.is-grouped>.control{flex-shrink:0;} .field.is-grouped>.control:not(:last-child){margin-bottom:0;margin-right:.75rem;} .field.is-grouped.is-grouped-multiline{flex-wrap:wrap;} .field.is-grouped.is-grouped-multiline>.control:last-child, .field.is-grouped.is-grouped-multiline>.control:not(:last-child){margin-bottom:.75rem;} .field.is-grouped.is-grouped-multiline:last-child{margin-bottom:-.75rem;} .control{font-size:1rem;position:relative;text-align:left;} .tags{align-items:center;display:flex;flex-wrap:wrap;justify-content:flex-start;} .tags .tag{margin-bottom:.5rem;} .tags .tag:not(:last-child){margin-right:.5rem;} .tags:last-child{margin-bottom:-.5rem;} .tags.has-addons .tag{margin-right:0;} .tags.has-addons .tag:not(:first-child){border-bottom-left-radius:0;border-top-left-radius:0;} .tags.has-addons .tag:not(:last-child){border-bottom-right-radius:0;border-top-right-radius:0;} .tag:not(body){align-items:center;background-color:#f5f5f5;border-radius:4px;color:#4a4a4a;display:inline-flex;font-size:.75rem;height:2em;justify-content:center;line-height:1.5;padding-left:.75em;padding-right:.75em;white-space:nowrap;} .tag:not(body).is-light{background-color:#f5f5f5;color:#363636;} .tag-primary{background-color:#0275d8 !important;color:#fff;} .tag-inverse{background-color:#292b2c !important;color:#fff;} .tag-info{background-color:#17a2b8 !important;color:#fff;} .tag-danger{background-color:#dc3545 !important;color:#fff;} .tag-success{background-color:#28a745 !important;color:#fff;} .tag-warning{background-color:#f0ad4e !important;color:#fff;} .tag-main{background-color:#b66dff !important;color:#fff;} .tag-avg{background-color:#6c757d !important;color:#fff;} .title{word-break:break-word;color:#363636;font-size:2rem;font-weight:600;line-height:1.125;} .node{align-items:stretch;display:flex;flex-direction:column;justify-content:space-between;} a{text-decoration:none;color:inherit;} .node.mainnet{background:linear-gradient(45deg, #5c5ff7, #9737fb) !important;color:#f5f5f5;} .node.testnet{background:linear-gradient(45deg, #2227fb, #37b0fb) !important;color:#f5f5f5;} .node strong{color:inherit;} .node .title{color:#f5f5f5;} .node.fh .node-b{align-items:center;display:flex;} .node.fh .node-b>.container{flex-grow:1;flex-shrink:1;} .node.fh{min-height:100vh;} .node-b{flex-grow:1;flex-shrink:0;padding:3rem 1.5rem;} @media screen and (min-width:1088px){.container{max-width:960px;width:960px;} } @media screen and (min-width:1280px){.container{max-width:1152px;width:1152px;} } @media screen and (min-width:1472px){.container{max-width:1344px;width:1344px;} }</style>
</head>
<body>

<?php
$chaindata = new DB();
$lastBlock = $chaindata->GetLastBlock(false);
$currentBlockHeight = $lastBlock['height'];

$peers = count($chaindata->GetAllPeers());

$network = 'mainnet';
$nodeConfig = $chaindata->GetAllConfig();
$miner = false;
$hashrate = 'No info';
$explorerURL = 'https://blockchain.mataxetos.es/block/height/';
$isSyncing = false;
$nodeVersion = 'No info';
if ($nodeConfig != null) {
    $network = $nodeConfig['network'];
    $nodeVersion = $nodeConfig['node_version'];

    if ($network == 'testnet')
        $explorerURL = 'https://testnet.mataxetos.es/block/height/';

    if ($nodeConfig['miner'] == 'on') {
        $miner = true;
        $hashrate = $nodeConfig['hashrate'];
    }

    if ($nodeConfig['syncing'] == 'on') {
        $isSyncing = true;
    }
}

$diffAvg = Blockchain::checkDifficulty($chaindata,null,($network == 'testnet'));
$difficulty = $diffAvg[0];
$avgTimeBlock = $diffAvg[1];

$ageBlock = Tools::datetimeDiff(date('Y-m-d H:i:s', $lastBlock['timestamp_end_miner']),date('Y-m-d H:i:s', Tools::GetGlobalTime()));
$ageBlockMessage = Tools::getAge($ageBlock);
?>

<section class="node <?= $network; ?> fh">
    <div class="node-b">
        <div class="container">
            <h1 class="title">MXC Node <small>on <?= $network; ?></small></h1>

            <div class="field is-grouped is-grouped-multiline">
                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-info">Current Block</strong>
                        <span class="tag is-light"><a href="<?= $explorerURL.$currentBlockHeight; ?>" target="_blank"><?= $currentBlockHeight; ?></a></span>
                    </div>
                </div>
                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-success">Version</strong>
                        <span class="tag is-light"><?= $nodeVersion; ?></span>
                    </div>
                </div>
                <?php
                if ($miner && !$isSyncing) {
                    ?>
                    <div class="control">
                        <div class="tags has-addons">
                            <strong class="tag tag-warning">Minning</strong>
                            <span class="tag is-light"><?= $hashrate; ?></span>
                        </div>
                    </div>
                    <?php
                } else if ($miner && $isSyncing) {
                    ?>
                    <div class="control">
                        <div class="tags has-addons">
                            <strong class="tag tag-warning">Minning</strong>
                            <span class="tag is-light">Syncing</span>
                        </div>
                    </div>
                    <?php
                }
                ?>

                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-danger">Difficulty</strong>
                        <span class="tag is-light"><?= $difficulty; ?></span>
                    </div>
                </div>

                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-main">Peers</strong>
                        <span class="tag is-light"><?= $peers; ?></span>
                    </div>
                </div>

                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-avg">Avg Time block</strong>
                        <span class="tag is-light"><?= number_format(($avgTimeBlock/60),2); ?> min</span>
                    </div>
                </div>

                <div class="control">
                    <div class="tags has-addons">
                        <strong class="tag tag-inverse">Last Block Time</strong>
                        <span class="tag is-light"><?= $ageBlockMessage; ?></span>
                    </div>
                </div>

                <?php if (isset($_GET['debug'])) { ?>
                <div>
                    <textarea style="margin: 0px; width: 985px; height: 766px;"><?= @file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."node_log"); ?></textarea>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
</body>
</html>
