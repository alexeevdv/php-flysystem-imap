<?php

namespace alexeevdv\Flysystem\Imap\Metadata;

class Item
{
    private string $name;

    private bool $isDirectory;

    /**
     * @var Item[]
     */
    private array $children;

    private ?string $uid;

    private ?int $fileSize;

    private ?string $visibility;

    /**
     * @param Item[] $children
     */
    public function __construct(
        string $name,
        bool $isDirectory,
        array $children,
        ?string $uid = null,
        ?int $fileSize = null,
        ?string $visibility = null,
    ) {
        $this->name = $name;
        $this->isDirectory = $isDirectory;
        $this->children = $children;
        $this->uid = $uid;
        $this->fileSize = $fileSize;
        $this->visibility = $visibility;
    }

    /**
     * @param self[] $children
     */
    public static function directory(string $name, array $children = []): self
    {
        return new self(
            name: $name,
            isDirectory: true,
            children: $children,
        );
    }

    public static function file(string $name): self
    {
        return new self(
            name: $name,
            isDirectory: false,
            children: [],
        );
    }

    public function setUid(string $uid): void
    {
        $this->uid = $uid;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isDirectory(): bool
    {
        return $this->isDirectory;
    }

    /**
     * @return Item[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getChildByName(string $name): ?Item
    {
        foreach ($this->children as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    public function addChild(Item $child): void
    {
        $this->children[] = $child;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }
}
