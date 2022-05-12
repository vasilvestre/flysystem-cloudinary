<?php

namespace Enl\Flysystem\Cloudinary\Test\AdapterAction;

/**
 * Visibility is not supported for Cloudinary,
 * so that this test just checks if the methods always return NotSupportedException
 *
 * @package Enl\Flysystem\Cloudinary\Test\AdapterAction
 */
class VisibilityTest extends ActionTestCase
{
    public function testvisibility()
    {
        list($cloudinary) = $this->buildAdapter();
        $this->expectException(\LogicException::class);
        $cloudinary->setVisibility('path', 'visibility');
    }

    public function testSetVisibility()
    {
        list($cloudinary) = $this->buildAdapter();
        $this->expectException(\LogicException::class);
        $cloudinary->visibility('path');
    }
}
