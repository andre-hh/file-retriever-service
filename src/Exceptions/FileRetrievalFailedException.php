<?php
declare(strict_types=1);

namespace FileRetrieverService\Exceptions;

use Exception;

class FileRetrievalFailedException extends Exception
{
    public function __construct(
        protected string $fileUrl,
        protected string $message,
        protected array $additionalData = []
    )
    {
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
