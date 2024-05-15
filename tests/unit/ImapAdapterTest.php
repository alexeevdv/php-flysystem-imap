<?php

namespace Tests\Unit;

use alexeevdv\Flysystem\Imap\Connection;
use alexeevdv\Flysystem\Imap\ImapAdapter;
use alexeevdv\Flysystem\Imap\Metadata\Driver;
use alexeevdv\Flysystem\Imap\Metadata\Item;
use alexeevdv\Flysystem\Imap\Metadata\JsonDriver;
use Codeception\Test\Unit;
use League\Flysystem\Config;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\MockObject\MockObject;

final class ImapAdapterTest extends Unit
{
    private ImapAdapter $imapAdapter;

    private MockObject|Connection $connection;

    private Driver $metadataDriver;

    protected function _setUp(): void
    {
        $this->metadataDriver = new JsonDriver();

        $this->connection = $this->createMock(Connection::class);

        $this->imapAdapter = new ImapAdapter($this->metadataDriver, $this->connection, 'metadata');
    }

    /**
     * @dataProvider fileExistsDataProvider
     */
    public function testFileExists(string $path, bool $expected): void
    {
        $this->metadataDriver->fromString(json_encode([
            [
                'name' => 'dir1',
                'isDirectory' => true,
                'children' => [
                    [
                        'name' => 'dir1.1',
                        'isDirectory' => true,
                        'children' => [],
                    ],
                    [
                        'name' => 'dir1.2',
                        'isDirectory' => true,
                        'children' => [
                            [
                                'name' => 'file1.2.1',
                                'isDirectory' => false,
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'dir2',
                'isDirectory' => true,
                'children' => [],
            ],
            [
                'name' => 'dir3',
                'isDirectory' => true,
                'children' => [
                    [
                        'name' => 'dir3.1',
                        'isDirectory' => true,
                        'children' => [],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $actual = $this->imapAdapter->fileExists($path);

        self::assertSame($expected, $actual);
    }

    public static function fileExistsDataProvider(): array
    {
        return [
            ['dir1/dir1.1', false],
            ['dir1/dir1.2', false],
            ['dir1/dir1.2/file1.2.1', true],
            ['dir1/dir1.2/file1.2.2', false],
            ['dir1/dir1.2/dir1.2.2', false],
            ['dir1/dir1.3', false],
            ['dir3', false],
            ['dir3/dir3.1', false],
        ];
    }

    /**
     * @dataProvider directoryExistsDataProvider
     */
    public function testDirectoryExists(string $path, bool $expected): void
    {
        $this->metadataDriver->fromString(json_encode([
            [
                'name' => 'dir1',
                'isDirectory' => true,
                'children' => [
                    [
                        'name' => 'dir1.1',
                        'isDirectory' => true,
                        'children' => [],
                    ],
                    [
                        'name' => 'dir1.2',
                        'isDirectory' => true,
                        'children' => [
                            [
                                'name' => 'file1.2.1',
                                'isDirectory' => false,
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'dir2',
                'isDirectory' => true,
                'children' => [],
            ],
            [
                'name' => 'dir3',
                'isDirectory' => true,
                'children' => [
                    [
                        'name' => 'dir3.1',
                        'isDirectory' => true,
                        'children' => [],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $actual = $this->imapAdapter->directoryExists($path);

        self::assertSame($expected, $actual);
    }

    public static function directoryExistsDataProvider(): array
    {
        return [
            ['dir1/dir1.1', true],
            ['dir1/dir1.2', true],
            ['dir1/dir1.2/file1.2.1', false],
            ['dir1/dir1.2/dir1.2.2', false],
            ['dir1/dir1.3', false],
            ['dir3', true],
            ['dir3/dir3.1', true],
        ];
    }

    public function testReadNonExistingFile(): void
    {
        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->read('non.existing');
    }

    public function testReadDirectory(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::directory('dir1'));

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->read('dir1');
    }

    public function testReadItemWithNoUid(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::file('file1'));

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->read('file1');
    }

    public function testReadFailed(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('read')
            ->with('123')
            ->willThrowException(new \RuntimeException('Smth happened'))
        ;

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->read('file1');
    }

    public function testReadHappyPath(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('read')
            ->with('123')
            ->willReturn('content')
        ;

        $actual = $this->imapAdapter->read('file1');

        self::assertSame('content', $actual);
    }

    public function testReadStreamNonExistingFile(): void
    {
        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->readStream('non.existing');
    }

    public function testReadStreamDirectory(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::directory('dir1'));

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->readStream('dir1');
    }

    public function testReadStreamItemWithNoUid(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::file('file1'));

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->readStream('file1');
    }

    public function testReadStreamFailed(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('readResource')
            ->with('123')
            ->willThrowException(new \RuntimeException('Smth happened'))
        ;

        $this->expectException(UnableToReadFile::class);

        $this->imapAdapter->readStream('file1');
    }

    public function testReadStreamHappyPath(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('readResource')
            ->with('123')
            ->willReturn(tmpfile())
        ;

        $actual = $this->imapAdapter->readStream('file1');

        self::assertIsResource($actual);
    }

    public function testDeleteNonExistingFile(): void
    {
        $this->expectException(UnableToDeleteFile::class);

        $this->imapAdapter->delete('non.existing');
    }

    public function testDeleteDirectory(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::directory('dir1'));

        $this->expectException(UnableToDeleteFile::class);

        $this->imapAdapter->delete('dir1');
    }

    public function testDeleteItemWithNoUid(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::file('file1'));

        $this->expectException(UnableToDeleteFile::class);

        $this->imapAdapter->delete('file1');
    }

    public function testDeleteFailed(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('delete')
            ->with('123')
            ->willThrowException(new \RuntimeException('Smth happened'))
        ;

        $this->expectException(UnableToDeleteFile::class);

        $this->imapAdapter->delete('file1');
    }

    public function testDeletePath(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->method('readResource')
            ->with('123')
            ->willReturn(tmpfile())
        ;

        $this->connection->expects($this->once())->method('getUid')
            ->with('metadata')
            ->willReturn(null)
        ;

        $this->connection->expects($this->once())->method('write')
            ->with('metadata', $this->anything())
        ;

        $this->imapAdapter->delete('file1');
    }

    public function testWriteExistingDirectory(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::directory('dir1'));

        $this->expectException(UnableToWriteFile::class);

        $this->imapAdapter->write('dir1', '', new Config());
    }

    public function testWriteExistingItemWithNoUid(): void
    {
        $this->metadataDriver->getTree()->addChild(Item::file('file1'));

        $this->expectException(UnableToWriteFile::class);

        $this->imapAdapter->write('file1', '', new Config());
    }

    public function testWriteExistingItemDeletionFailed(): void
    {
        $file1 = Item::file('file1');
        $file1->setUid('123');
        $this->metadataDriver->getTree()->addChild($file1);

        $this->connection->expects($this->once())->method('delete')
            ->with('123')
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(UnableToWriteFile::class);

        $this->imapAdapter->write('file1', '', new Config());
    }

    public function testWriteFailed(): void
    {
        $this->metadataDriver->getTree();

        $this->connection->expects($this->once())->method('write')
            ->with('file1', 'contents')
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(UnableToWriteFile::class);

        $this->imapAdapter->write('file1', 'contents', new Config());
    }

    public function testWriteGetUidFailed(): void
    {
        $this->metadataDriver->getTree();

        $this->connection->expects($this->once())->method('write')
            ->with('file1', 'contents')
        ;

        $this->connection->expects($this->once())->method('getUid')
            ->with('file1')
            ->willReturn(null)
        ;

        $this->expectException(UnableToWriteFile::class);

        $this->imapAdapter->write('file1', 'contents', new Config());
    }
}
