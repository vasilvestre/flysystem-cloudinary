<?php

namespace Enl\Flysystem\Cloudinary;

use Cloudinary\Api;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;

class CloudinaryAdapter implements FilesystemAdapter
{
    /** @var ApiFacade */
    private $api;

    public function __construct(ApiFacade $api)
    {
        $this->api = $api;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->normalizeMetadata($this->api->upload($path, $contents, true));
    }

    /**
     * Rename a file.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->api->move($source, $destination);
    }

    public function delete(string $path): void
    {
        $this->api->deleteFile($path);
    }

    public function deleteDir(string $dirname): void
    {
        $this->api->delete_resources_by_prefix(rtrim($dirname, '/').'/');
    }

    public function createDirectory(string $dirname, Config $config): void
    {
        rtrim($dirname, '/').'/';
    }

    public function fileExists(string $path): bool
    {
        return $this->getMetadata($path);
    }

    public function read(string $path): string
    {
        return ['contents' => stream_get_contents($response['stream']), 'path' => $response['path']];
    }

    public function readStream(string $path)
    {
        try {
            return [
                'stream' => $this->api->content($path),
                'path' => $path,
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List contents of a directory.
     * Cloudinary does not support non recursive directory scan
     * because they treat filename prefixes as folders.
     *
     * Good news is Flysystem can handle this and will filter out subdirectory content
     * if $recursive is false.
     */
    public function listContents(string $path = '', bool $recursive = false): iterable
    {
        try {
            return $this->addDirNames($this->doListContents($path));
        } catch (\Exception $e) {
            return [];
        }
    }

    private function addDirNames($contents)
    {
        // Add the the dirnames of the returned files as directories
        $dirs = [];

        foreach ($contents as $file) {
            $dirname = dirname($file['path']);

            if ($dirname !== '.') {
                $dirs[$dirname] = [
                    'type' => 'dir',
                    'path' => $dirname,
                ];
            }
        }

        foreach ($dirs as $dir) {
            $contents[] = $dir;
        }

        return $contents;
    }

    private function doListContents($directory = '', array $storage = ['files' => []])
    {
        $options = ['prefix' => $directory, 'max_results' => 500, 'type' => 'upload'];
        if (isset($storage['next_cursor'])) {
            $options['next_cursor'] = $storage['next_cursor'];
        }

        $response = $this->api->resources($options);

        foreach ($response['resources'] as $resource) {
            $storage['files'][] = $this->normalizeMetadata($resource);
        }
        if (isset($response['next_cursor'])) {
            $storage['next_cursor'] = $response['next_cursor'];

            return $this->doListContents($directory, $storage);
        }

        return $storage['files'];
    }

    /**
     * Get all the meta data of a file or directory.
     */
    public function getMetadata($path)
    {
        try {
            return $this->normalizeMetadata($this->api->resource($path));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all the meta data of a file or directory.
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     */
    public function getTimestamp(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    private function normalizeMetadata($resource)
    {
        return !$resource instanceof \ArrayObject && !is_array($resource) ? false : [
            'type' => 'file',
            'path' => $resource['path'],
            'size' => isset($resource['bytes']) ? $resource['bytes'] : false,
            'timestamp' => isset($resource['created_at']) ? strtotime($resource['created_at']) : false,
            'version' => isset($resource['version']) ? $resource['version'] : 1,
        ];
    }
}
