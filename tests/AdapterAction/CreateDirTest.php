<?php

namespace Enl\Flysystem\Cloudinary\Test\AdapterAction;

use League\Flysystem\Config;

class createDirectoryTest extends ActionTestCase
{
    public function createDirectoryProvider()
    {
        return [
            ['path', ['path' => 'path/', 'type' => 'dir']],
            ['path/', ['path' => 'path/', 'type' => 'dir']],
        ];
    }

    /**
     * @dataProvider createDirectoryProvider
     * @param $path
     * @param $expected
     */
    public function testcreateDirectory($path, $expected)
    {
        list($cloudinary,) = $this->buildAdapter();

        $this->assertEquals($expected, $cloudinary->createDirectory($path, new Config()));
    }
}
