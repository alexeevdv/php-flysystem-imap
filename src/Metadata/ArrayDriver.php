<?php

namespace alexeevdv\Flysystem\Imap\Metadata;

final class ArrayDriver implements Driver
{
    private Item $tree;

    public function __construct(
        array $items,
    ) {
        $this->tree = Item::directory('', array_map([$this, 'mapArray'], $items));
    }

    public function getTree(): Item
    {
        return $this->tree;
    }

    public function toString(): string
    {
        return 'TODO';
    }

    private function mapArray(array $item): Item
    {
        return new Item(
            name: $item['name'],
            isDirectory: $item['isDirectory'],
            children: array_map([$this, 'mapArray'], $item['children'] ?? []),
            uid: $item['uid'] ?? null,
            fileSize: $item['fileSize'] ?? null,
        );
    }
}
