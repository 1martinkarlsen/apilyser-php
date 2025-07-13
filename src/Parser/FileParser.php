<?php

namespace Apilyser\Parser;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileParser
{

    private string $regex = '#(?<!/)\.php$|^[^\.]*$#i';
    private RecursiveDirectoryIterator $recursiveDirectory;
    private RecursiveIteratorIterator $directoryIterator;

    public string $folderRoot;

    public function __construct(string $folderPath) {
        $this->folderRoot = $folderPath;
        $this->recursiveDirectory = new RecursiveDirectoryIterator($this->folderRoot, RecursiveDirectoryIterator::SKIP_DOTS);
        $this->directoryIterator = new RecursiveIteratorIterator($this->recursiveDirectory);
    }

    /**
     * @return string[]
     */
    public function getFiles(): array {
        // Array of files to return
        $files = [];

        foreach ($this->directoryIterator as $path) {
            $pathDirs = explode("/", $path);
            $lastPath = end($pathDirs);
            if (preg_match($this->regex, $lastPath)) {
                array_push($files, $path);
            }
        }

        return $files;
    }
}