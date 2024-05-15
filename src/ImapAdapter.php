<?php

namespace alexeevdv\Flysystem\Imap;

use alexeevdv\Flysystem\Imap\Metadata\Item;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use LogicException;
use RuntimeException;
use Throwable;

class ImapAdapter implements FilesystemAdapter
{
    private const int STREAM_READ_BUFFER_SIZE = 8192;

    private Metadata\Driver $metadataDriver;

    private Connection $connection;

    private string $pathToMetadata;

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
        }
    }

    public function fileExists(string $path): bool
    {
        $item = $this->getItem($path);

        return $item !== null && $item->isDirectory() === false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $item = $this->getItem($path);
        if ($item !== null) {
            if ($item->isDirectory()) {
                throw new \RuntimeException('Cant write to directory');
            }

            if ($item->getUid() === null) {
                throw new \LogicException('Item has no uid');
            }

            $this->connection->delete($item->getUid());
        }

        $item = $this->ensureFile($path);

        $item->setFileSize(strlen($contents));

        $subject = $this->metadataDriver->getItemPath($item);
        if ($subject === null) {
            throw new \RuntimeException('null subject');
        }

        if ($this->connection->write($subject, $contents) === false) {
            throw new \RuntimeException('Cant write file contents');
        }

        $uid = $this->connection->getUid($subject);
        if ($uid === null) {
            throw new \RuntimeException('Cant find uid for subject');
        }

        $item->setUid($uid);

        $this->writeMeta();
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
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }

        $this->writeMeta();
    }

    public function deleteDirectory(string $path): void
    {
        // TODO: Implement deleteDirectory() method.
    }

    public function createDirectory(string $path, Config $config): void
    {
        // TODO check if directory exists
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // TODO: Implement setVisibility() method.
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
        // TODO: Implement listContents() method.
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
            visibility: null,
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

    private function getFile(string $path): Item
    {
        $item = $this->getItem($path);

        if ($item === null) {
            throw new RuntimeException('File does not exist');
        }

        if ($item->isDirectory()) {
            throw new RuntimeException('Trying to read directory');
        }

        if ($item->getUid() === null) {
            throw new LogicException('Metadata item has no uid');
        }

        return $item;
    }
}
