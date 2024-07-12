<?php

class PluginEvent {
    private $gameId;
    private $frameworkId;
    private $runtimeVersion;
    private $frameworkVersion;
    private $pluginVersion;
    private $serverId;
    private $eventMessage;
    private $eventLevel; //WARNING, INFO, ERROR
    private $metadata;
    private $trace;
    private $storeUrl;

    public function __construct($storeId, $level, $message) {
        $this->gameId = "Checkout"; // indicate usage of Checkout API
        $this->frameworkId = "WHMCS"; // indicate specific user app, WHMCS
        $this->runtimeVersion = ""; // PHP version
        $this->frameworkVersion = ""; // WHMCS version
        $this->pluginVersion = ""; // plugin version
        $this->eventLevel = $level;
        $this->eventMessage = $message;
        $this->trace = "";
        $this->serverId = $storeId; // Tebex Checkout store ID
        $this->storeUrl = ""; //empty for this integration
        $this->metadata = [];
    }

    public function withTrace($trace = null) {
        if (is_null($trace)) {
            // create a trace to here if no other trace provided
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $this->trace = json_encode($trace);
        } else if ($trace instanceof \Throwable) {
            // handle Throwable trace
            $this->trace = $trace->getTraceAsString();
        } else {
            $this->trace = (string) $trace;
        }
        return $this;
    }

    public function withFrameworkVersion($version) {
        $this->frameworkVersion = $version;
        return $this;
    }

    public function withRuntimeVersion($version) {
        $this->runtimeVersion = $version;
        return $this;
    }

    public function withPluginVersion($version) {
        $this->pluginVersion = $version;
        return $this;
    }

    public function withMetadata($metadata) {
        $this->metadata = $metadata;
        return $this;
    }

    public function toJsonString() {
        return json_encode(get_object_vars($this));
    }

    public function send() {
        $ch = curl_init("https://plugin-logs.tebex.io/events");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $eventData = [
            [
                "game_id" => $this->gameId,
                "framework_id" => $this->frameworkId,
                "runtime_version" => $this->runtimeVersion,
                "framework_version" => $this->frameworkVersion,
                "plugin_version" => $this->pluginVersion,
                "event_level" => $this->eventLevel,
                "event_message" => $this->eventMessage,
                "trace" => $this->trace,
                "server_id" => $this->serverId,
                "server_ip" => $_SERVER['SERVER_ADDR'],
                "store_url" => $this->storeUrl,
                "metadata" => json_encode($this->metadata),
            ],
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData)); // array of events is required
        curl_setopt($ch, CURLOPT_USERAGENT, "TebexCheckout-WHMCS");
        $response = curl_exec($ch);
        $requestInfo = curl_getinfo($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);
            logModuleCall("Tebex Checkout", "failed to send error event", $requestInfo, curl_error($ch), curl_error($ch), "", "");
            throw new Exception('Request Error: ' . curl_error($ch));
        }

        curl_close($ch);
        $responseData = json_decode($response, true);

        // Show error in the module log if a problem occurred
        if ($httpStatusCode >= 400) {
            logModuleCall("Tebex Checkout", "failed to send error event: status code " . $httpStatusCode, $requestInfo, $response, $responseData, "", "");
        } else { // successful response
            logModuleCall("Tebex Checkout", "successfully submitted event", $requestInfo, $responseData, $responseData, "", "");
        }
    }

    /**
     * @return mixed
     */
    private function executeRequest(CurlHandle $ch)
    {
        

        return $response;
    }
}
