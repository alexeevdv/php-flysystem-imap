<?php

namespace alexeevdv\Flysystem\Imap;

interface Connection
{
    public function getUid(string $subject): ?string;

    /**
     * @return resource
     */
    public function readResource(string $uid);

    public function read(string $uid): string;

    public function write(string $subject, string $contents): bool;

    public function delete(string $uid): void;
}
