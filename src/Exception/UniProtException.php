<?php

declare(strict_types=1);

namespace UniProtPHP\Exception;

/**
 * UniProtException
 * 
 * Base exception for all UniProt library errors.
 * Includes error codes and structured error information.
 */
class UniProtException extends \Exception
{
    /**
     * UniProt API error code (if from API)
     */
    private ?string $apiErrorCode = null;

    /**
     * UniProt API error message
     */
    private ?string $apiErrorMessage = null;

    /**
     * HTTP status code
     */
    private int $httpStatus = 0;

    /**
     * Full API response for debugging
     */
    private ?string $apiResponse = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set API error code
     */
    public function setApiErrorCode(?string $code): self
    {
        $this->apiErrorCode = $code;
        return $this;
    }

    /**
     * Get API error code
     */
    public function getApiErrorCode(): ?string
    {
        return $this->apiErrorCode;
    }

    /**
     * Set API error message
     */
    public function setApiErrorMessage(?string $message): self
    {
        $this->apiErrorMessage = $message;
        return $this;
    }

    /**
     * Get API error message
     */
    public function getApiErrorMessage(): ?string
    {
        return $this->apiErrorMessage;
    }

    /**
     * Set HTTP status code
     */
    public function setHttpStatus(int $status): self
    {
        $this->httpStatus = $status;
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Set full API response
     */
    public function setApiResponse(?string $response): self
    {
        $this->apiResponse = $response;
        return $this;
    }

    /**
     * Get full API response
     */
    public function getApiResponse(): ?string
    {
        return $this->apiResponse;
    }

    /**
     * Check if this is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->httpStatus >= 400 && $this->httpStatus < 500;
    }

    /**
     * Check if this is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->httpStatus >= 500 && $this->httpStatus < 600;
    }

    /**
     * Check if this is a network/transport error
     */
    public function isTransportError(): bool
    {
        return $this->httpStatus === 0;
    }

    /**
     * Get a detailed error message for debugging
     */
    public function getDetailedMessage(): string
    {
        $parts = [$this->getMessage()];

        if ($this->httpStatus > 0) {
            $parts[] = "HTTP Status: {$this->httpStatus}";
        }

        if ($this->apiErrorCode !== null) {
            $parts[] = "Error Code: {$this->apiErrorCode}";
        }

        if ($this->apiErrorMessage !== null) {
            $parts[] = "API Message: {$this->apiErrorMessage}";
        }

        return implode(' | ', $parts);
    }
}
