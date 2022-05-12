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
        $overwrite = (bool)$config->get('disable_asserts');

        try {
            return $this->normalizeMetadata($this->api->upload($path, $contents, $overwrite));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function update(string $path, string $contents, Config $config)
    {
        try {
            return $this->normalizeMetadata($this->api->upload($path, $contents, true));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function rename(string $path, string $newPath)
    {
        try {
            return (bool) $this->api->rename($path, $newPath);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        try {
            $response = $this->api->deleteFile($path);

            return $response['result'] === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        try {
            $response = $this->api->delete_resources_by_prefix(rtrim($dirname, '/').'/');

            return is_array($response['deleted']);
        } catch (Api\Error $e) {
            return false;
        }
    }

    /**
     * Create a directory.
     * Cloudinary creates folders implicitly when you upload file with name 'path/file' and it has no API for folders
     * creation. So that we need to just say "everything is ok, go on!".
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        return [
            'path' => rtrim($dirname, '/').'/',
            'type' => 'dir',
        ];
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        if ($response = $this->readStream($path)) {
            return ['contents' => stream_get_contents($response['stream']), 'path' => $response['path']];
        }

        return false;
    }

    /**
     * @param $path
     *
     * @return array|bool
     */
    public function readStream($path)
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
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        try {
            return $this->addDirNames($this->doListContents($directory));
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
     *
     * @param string $path
     *
     * @return array|false
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
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
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
