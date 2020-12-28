<?php
declare(strict_types=1);

namespace FileRetrieverService\Models;

use DateTime;

class RetrievedFile
{
    public function __construct(
        private string $url,
        private string $absoluteLocalPath,
        private ?DateTime $lastModifiedAt,
        private int $totalCharactersInFile
    )
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAbsoluteLocalPath(): string
    {
        return $this->absoluteLocalPath;
    }

    public function getLastModifiedAt(): ?DateTime
    {
        return $this->lastModifiedAt;
    }

    public function getTotalCharactersInFile(): int
    {
        return $this->totalCharactersInFile;
    }
}
