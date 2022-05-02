<?php

#test send ether via hardhat


/*

composer require web3p/web3.php
composer require web3p/ethereum-tx

*/

require 'vendor/autoload.php';


use Web3\Contract;
use Web3\Utils;
use Web3p\EthereumTx\Transaction;

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '/.env');
// $dotenv->load();

#erc1155 - use contract address from deployed contract in hardhat
$contractAddress = "";


#to address, use hardhat account 1
$destinationAddress = "";

#from, use hardhat account 0
$fromAddress = "";
$fromAccountPrivateKey = "";



$secondsToWaitForReceiptString = 300;
$secondsToWaitForReceipt = intval($secondsToWaitForReceiptString);

$factorToMultiplyGasEstimateString = 50000;
$factorToMultiplyGasEstimate = intval($factorToMultiplyGasEstimateString);

$amountIn = Utils::toWei('50000000', 'ether');
$amountInWholeNumber = Utils::toBn($amountIn);

#hardhat chainID
$chainId = 31337;


#make sure this abi path location exists
$abi = file_get_contents(__DIR__ . '/resources/erc1155.abi.json');


$provider = "http://localhost:8545";



################

$contract = new Contract($provider, $abi);

$eth = $contract->eth;

$contract->at($contractAddress)->call('balanceOf', $fromAddress, 0, function ($err, $results) use ($contract) {
    if ($err !== null) {
        echo $err->getMessage() . PHP_EOL;
    }
    if (isset($results)) {
        foreach ($results as &$result) {
            $bn = Utils::toBn($result);
            echo 'BEFORE fromAccount balance ' . $bn->toString() . PHP_EOL;
        }
    }
});

$contract->at($contractAddress)->call('balanceOf', $destinationAddress, 0, function ($err, $results) use ($contract) {
    if ($err !== null) {
        echo $err->getMessage() . PHP_EOL;
    }
    if (isset($results)) {
        foreach ($results as &$result) {
            $bn = Utils::toBn($result);
            echo 'BEFORE destinationAddress balance ' . $bn->toString() . PHP_EOL;
        }
    }
});


$rawTransactionData = '0x' . $contract->at($contractAddress)->getData('mint', $destinationAddress, 0, 1, "0x000");

$transactionCount = null;

$eth->getTransactionCount($fromAddress, function ($err, $transactionCountResult) use (&$transactionCount) {
    if ($err) {
        echo 'getTransactionCount error: ' . $err->getMessage() . PHP_EOL;
    } else {
        $transactionCount = $transactionCountResult;
    }
});
echo "\$transactionCount=$transactionCount" . PHP_EOL;


$transactionParams = [
    'nonce' => "0x" . dechex($transactionCount->toString()),
    'from' => $fromAddress,
    'to' => $destinationAddress,
    'amount' => 1,
    'gas' => '0x' . dechex(8000000),
    'chainId' => $chainId,
    'value' => '0x000',
    'data' => $rawTransactionData
];

$estimatedGas = null;
$eth->estimateGas($transactionParams, function ($err, $gas) use (&$estimatedGas) {
    if ($err) {
        echo 'estimateGas error: ' . $err->getMessage() . PHP_EOL;
    } else {
        $estimatedGas = $gas;
    }
});
echo "\$estimatedGas=$estimatedGas" . PHP_EOL;

$gasPriceMultiplied = hexdec(dechex($estimatedGas->toString())) * $factorToMultiplyGasEstimate;
echo "\$gasPriceMultiplied=$gasPriceMultiplied" . PHP_EOL;
$transactionParams['gasPrice'] = '0x' . dechex($gasPriceMultiplied);

$tx = new Transaction($transactionParams);
$signedTx = '0x' . $tx->sign($fromAccountPrivateKey);
$txHash = null;

$eth->sendRawTransaction($signedTx, function ($err, $txResult) use (&$txHash) {
    if ($err) {
        echo 'transaction error: ' . $err->getMessage() . PHP_EOL;
    } else {
        $txHash = $txResult;
    }
});

echo "\$txHash=$txHash" . PHP_EOL;

$txReceipt = null;
echo "Waiting for transaction receipt";

for ($i = 0; $i <= $secondsToWaitForReceipt; $i++) {

    echo '.';

    $eth->getTransactionReceipt($txHash, function ($err, $txReceiptResult) use (&$txReceipt) {
        if ($err) {
            echo 'getTransactionReceipt error: ' . $err->getMessage() . PHP_EOL;
        } else {
            $txReceipt = $txReceiptResult;
        }
    });

    if ($txReceipt) {
        echo PHP_EOL;
        break;
    }

    sleep(1);

}

$txStatus = $txReceipt->status;
echo "\$txStatus=$txStatus" . PHP_EOL;

$contract->at($contractAddress)->call('balanceOf', $fromAddress, 0, function ($err, $results) use ($contract) {
    if ($err !== null) {
        echo $err->getMessage() . PHP_EOL;
    }
    if (isset($results)) {
        foreach ($results as &$result) {
            $bn = Utils::toBn($result);
            echo 'AFTER fromAccount balance ' . $bn->toString() . PHP_EOL;
        }
    }
});

$contract->at($contractAddress)->call('balanceOf', $destinationAddress, 0, function ($err, $results) use ($contract) {
    if ($err !== null) {
        echo $err->getMessage() . PHP_EOL;
    }
    if (isset($results)) {
        foreach ($results as &$result) {
            $bn = Utils::toBn($result);
            echo 'AFTER destinationAddress balance ' . $bn->toString() . PHP_EOL;
        }
    }
});

##########

