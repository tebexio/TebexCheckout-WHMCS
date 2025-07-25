<?php

namespace Tebex;

use GuzzleHttp\Client;
use TebexCheckout\Configuration;
use TebexCheckout\TebexCheckout\BasketsApi;

abstract class TebexAPI {
    protected static string $_projectId = "";
    protected static string $_privateKey = "";
    protected static string $_publicToken = "";
    protected static bool $_areApiKeysSet = false;

    public abstract function getApiBaseUrl() : string;
}