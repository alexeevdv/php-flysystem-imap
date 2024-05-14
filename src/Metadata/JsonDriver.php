<?php

namespace alexeevdv\Flysystem\Imap\Metadata;

final class JsonDriver implements Driver
{
    private Item $tree;

    public function __construct()
    {
        $this->tree = Item::directory('', []);
    }

    public function getItemPath(Item $item): ?string
    {
        return $this->getItemPathInternal($this->getTree(), $item);
    }

    private function getItemPathInternal(Item $tree, Item $needle): ?string
    {
        foreach ($tree->getChildren() as $treeItem) {
            if ($treeItem === $needle) {
                return $needle->getName();
            }

            $path = $this->getItemPathInternal($treeItem, $needle);
            if ($path !== null) {
                return $treeItem->getName() . DIRECTORY_SEPARATOR . $path;
            }
        }

        return null;
    }

    public function getTree(): Item
    {
        return $this->tree;
    }

    public function fromString(string $value): void
    {
        $items = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        $this->tree = Item::directory('', array_map([$this, 'unmapItem'], $items));
    }

    public function toString(): string
    {
        $tree = $this->mapItem($this->getTree());
        return json_encode($tree['children'], JSON_THROW_ON_ERROR);
    }

    private function unmapItem(array $item): Item
    {
        return new Item(
            name: $item['name'],
            isDirectory: $item['isDirectory'],
            children: array_map([$this, 'unmapItem'], $item['children'] ?? []),
            uid: $item['uid'] ?? null,
            fileSize: $item['fileSize'] ?? null,
        );
    }

    private function mapItem(Item $item): array
    {
        return [
            'name' => $item->getName(),
            'isDirectory' => $item->isDirectory(),
            'children' => array_map([$this, 'mapItem'], $item->getChildren()),
            'uid' => $item->getUid(),
            'fileSize' => $item->getFileSize(),
        ];
    }
}
