<?php

namespace alexeevdv\Flysystem\Imap;

use alexeevdv\Flysystem\Imap\Metadata\Item;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use LogicException;
use RuntimeException;
use Throwable;

class ImapAdapter implements FilesystemAdapter
{
    private const int STREAM_READ_BUFFER_SIZE = 8192;

    private Metadata\Driver $metadataDriver;

    private Connection $connection;

    private string $pathToMetadata;

    private ?string $lastMetaHash = null;

    public function __construct(
        Metadata\Driver $metadataDriver,
        Connection $connection,
        string $pathToMetadata,
    ) {
        $this->metadataDriver = $metadataDriver;
        $this->connection = $connection;
        $this->pathToMetadata = $pathToMetadata;

        $uid = $this->connection->getUid($this->pathToMetadata);
        if ($uid !== null) {
            $metadata = $this->connection->read($uid);
            $this->metadataDriver->fromString($metadata);
            $this->lastMetaHash = $this->getMetaHash();
        }
    }

    public function __destruct()
    {
        if ($this->lastMetaHash !== $this->getMetaHash()) {
            $this->writeMeta();
        }
    }

    public function fileExists(string $path): bool
    {
        $item = $this->getItem($path);

        return $item !== null && $item->isDirectory() === false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $item = $this->getNullableFile($path);
            if ($item !== null) {
                $this->connection->delete($item->getUid());
            }

            $item = $this->ensureFile($path);
            $item->setFileSize(strlen($contents));

            $subject = $this->metadataDriver->getItemPath($item);
            $this->connection->write($subject, $contents);

            $uid = $this->connection->getUid($subject);
            if ($uid === null) {
                throw new RuntimeException('Cant find uid for subject: ' . $subject);
            }

            $item->setUid($uid);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $content = '';
        while(($chunk = fread($contents, self::STREAM_READ_BUFFER_SIZE)) !== false && feof($contents) !== true) {
            $content .= $chunk;
        }

        $this->write($path, $content, $config);
    }

    public function read(string $path): string
    {
        try {
            $item = $this->getFile($path);
            return $this->connection->read($item->getUid());
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $item = $this->getFile($path);
            return $this->connection->readResource($item->getUid());
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $item = $this->getFile($path);
            $this->connection->delete($item->getUid());

            // TODO delete item from metadata tree
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $item = $this->getDirectory($path);

            // TODO delete all nested items
            // TODO delete item from metadata tree
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $item = $this->getNullableDirectory($path);
            if ($item !== null) {
                throw new RuntimeException('Directory already exists');
            }

            // TODO add item to metadata tree
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $item = $this->getItem($path);
        if ($item === null) {
            throw UnableToSetVisibility::atLocation($path, 'Item not found');
        }

        $item->setVisibility($visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        yield;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // TODO just change metadata
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    private function getFileAttributes(string $path): FileAttributes
    {
        $item = $this->getItem($path);
        if ($item === null) {
            throw new \RuntimeException('File does not exist');
        }

        if ($item->isDirectory()) {
            throw new \RuntimeException('Item is directory');
        }

        return new FileAttributes(
            path: $this->metadataDriver->getItemPath($item),
            fileSize: $item->getFileSize(),
            visibility: $item->getVisibility(),
            lastModified: null,
            mimeType: null,
            extraMetadata: [],
        );
    }

    public function directoryExists(string $path): bool
    {
        $item = $this->getItem($path);

        return $item !== null && $item->isDirectory();
    }

    private function getItem(string $path): ?Item
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);

        $item = $this->metadataDriver->getTree();

        foreach ($pathParts as $pathPart) {
            $item = $item->getChildByName($pathPart);
            if ($item === null) {
                return null;
            }
        }

        return $item;
    }

    private function ensureFile(string $path): Item
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);

        $fileName = array_pop($pathParts);

        $directory = $this->metadataDriver->getTree();

        foreach ($pathParts as $pathPart) {
            $itemFound = $directory->getChildByName($pathPart);
            if ($itemFound === null) {
                $item = Item::directory($pathPart);

                $directory->addChild($item);
                $directory = $item;
            } else {
                $directory = $itemFound;
            }
        }

        $item = $directory->getChildByName($fileName);
        if ($item === null) {
            $item = Item::file($fileName);
            $directory->addChild($item);
        }

        return $item;
    }

    private function writeMeta(): void
    {
        $uid = $this->connection->getUid($this->pathToMetadata);
        if ($uid !== null) {
            $this->connection->delete($uid);
        }

        $this->connection->write($this->pathToMetadata, $this->metadataDriver->toString());
    }

    private function getNullableFile(string $path): ?Item
    {
        $item = $this->getItem($path);
        if ($item === null) {
            return null;
        }

        $item = $this->validateItemIsFile($item);
        $item = $this->validateItemHasUid($item);

        return $item;
    }

    private function getFile(string $path): Item
    {
        $item = $this->getNullableFile($path);
        $item = $this->validateItemExists($item);

        return $item;
    }

    private function getNullableDirectory(string $path): ?Item
    {
        $item = $this->getItem($path);
        if ($item === null) {
            return null;
        }

        $item = $this->validateItemIsDirectory($item);

        return $item;
    }

    private function getDirectory(string $path): Item
    {
        $item = $this->getNullableDirectory($path);
        $item = $this->validateItemExists($item);

        return $item;
    }

    private function validateItemExists(?Item $item): Item
    {
        if ($item === null) {
            throw new RuntimeException('Metadata item does not exist');
        }

        return $item;
    }

    private function validateItemIsFile(Item $item): Item
    {
        if ($item->isDirectory() === true) {
            throw new RuntimeException('Metadata item is not a file');
        }

        return $item;
    }

    private function validateItemIsDirectory(Item $item): Item
    {
        if ($item->isDirectory() === false) {
            throw new RuntimeException('Metadata item is not a directory');
        }

        return $item;
    }

    private function validateItemHasUid(Item $item): Item
    {
        if ($item->getUid() === null) {
            throw new LogicException('Metadata item has no uid');
        }

        return $item;
    }

    private function getMetaHash(): string
    {
        return md5($this->metadataDriver->toString());
    }
}
