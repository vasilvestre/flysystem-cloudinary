<?php

namespace Enl\Flysystem\Cloudinary\Test\AdapterAction;

use Cloudinary\Error;

/**
 * Class RenameTest
 * @package Enl\Flysystem\Cloudinary\Test\AdapterAction
 * @todo what to do with folder rename?
 */
class RenameTest extends ActionTestCase
{
    public function testReturnsFalseOnFailure()
    {
        list($cloudinary, $api) = $this->buildAdapter();
        $api->move('old', 'new')->willThrow(Error::class);
        $this->assertFalse($cloudinary->move('old', 'new'));
    }

    public function testReturnsTrueOnSuccess()
    {
        list($cloudinary, $api) = $this->buildAdapter();
        $api->move('old', 'new')->willReturn(['public_id' => 'new']);
        $this->assertTrue($cloudinary->move('old', 'new'));
    }
}
