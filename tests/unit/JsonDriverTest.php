<?php

namespace Tests\Unit;

use Codeception\Test\Unit;
use alexeevdv\Flysystem\Imap\Metadata\JsonDriver;

final class JsonDriverTest extends Unit
{
    public function testConstruction(): void
    {
        $json = <<<JSON
        [
          {
            "name": "dir1",
            "isDirectory": true,
            "children": []          
          }
        ]
        JSON;


        $driver = new JsonDriver($json);

        $actual = json_decode($driver->toString(), true);
        $expected = json_decode($json, true);

        self::assertEquals($expected, $actual);
    }
}