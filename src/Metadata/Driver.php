<?php

namespace alexeevdv\Flysystem\Imap\Metadata;

interface Driver
{
    public function getItemPath(Item $item): string;

    public function getTree(): Item;

    public function fromString(string $value): void;

    public function toString(): string;
}
