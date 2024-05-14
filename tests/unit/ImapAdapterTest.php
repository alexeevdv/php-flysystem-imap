<?php

namespace Tests\Unit;

use alexeevdv\Flysystem\Imap\Connection;
use alexeevdv\Flysystem\Imap\ImapAdapter;
use alexeevdv\Flysystem\Imap\ImapConnection;
use alexeevdv\Flysystem\Imap\Metadata\ArrayDriver;
use Codeception\Test\Unit;

final class ImapAdapterTest extends Unit
{
    private ImapAdapter $imapAdapter;

    private Connection $connection;

    private ArrayDriver $metadata;

    protected function _setUp(): void
    {
        $this->metadata = new ArrayDriver(items: [
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
        ]);

        $this->connection = new ImapConnection('');

        $this->imapAdapter = new ImapAdapter($this->metadata, $this->connection);
    }

    /**
     * @dataProvider fileExistsDataProvider
     */
    public function testFileExists(string $path, bool $expected): void
    {
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
}
