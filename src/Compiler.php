<?php

namespace CodeAlpha\Blade;

use InvalidArgumentException;

abstract class Compiler
{
    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Get the template path for the views.
     *
     * @var string
     */
    protected $viewPath;

    /**
     * Get the cache path for the compiled views.
     *
     * @var string
     */
    protected $cachePath;


    /**
     * Create a new compiler instance.
     *
     * @param  $files
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($viewPath, $cachePath)
    {
        if (! $viewPath) {
            throw new InvalidArgumentException('Please provide a valid view path.');
        }

        if (! $cachePath) {
            throw new InvalidArgumentException('Please provide a valid cache path.');
        }

        $this->viewPath = $viewPath;
        $this->cachePath = $cachePath;
        $this->files = new Filesystem();
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path)
    {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path)
    {
        $compiled = $this->getCompiledPath($path);

        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (! $this->files->exists($compiled)) {
            return true;
        }

        return $this->files->lastModified($this->viewPath . '/' . $path . '.blade.php') >=
               $this->files->lastModified($compiled);
    }

    /**
     * Create the compiled file directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureCompiledDirectoryExists($path)
    {
        if (! file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true, true);
        }
    }
}
