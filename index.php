<?php
require 'vendor/autoload.php';

use App\Trade;

$key = "";
$trade = new Trade($key);
while (true) {
    echo "[1] List top crypto currencies\n[2] Search crypto by its ticking symbol
[3] Purchase crypto\n[4] Sell crypto\n[5] Display state of wallet\n[6] Display transaction list\n[Any key] Exit\n";
    $choice = (int)readline("Enter choice ");
    switch ($choice) {
        case 1:
            $trade->list();
            break;
        case 2:
            $symbol = strtoupper(readline("Enter ticking symbol "));
            $trade->search($symbol);
            break;
        case 3:
            $symbol = strtoupper(readline("Enter crypto symbol to purchase "));
            $trade->purchase($symbol);
            break;
        case 4:
            $symbol = strtoupper(readline("Enter crypto symbol to sell "));
            $trade->sell($symbol);
            break;
        case 5:
            $trade->displayWallet();
            break;
        case 6:
            $trade->displayTransactions();
            break;
        default:
            exit("Goodbye\n");

    }
}