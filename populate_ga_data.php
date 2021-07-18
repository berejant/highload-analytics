#!/usr/bin/env php
<?php

use TheIconic\Tracking\GoogleAnalytics\Analytics;

require __DIR__ . '/vendor/autoload.php';

define('PRICE_FILE', __DIR__ . '/price.json');

$prices = get_prices();

// Instantiate the Analytics object
// optionally pass TRUE in the constructor if you want to connect using HTTPS
$analytics = new Analytics(true);

// Build the GA hit using the Analytics class methods
// they should Autocomplete if you use a PHP IDE
$analytics
    ->setProtocolVersion('1')
    ->setTrackingId('UA-19685831-6')
    ->setClientId(1)
    ->setDocumentPath('/')
    ->setIpOverride('127.0.0.1');


foreach ($prices as $price) {
    $analytics->setCustomDimension($price['fuel'], 1);
    $analytics->setCustomDimension($price['region'], 2);
    $analytics->setCustomDimension($price['provider'], 3);
    $analytics->setCustomMetric($price['price'], 1);

    $response = $analytics->sendPageview();
}
var_dump($response);



function get_prices() {
    if (file_exists(PRICE_FILE)) {
        return json_decode(file_get_contents(PRICE_FILE), true);
    }

    $html = file_get_contents('https://index.minfin.com.ua/markets/fuel/detail/');
    list(, $html) = explode('<article id=\'idx-content\'>', $html, 2);
    list(, $html) = explode('<table', $html, 2);
    list($html) = explode('</table', $html, 2);
    list(,$html) = explode('<tr>', $html, 2);
    $html = str_replace('&nbsp;', ' ', $html);

    $rows = explode('</tr>', $html);
    array_pop($rows);
    $rows = array_map('trim', $rows);

    $headers = null;
    $region = null;

    $data = [];

    foreach ($rows as $index => $row) {
        $is_header = substr($row,  -5) === '</th>';
        $cells = explode($is_header ? '</th>' : '</td>', $row);
        array_pop($cells);
        $cells = array_map('strip_tags', $cells);
        $cells = array_map('trim', $cells);


        if ($index === 0) {
            array_shift($cells);
            $headers = $cells;
        } elseif ($is_header) {
            $region = $cells[0];

        } elseif (!isset($headers, $region)) {
            throw new Exception('Failed to parse table header and region name');

        } else {
            $provider = $cells[0];
            $cells = array_slice($cells, -count($headers));

            foreach ($cells as $key => $value) {
                $cells[$key] = (float)str_replace(',', '.', $value);
            }

            $prices = array_combine($headers, $cells);

            foreach ($prices as $fuel => $price) {
                if (!$price) {
                    continue;
                }

                $data[] = [
                    'region' => $region,
                    'provider' => $provider,
                    'fuel' => $fuel,
                    'price' => $price,
                ];
            }
        }
    }

    file_put_contents(PRICE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $data;
}