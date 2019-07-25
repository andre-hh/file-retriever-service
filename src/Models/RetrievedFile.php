<?php
declare(strict_types=1);

namespace FileRetrieverService\Models;

use DateTime;

class RetrievedFile
{
    /** @var string */
    private $url;

    /** @var string */
    private $absoluteLocalPath;

    /** @var DateTime|null */
    private $lastModifiedAt;

    /** @var int */
    private $totalCharactersInFile;

    public function __construct(
        string $url,
        string $absoluteLocalPath,
        ?DateTime $lastModifiedAt,
        int $totalCharactersInFile
    )
    {
        $this->url = $url;
        $this->absoluteLocalPath = $absoluteLocalPath;
        $this->lastModifiedAt = $lastModifiedAt;
        $this->totalCharactersInFile = $totalCharactersInFile;
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
