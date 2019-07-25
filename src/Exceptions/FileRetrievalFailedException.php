<?php
declare(strict_types=1);

namespace FileRetrievalService\Exceptions;

class FileRetrievalFailedException extends \Exception
{
    /** @var string */
    private $fileUrl;

    /** @var array */
    private $additionalData;

    public function __construct(string $fileUrl, string $message, array $additionalData = [])
    {
        $this->fileUrl = $fileUrl;
        $this->additionalData = $additionalData;

        parent::__construct($message);
    }

    public function getFileUrl(): string
    {
        return $this->fileUrl;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }
}
