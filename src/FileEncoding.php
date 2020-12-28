<?php
declare(strict_types=1);

namespace FileRetrieverService;

abstract class FileEncoding
{
    public const ENCODING_UTF_8 = 'UTF-8';
    public const ENCODING_WINDOWS_1252 = 'Windows-1252';

    /**
     * @return array<string>
     */
    public static function getAvailableEncodings(): array
    {
        return [
            self::ENCODING_UTF_8,
            self::ENCODING_WINDOWS_1252,
        ];
    }
}
