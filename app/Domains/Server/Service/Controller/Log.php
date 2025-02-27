<?php declare(strict_types=1);

namespace App\Domains\Server\Service\Controller;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use stdClass;

class Log extends ControllerAbstract
{
    /**
     * @var string
     */
    protected string $path;

    /**
     * @var string
     */
    protected string $basepath;

    /**
     * @var string
     */
    protected string $fullpath;

    /**
     * @var bool
     */
    protected bool $isFile;

    /**
     * @const string
     */
    protected const BASE = 'storage/logs';

    /**
     * @return array
     */
    public function handle(): array
    {
        return [$this->view(), $this->data()];
    }

    /**
     * @return string
     */
    public function view(): string
    {
        return $this->isFile() ? 'server.log-detail' : 'server.log';
    }

    /**
     * @return array
     */
    public function data(): array
    {
        return [
            'is_file' => $this->isFile(),
            'path' => $this->path(),
            'breadcrumb' => $this->breadcrumb(),
            'list' => $this->list(),
            'contents' => $this->contents(),
        ];
    }

    /**
     * @return string
     */
    protected function path(): string
    {
        if (isset($this->path)) {
            return $this->path;
        }

        if (empty($path = $this->request->input('path'))) {
            return $this->path = '';
        }

        $path = base64_decode($path);

        if (preg_match('/^[a-z0-9_\.\/\-]+$/', $path) === 0) {
            return $this->path = '';
        }

        if (str_contains($path, '..')) {
            return $this->path = '';
        }

        $path = preg_replace('/\/+/', '/', trim($path, '/'));

        if (file_exists($this->basepath().'/'.$path) === false) {
            return $this->path = '';
        }

        return $this->path = $path;
    }

    /**
     * @return string
     */
    protected function basepath(): string
    {
        return $this->basepath ??= base_path(static::BASE);
    }

    /**
     * @return string
     */
    protected function fullpath(): string
    {
        return $this->fullpath ??= $this->basepath().'/'.$this->path();
    }

    /**
     * @return bool
     */
    protected function isFile(): bool
    {
        return $this->isFile ??= is_file($this->fullpath());
    }

    /**
     * @return array
     */
    protected function breadcrumb(): array
    {
        $breadcrumb = [];
        $acum = [];

        foreach (explode('/', $this->path()) as $path) {
            $breadcrumb[] = (object)[
                'name' => $path,
                'hash' => base64_encode(implode('/', ($acum[] = $path) ? $acum : [])),
            ];
        }

        return $breadcrumb;
    }

    /**
     * @return array
     */
    protected function list(): array
    {
        if ($this->isFile()) {
            return [];
        }

        $list = [];

        foreach (new DirectoryIterator($this->fullpath()) as $fileInfo) {
            if ($this->listContentIsValid($fileInfo)) {
                $list[] = $this->listContent($fileInfo);
            }
        }

        usort($list, [$this, 'listSort']);

        return $list;
    }

    /**
     * @param \stdClass $a
     * @param \stdClass $b
     *
     * @return int
     */
    protected function listSort(stdClass $a, stdClass $b): int
    {
        if ($a->type !== $b->type) {
            return $a->type <=> $b->type;
        }

        if ($a->type === 'file') {
            return $b->name <=> $a->name;
        }

        $aIsNumber = preg_match('/^[0-9]/', $a->name);
        $bIsNumber = preg_match('/^[0-9]/', $b->name);

        if ($aIsNumber !== $bIsNumber) {
            return $aIsNumber ? -1 : 1;
        }

        return $aIsNumber ? ($b->name <=> $a->name) : ($a->name <=> $b->name);
    }

    /**
     * @param \DirectoryIterator $fileInfo
     *
     * @return bool
     */
    protected function listContentIsValid(DirectoryIterator $fileInfo): bool
    {
        if ($fileInfo->isDot()) {
            return false;
        }

        if ($fileInfo->isDir()) {
            return $this->listContentIsValidDir($fileInfo);
        }

        return in_array($fileInfo->getExtension(), ['json', 'log']);
    }

    /**
     * @param \DirectoryIterator $fileInfo
     *
     * @return bool
     */
    protected function listContentIsValidDir(DirectoryIterator $fileInfo): bool
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fileInfo->getPathname()));

        foreach (new RegexIterator($iterator, '/\.(log|json)$/i', RegexIterator::MATCH) as $file) {
            if ($file->isFile()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \DirectoryIterator $fileInfo
     *
     * @return \stdClass
     */
    protected function listContent(DirectoryIterator $fileInfo): stdClass
    {
        return (object)[
            'path' => ($path = $fileInfo->getPathname()),
            'location' => ($location = str_replace($this->basepath(), '', $path)),
            'hash' => base64_encode($location),
            'name' => $fileInfo->getBasename(),
            'size' => $fileInfo->getSize(),
            'type' => $fileInfo->isDir() ? 'dir' : 'file',
            'updated_at' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
        ];
    }

    /**
     * @return callable
     */
    protected function contents(): callable
    {
        return fn () => $this->isFile() ? readfile($this->fullpath()) : null;
    }
}
