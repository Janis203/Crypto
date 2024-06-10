<?php

namespace app;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class Trade
{
    private string $apiKey;
    private string $transactions;
    private float $balance = 1000.0;

    public function __construct(string $apiKey, string $transactions = 'transactions.json')
    {
        $this->apiKey = $apiKey;
        $this->transactions = $transactions;
        if (!file_exists($this->transactions)) {
            file_put_contents($this->transactions, json_encode(['balance' => $this->balance, 'transactions' => []]));
        }
    }

    public function list(): void
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            'start' => '1',
            'limit' => '10',
            'convert' => 'USD'
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: ' . $this->apiKey
        ];
        $qs = http_build_query($parameters);
        $request = "{$url}?{$qs}";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1
        ));
        $response = curl_exec($curl);
        if ($response === false) {
            curl_close($curl);
            exit("error getting response");
        }
        $data = json_decode($response, true);
        curl_close($curl);
        if (isset($data["data"])) {
            $output = new ConsoleOutput();
            $table = new Table($output);
            $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
            foreach ($data["data"] as $crypto) {
                $table->addRow([
                    $crypto["cmc_rank"],
                    $crypto["name"],
                    $crypto["symbol"],
                    $crypto["quote"]["USD"]["price"]
                ]);
            }
            $table->render();
        } else {
            exit ("error getting data");
        }
    }

    private function searchSymbol(string $symbol): array
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
        $parameters = [
            'symbol' => $symbol,
            'convert' => 'USD'
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: ' . $this->apiKey
        ];
        $qs = http_build_query($parameters);
        $request = "{$url}?{$qs}";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1
        ));
        $response = curl_exec($curl);
        if ($response === false) {
            curl_close($curl);
            exit("error getting response");
        }
        curl_close($curl);
        return json_decode($response, true);
    }

    public function search(string $symbol): void
    {
        $data = $this->searchSymbol($symbol);
        if (isset($data["data"])) {
            $crypto = $data["data"][$symbol];
            $output = new ConsoleOutput();
            $table = new Table($output);
            $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
            $table->addRow([
                $crypto["cmc_rank"],
                $crypto["name"],
                $crypto["symbol"],
                $crypto["quote"]["USD"]["price"]
            ]);
            $table->render();
        } else {
            exit ("error getting data");
        }
    }

    private function getTransactions(): array
    {
        return json_decode(file_get_contents($this->transactions), true);
    }

    private function saveTransactions(array $data): void
    {
        file_put_contents($this->transactions, json_encode($data, JSON_PRETTY_PRINT));
    }


    public function purchase(string $symbol): void
    {
        $data = $this->searchSymbol($symbol);
        if (isset($data['data'][$symbol])) {
            $amount = (float)readline("Enter amount of $symbol to buy ");
            if ($amount <= 0) {
                echo "Enter positive amount " . PHP_EOL;
                return;
            }
            $price = $data["data"][$symbol]["quote"]["USD"]["price"];
            $cost = $price * $amount;
            $transactions = $this->getTransactions();
            if ($transactions['balance'] < $cost) {
                echo "Insufficient funds to buy $amount $symbol " . PHP_EOL;
                return;
            }
            $transactions['balance'] -= $cost;
            $transactions['transactions'][] = [
                'type' => 'purchase',
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price,
                'cost' => $cost,
                'time' => date('Y-m-d H:i:s')
            ];
            $this->saveTransactions($transactions);
            echo "Purchased $amount $symbol for \$$cost" . PHP_EOL;
        } else {
            echo $symbol . " not found" . PHP_EOL;
        }
    }

    public function sell(string $symbol): void
    {
        $data = $this->searchSymbol($symbol);
        if (isset($data['data'][$symbol])) {
            $amount = (float)readline("Enter amount of $symbol to sell ");
            if ($amount <= 0) {
                echo "Enter positive amount " . PHP_EOL;
                return;
            }
            $price = $data["data"][$symbol]["quote"]["USD"]["price"];
            $value = $price * $amount;
            $bought = 0;
            $sold = 0;
            $transactions = $this->getTransactions();
            foreach ($transactions['transactions'] as $transaction) {
                if ($transaction['type'] === "purchase" && $transaction['symbol'] === $symbol) {
                    $bought += $transaction['amount'];
                } elseif ($transaction['type'] === "sell" && $transaction['symbol'] === $symbol) {
                    $sold += $transaction['amount'];
                }
            }
            $availableAmount = $bought - $sold;
            if ($amount > $availableAmount) {
                echo "Insufficient amount of $symbol to sell " . PHP_EOL;
                return;
            }
            $transactions['balance'] += $value;
            $transactions['transactions'][] = [
                'type' => 'sell',
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price,
                'value' => $value,
                'time' => date('Y-m-d H:i:s')
            ];
            $this->saveTransactions($transactions);
            echo "Sold $amount $symbol for \$$value" . PHP_EOL;
        } else {
            echo $symbol . " not found" . PHP_EOL;
        }
    }

    public function displayWallet(): void
    {
        $transactions = $this->getTransactions();
        echo "Current balance is " . $transactions['balance'] . PHP_EOL;
        $holding = [];
        foreach ($transactions['transactions'] as $transaction) {
            $symbol = $transaction['symbol'];
            if (!isset($holding[$symbol])) {
                $holding[$symbol] = 0;
            }
            if ($transaction['type'] === 'purchase') {
                $holding[$symbol] += $transaction['amount'];
            } elseif ($transaction['type'] === "sell") {
                $holding[$symbol] -= $transaction['amount'];
            }
        }
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Symbol", "Amount"]);
        foreach ($holding as $symbol => $amount) {
            if ($amount > 0) {
                $table->addRow([$symbol, $amount]);
            }
        }
        $table->render();
    }

    public function displayTransactions(): void
    {
        $transactions = $this->getTransactions();
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Type", "Symbol", "Amount", "Price", "Value", "Time"]);
        foreach ($transactions['transactions'] as $transaction) {
            $table->addRow([
                ucfirst($transaction["type"]),
                $transaction['symbol'],
                $transaction['amount'],
                $transaction['price'],
                $transaction['cost'] ?? $transaction['value'],
                $transaction['time']
            ]);
        }
        $table->render();
    }
}