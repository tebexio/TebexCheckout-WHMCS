<?php

/**
 * Error format received when Checkout API issues occur
 */
class CheckoutAPIError extends Exception
{
    private $request;
    private $response;
    private $statusCode;
    private $errorDetails;

    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->statusCode = $response['status'] ?? 0;
        $this->parseErrorDetails();

        parent::__construct(implode(',', $this->errorDetails));
    }

    private function parseErrorDetails()
    {
        $body = $this->response['body'] ?? '';
        $this->errorDetails = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error parsing JSON response: ' . json_last_error_msg());
            $this->errorDetails = [
                'title' => 'Error parsing JSON response',
                'detail' => 'An error occurred while decoding the API response.'
            ];
        }
    }

    public function withMessage(string $value) : CheckoutApiError {
        $this->errorDetail = "$value: {$this->errorDetails}";
        return $this;
    }

    public function logErrorDetails()
    {
        $errorTitle = $this->errorDetails['title'] ?? 'Unknown error';
        $errorDetail = $this->errorDetails['detail'] ?? 'No additional information provided';
        logModuleCall("Tebex Checkout", "api response - error", $this->request, $this->response, $this->response, "", "");
    }

    public function throw() {
        switch ($this->statusCode) {
            case 400:
                throw new InvalidArgumentException("Bad Request: '{$this->errorDetails}'");
            case 401:
            case 403:
                throw $this->withMessage("Access Denied");
            case 404:
                throw $this->withMessage("Not Found: {$this->errorDetails}");
            case 500:
            case 501:
            case 502:
            case 503:
            case 504:
                throw $this->withMessage("Internal Server Error: {$this->errorDetails['title']} - {$this->errorDetails}");
            default:
                throw $this->withMessage("API Error: {$this->errorDetails['title']} - {$this->errorDetails}");
        }
    }
}