<?php
/**
 ***********************************************************************************************
 * This class handles the most necessary file-system operations
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class FileSystemUtils
 * This class handles the most necessary file-system operations like:
 * - Function: get normalized path, get human readable bytes, restrict all operations to specific directories
 * - Info: disk space, process owner/group info, path owner/group info, is path owner, path mode, path permissions
 * - Folder: create, is empty, get content, delete content, delete folder, copy, move, chmod
 * - File: delete, copy, move, chmod, read, write
 */
final class FileSystemUtils
{
    const CONTENT_TYPE_DIRECTORY = 'directory';
    const CONTENT_TYPE_FILE      = 'file';
    const CONTENT_TYPE_LINK      = 'link';

    const ROOT_ID = 0;
    const ROOT_FOLDER = '/';

    /**
     * @var array<int,string> The allowed directories
     */
    private static $allowedDirectories = array();

    /**
     * Get a normalized/simplified path
     * @param string $path The path to normalize
     * @return string The normalized path
     * @see https://secure.php.net/manual/en/function.realpath.php
     * @example "/path/to/test/.././..//..///..///../one/two/../three/filename" => "../../one/three/filename"
     */
    public static function getNormalizedPath($path)
    {
        $path = str_replace('\\', '/', $path); // Replace back-slashes with forward-slashes
        $path = preg_replace('/\/+/', '/', $path); // Combine multiple slashes into a single slash

        $segments = explode('/', $path);

        $parts = array();
        foreach ($segments as $segment)
        {
            if ($segment === '.')
            {
                // Actual directory => ignore
                continue;
            }

            $test = array_pop($parts);
            if ($test === null)
            {
                // No path added => add first path
                $parts[] = $segment;
            }
            elseif ($segment === '..')
            {
                if ($test === '..')
                {
                    // Change to grand-parent directory => add two times ".."
                    $parts[] = $test;
                    $parts[] = $segment;
                }
                elseif ($test === '')
                {
                    // File-system root => remove root and add ".."
                    $parts[] = $segment;
                }
//                else
//                {
//                    // Change to parent directory => ignore
//                }
            }
            else
            {
                // New sub-path => add parent path and new path
                $parts[] = $test;
                $parts[] = $segment;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Gets human readable bytes with unit
     * @param int  $bytes The bytes
     * @param bool $si    Use SI or binary unit. Set true for SI units
     * @return string Returns human readable bytes with unit.
     * @example "[value] [unit]" (e.g: 34.5 MiB)
     */
    public static function getHumanReadableBytes($bytes, $si = false)
    {
        $divider = 1024;
        $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'YiB');
        if ($si)
        {
            $divider = 1000;
            $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'YB');
        }

        $iteration = 0;
        while ($bytes >= $divider)
        {
            ++$iteration;
            $bytes /= $divider;
        }

        $unit = $units[$iteration];

        return round($bytes, 1) . ' ' . $unit;
    }

    /**
     * Restrict all operations of this class to specific directories
     * @param array<int,string> $directoryPaths The allowed directories
     * @throws \UnexpectedValueException Throws if a given directory does not exist
     */
    public static function setAllowedDirectories(array $directoryPaths = array())
    {
        foreach ($directoryPaths as &$directoryPath)
        {
            $directoryPath = self::getNormalizedPath($directoryPath);
            if (!is_dir($directoryPath))
            {
                throw new \UnexpectedValueException('Directory does not exist!');
            }
        }
        unset($directoryPath);

        self::$allowedDirectories = $directoryPaths;
    }

    /**
     * Check if a given path is in the allowed directories
     * @param string $path The path to check
     * @throws \RuntimeException Throws if the given path is not in an allowed directory
     */
    private static function checkIsInAllowedDirectories(&$path)
    {
        $path = self::getNormalizedPath($path);

        if (count(self::$allowedDirectories) === 0)
        {
            return;
        }

        foreach (self::$allowedDirectories as $allowedDirectory)
        {
            if (strpos($path, $allowedDirectory) === 0)
            {
                return;
            }
        }

        throw new \RuntimeException('Path is not in valid directory!');
    }

    // INFO STUFF

    /**
     * Gets the total, free and used disk space in bytes
     * @param string $path Path of the filesystem
     * @return array<string,int> Returns the total, free and used disk space in bytes
     * @see https://secure.php.net/manual/en/function.disk-total-space.php
     * @see https://secure.php.net/manual/en/function.disk-free-space.php
     * @example array("total" => 10737418240, "free" => 2147483648, "used" => 8589934592)
     */
    public static function getDiskSpace($path = '/')
    {
        $total = (int) disk_total_space($path);
        $free = (int) disk_free_space($path);
        $used = $total - $free;

        return array('total' => $total, 'free' => $free, 'used' => $used);
    }

    /**
     * Gets info about the php-process owner
     * @return array<string,string|int> Returns info about the php-process owner
     * @see https://secure.php.net/manual/en/function.posix-geteuid.php
     * @see https://secure.php.net/manual/en/function.posix-getpwuid.php
     * @example
     * array(
     *     "name" => "max", "passwd" => "x", "uid" => 1000, "gid" => 1000,
     *     "gecos" => "max,,,", "dir" => "/home/max", "shell" => "/bin/bash"
     * )
     */
    public static function getProcessOwnerInfo()
    {
        return posix_getpwuid(posix_geteuid());
    }

    /**
     * Gets info about the php-process group
     * @return array<string,string|int|array> Returns info about the php-process group
     * @see https://secure.php.net/manual/en/function.posix-getegid.php
     * @see https://secure.php.net/manual/en/function.posix-getgrgid.php
     * @example array("name" => "max", "passwd" => "x", "members" => array(), "gid" => 1000)
     */
    public static function getProcessGroupInfo()
    {
        return posix_getgrgid(posix_getegid());
    }

    /**
     * Gets info about the path owner
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the fileowner determination fails
     * @return array<string,string|int> Returns info about the path owner
     * @see https://secure.php.net/manual/en/function.fileowner.php
     */
    public static function getPathOwnerInfo($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $fileOwnerResult = fileowner($path);
        if ($fileOwnerResult === false)
        {
            throw new \RuntimeException('File owner cannot be determined!');
        }

        return posix_getpwuid($fileOwnerResult);
    }

    /**
     * Gets info about the path group
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the groupowner determination fails
     * @return array<string,string|int|array> Returns info about the path group
     * @see https://secure.php.net/manual/en/function.filegroup.php
     */
    public static function getPathGroupInfo($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $fileGroupResult = filegroup($path);
        if ($fileGroupResult === false)
        {
            throw new \RuntimeException('File group cannot be determined!');
        }

        return posix_getgrgid($fileGroupResult);
    }

    /**
     * Checks if the php-process is the path owner
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the fileowner determination fails
     * @return bool Returns true if php-process is the path owner
     * @see https://secure.php.net/manual/en/function.posix-geteuid.php
     * @see https://secure.php.net/manual/en/function.posix-getpwuid.php
     * @see https://secure.php.net/manual/en/function.fileowner.php
     */
    public static function hasPathOwnerRight($path)
    {
        self::checkIsInAllowedDirectories($path);

        $processOwnerInfo = self::getProcessOwnerInfo();
        $pathOwnerInfo = self::getPathOwnerInfo($path);

        return $processOwnerInfo['uid'] === self::ROOT_ID || $processOwnerInfo['uid'] === $pathOwnerInfo['uid'];
    }

    /**
     * Gets the mode permissions of a path
     * @param string $path  The path from which to get the information
     * @param bool   $octal Set true to get the octal instead of the string mode representation
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if the permissions determination fails
     * @return string Returns the mode permissions of a path in octal or string representation
     * @see https://secure.php.net/manual/en/function.fileperms.php
     * @example "drwxrwxr-x" or "0775"
     */
    public static function getPathMode($path, $octal = false)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $perms = fileperms($path);
        if ($perms === false)
        {
            throw new \RuntimeException('File permissions cannot be read!');
        }

        if ($octal)
        {
            return substr(sprintf('%o', $perms), -4);
        }

        return self::convertPermsToString($perms);
    }

    /**
     * Convert file permissions to string representation
     * @param int $perms The file permissions
     * @return string Returns file permissions in string representation
     * @see https://secure.php.net/manual/en/function.fileperms.php
     */
    private static function convertPermsToString($perms)
    {
        switch ($perms & 0xF000)
        {
            case 0xC000: // Socket
                $info = 's';
                break;
            case 0xA000: // Symbolic Link
                $info = 'l';
                break;
            case 0x8000: // Regular
                $info = '-'; // r
                break;
            case 0x6000: // Block special
                $info = 'b';
                break;
            case 0x4000: // Directory
                $info = 'd';
                break;
            case 0x2000: // Character special
                $info = 'c';
                break;
            case 0x1000: // FIFO pipe
                $info = 'p';
                break;
            default: // unknown
                $info = 'u';
        }

        // User
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040)
            ? (($perms & 0x0800) ? 's' : 'x')
            : (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008)
            ? (($perms & 0x0400) ? 's' : 'x')
            : (($perms & 0x0400) ? 'S' : '-'));

        // Other
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001)
            ? (($perms & 0x0200) ? 't' : 'x')
            : (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

    /**
     * Gets owner, group and mode info from a path
     * @param string $path The path from which to get the information
     * @throws \UnexpectedValueException Throws if path does not exist
     * @throws \RuntimeException         Throws if a info determination fails
     * @return array<string,string> Returns owner, group and mode info from a path
     * @see https://secure.php.net/manual/en/function.fileowner.php
     * @see https://secure.php.net/manual/en/function.filegroup.php
     * @see https://secure.php.net/manual/en/function.fileperms.php
     * @example array("owner" => "www-data", "group" => "www", "mode" => "drwxrwxr-x")
     */
    public static function getPathPermissions($path)
    {
        self::checkIsInAllowedDirectories($path);

        $parentDirectoryPath = dirname($path);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!file_exists($path))
        {
            throw new \UnexpectedValueException('Path does not exist!');
        }

        $ownerInfo = self::getPathOwnerInfo($path);
        $groupInfo = self::getPathGroupInfo($path);

        return array(
            'owner' => $ownerInfo['name'],
            'group' => $groupInfo['name'],
            'mode' => self::getPathMode($path)
        );
    }

    // FOLDER STUFF

    /**
     * Creates a directory if it already did not exist
     * @param string              $directoryPath The directory to create
     * @param array<string,mixed> $options       Operation options ([int] mode, [int] modeParents, [bool] createDirectoryStructure)
     * @throws \UnexpectedValueException Throws if the parent directory is not writable
     * @throws \RuntimeException         Throws if the mkdir process fails
     * @return bool Returns true if directory was successfully created or false if directory did already exist
     * @see https://secure.php.net/manual/en/function.mkdir.php
     */
    public static function createDirectoryIfNotExists($directoryPath, array $options = array())
    {
        self::checkIsInAllowedDirectories($directoryPath);

        $options = array_merge(array('mode' => 0777, 'modeParents' => 0777, 'createDirectoryStructure' => true), $options);

        if (is_dir($directoryPath))
        {
            return false;
        }

        $parentDirectoryPath = dirname($directoryPath);
        if (!is_dir($parentDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                $parentOptions = $options;
                $parentOptions['mode'] = $options['modeParents'];
                self::createDirectoryIfNotExists($parentDirectoryPath, $parentOptions);
            }
            else
            {
                throw new \UnexpectedValueException('Parent directory does not exist!');
            }
        }
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        $mkdirResult = mkdir($directoryPath, $options['mode']);
        if (!$mkdirResult)
        {
            throw new \RuntimeException('Directory cannot be created!');
        }

        return true;
    }

    /**
     * Checks if a directory is empty
     * @param string $directoryPath The directory to check if is empty
     * @throws \UnexpectedValueException Throws if the directory is not readable
     * @throws \RuntimeException         Throws if the opendir process fails
     * @return bool Returns true if the directory is empty
     * @see https://secure.php.net/manual/en/function.opendir.php
     * @see https://secure.php.net/manual/en/function.readdir.php
     */
    public static function isDirectoryEmpty($directoryPath)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry !== '.' && $entry !== '..')
            {
                closedir($dirHandle);

                return false;
            }
        }
        closedir($dirHandle);

        return true;
    }

    /**
     * Gets the content of a directory and optional recursive from all subdirectories
     * @param string            $directoryPath        The directory from which to get the content
     * @param bool              $recursive            If true, also all subdirectories are scanned
     * @param bool              $fullPath             Set true to get the full paths instead of the content entry names
     * @param array<int,string> $includedContentTypes A list with all content types that should get returned (directories, files, links)
     * @throws \UnexpectedValueException Throws if directory is not readable
     * @throws \RuntimeException         Throws if the opendir process fails
     * @return array<string,string|array> The content of the directory (and all the subdirectories)
     * @see https://secure.php.net/manual/en/function.opendir.php
     * @see https://secure.php.net/manual/en/function.readdir.php
     */
    public static function getDirectoryContent($directoryPath, $recursive = false, $fullPath = true, array $includedContentTypes = array(self::CONTENT_TYPE_DIRECTORY, self::CONTENT_TYPE_FILE, self::CONTENT_TYPE_LINK))
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        $directoryContent = array();

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry === '.' || $entry === '..')
            {
                continue;
            }

            $directoryEntry = $directoryPath . DIRECTORY_SEPARATOR . $entry;
            $entryValue = $fullPath ? $directoryEntry : (string) $entry;

            if (is_dir($directoryEntry))
            {
                if ($recursive)
                {
                    $directoryContent[$entryValue] = self::getDirectoryContent($directoryEntry, $recursive, $fullPath, $includedContentTypes);
                }
                elseif (in_array(self::CONTENT_TYPE_DIRECTORY, $includedContentTypes, true))
                {
                    $directoryContent[$entryValue] = self::CONTENT_TYPE_DIRECTORY;
                }
            }
            elseif (is_file($directoryEntry) && in_array(self::CONTENT_TYPE_FILE, $includedContentTypes, true))
            {
                $directoryContent[$entryValue] = self::CONTENT_TYPE_FILE;
            }
            elseif (is_link($directoryEntry) && in_array(self::CONTENT_TYPE_LINK, $includedContentTypes, true))
            {
                $directoryContent[$entryValue] = self::CONTENT_TYPE_LINK;
            }
        }
        closedir($dirHandle);

        return $directoryContent;
    }

    /**
     * Deletes the content of a directory
     * @param string $directoryPath The directory where to delete the content
     * @throws \UnexpectedValueException Throws if directory is not writable and readable
     * @throws \RuntimeException         Throws if the unlink, rmdir or opendir process fails
     * @return bool Returns true if directory content was successfully deleted or false if directory was already empty
     * @see https://secure.php.net/manual/en/function.opendir.php
     * @see https://secure.php.net/manual/en/function.readdir.php
     */
    public static function deleteDirectoryContentIfExists($directoryPath)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }

        if (self::isDirectoryEmpty($directoryPath))
        {
            return false;
        }

        if (!is_writable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not writable!');
        }
        if (!is_readable($directoryPath))
        {
            throw new \UnexpectedValueException('Directory is not readable!');
        }

        $dirHandle = opendir($directoryPath);
        if ($dirHandle === false)
        {
            throw new \RuntimeException('Directory cannot be opened!');
        }

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($entry === '.' || $entry === '..')
            {
                continue;
            }

            $directoryEntry = $directoryPath . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($directoryEntry))
            {
                self::deleteDirectoryIfExists($directoryEntry, true);
            }
            else
            {
                self::deleteFileIfExists($directoryEntry);
            }
        }
        closedir($dirHandle);

        return true;
    }

    /**
     * Deletes a directory if it exists
     * @param string $directoryPath     The directory to delete
     * @param bool   $deleteWithContent If true, directory will also be deleted if directory is not empty
     * @throws \UnexpectedValueException Throws if the parent directory is not writable
     * @throws \RuntimeException         Throws if the rmdir or opendir process fails
     * @return bool Returns true if directory was successfully deleted or false if directory already did not exist
     * @see https://secure.php.net/manual/en/function.rmdir.php
     */
    public static function deleteDirectoryIfExists($directoryPath, $deleteWithContent = false)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if ($directoryPath === self::ROOT_FOLDER)
        {
            throw new \UnexpectedValueException('Directory "root" cannot be deleted!');
        }

        $parentDirectoryPath = dirname($directoryPath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_dir($directoryPath))
        {
            return false;
        }

        if (!self::isDirectoryEmpty($directoryPath))
        {
            if ($deleteWithContent)
            {
                self::deleteDirectoryContentIfExists($directoryPath);
            }
            else
            {
                throw new \UnexpectedValueException('Directory is not empty!');
            }
        }

        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        $rmdirResult = rmdir($directoryPath);
        if (!$rmdirResult)
        {
            throw new \RuntimeException('Directory cannot be deleted!');
        }

        return true;
    }

    /**
     * Checks if all preconditions are fulfilled
     * @param string             $oldDirectoryPath The source directory
     * @param string             $newDirectoryPath The destination directory
     * @param array<string,bool> $options          Operation options ([bool] createDirectoryStructure, [bool] overwriteContent)
     * @throws \UnexpectedValueException Throws if source directory is not readable, destination directory is not writable or a collision is detected
     * @throws \RuntimeException         Throws if the mkdir or opendir process fails
     * @return bool Returns true if content will get overwritten
     */
    private static function checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        self::checkIsInAllowedDirectories($oldDirectoryPath);
        self::checkIsInAllowedDirectories($newDirectoryPath);

        $options = array_merge(array('createDirectoryStructure' => true, 'overwriteContent' => false), $options);

        if (!is_dir($oldDirectoryPath))
        {
            throw new \UnexpectedValueException('Source directory does not exist!');
        }
        if (!is_readable($oldDirectoryPath))
        {
            throw new \UnexpectedValueException('Source directory is not readable!');
        }

        if (!is_dir($newDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                self::createDirectoryIfNotExists($newDirectoryPath);

                return false;
            }

            throw new \UnexpectedValueException('Destination directory does not exist!');
        }
        if (!is_executable($newDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination directory is not executable!');
        }
        if (!is_writable($newDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination directory is not writable!');
        }

        $oldDirectoryContentTree = self::getDirectoryContent($oldDirectoryPath, true, false);
        $newDirectoryContentTree = self::getDirectoryContent($newDirectoryPath, true, false);

        $collision = self::checkDirectoryContentTreeCollisions($oldDirectoryContentTree, $newDirectoryContentTree);
        if (!$collision)
        {
            return false;
        }
        if ($options['overwriteContent'])
        {
            return true;
        }

        throw new \UnexpectedValueException('Destination directory has collisions!');
    }

    /**
     * Checks if two directories have same files or directories
     * @param array<string,string|array> $directoryContentTree1       Thirst directory to check
     * @param array<string,string|array> $directoryContentTree2       Second directory to check
     * @param bool                       $considerDirectoryCollisions If true, also directory collisions are checked
     * @return bool Returns true if both directories has same files or directories
     */
    private static function checkDirectoryContentTreeCollisions(array $directoryContentTree1, array $directoryContentTree2, $considerDirectoryCollisions = false)
    {
        foreach ($directoryContentTree1 as $directoryContentName => $directoryContentType1)
        {
            if (array_key_exists($directoryContentName, $directoryContentTree2))
            {
                if ($considerDirectoryCollisions)
                {
                    return true;
                }

                $directoryContentType2 = $directoryContentTree2[$directoryContentName];

                if (!is_array($directoryContentType1) || !is_array($directoryContentType2))
                {
                    return true;
                }

                $collision = self::checkDirectoryContentTreeCollisions($directoryContentType1, $directoryContentType2, $considerDirectoryCollisions);
                if ($collision)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Execute the copy process to copy a directory
     * @param string $oldDirectoryPath The directory to copy
     * @param string $newDirectoryPath The destination directory
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the mkdir, copy or opendir process fails
     */
    private static function doCopyDirectory($oldDirectoryPath, $newDirectoryPath)
    {
        $oldDirectoryContent = self::getDirectoryContent($oldDirectoryPath, false, false);

        foreach ($oldDirectoryContent as $entry => $type)
        {
            $oldEntryPath = $oldDirectoryPath . DIRECTORY_SEPARATOR . $entry;
            $newEntryPath = $newDirectoryPath . DIRECTORY_SEPARATOR . $entry;

            if ($type === self::CONTENT_TYPE_DIRECTORY)
            {
                if (!is_dir($newEntryPath))
                {
                    self::createDirectoryIfNotExists($newEntryPath);
                }

                self::doCopyDirectory($oldEntryPath, $newEntryPath);
            }
            else
            {
                self::copyFile($oldEntryPath, $newEntryPath, array('overwrite' => true));
            }
        }
    }

    /**
     * Copies a directory
     * @param string             $oldDirectoryPath The directory to copy
     * @param string             $newDirectoryPath The destination directory
     * @param array<string,bool> $options          Operation options ([bool] createDirectoryStructure, [bool] overwriteContent)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the mkdir, copy or opendir process fails
     * @return bool Returns true if content was overwritten
     */
    public static function copyDirectory($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        $returnValue = self::checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, $options);

        self::doCopyDirectory($oldDirectoryPath, $newDirectoryPath);

        return $returnValue;
    }

    /**
     * Moves a directory
     * @param string             $oldDirectoryPath The directory to move
     * @param string             $newDirectoryPath The destination directory
     * @param array<string,bool> $options          Operation options ([bool] createDirectoryStructure, [bool] overwriteContent)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the mkdir, copy, rmdir, unlink or opendir process fails
     * @return bool Returns true if content was overwritten
     */
    public static function moveDirectory($oldDirectoryPath, $newDirectoryPath, array $options = array())
    {
        $returnValue = self::checkDirectoryPreconditions($oldDirectoryPath, $newDirectoryPath, $options);

        self::doCopyDirectory($oldDirectoryPath, $newDirectoryPath);

        self::deleteDirectoryIfExists($oldDirectoryPath, true);

        return $returnValue;
    }

    /**
     * Chmod a directory and optional recursive all subdirectories and files
     * @param string $directoryPath   The directory to chmod
     * @param int    $mode            The mode to set, in octal notation (e.g. 0755)
     * @param bool   $recursive       If true, subdirectories are chmod too
     * @param bool   $onlyDirectories If true, only directories gets chmod. If false all content gets chmod
     * @throws \UnexpectedValueException Throws if process is not directory owner
     * @throws \RuntimeException         Throws if the chmod or opendir process fails
     * @see https://secure.php.net/manual/en/function.chmod.php
     */
    public static function chmodDirectory($directoryPath, $mode, $recursive = false, $onlyDirectories = true)
    {
        self::checkIsInAllowedDirectories($directoryPath);

        if (!is_dir($directoryPath))
        {
            throw new \UnexpectedValueException('Directory does not exist!');
        }

        if (!self::hasPathOwnerRight($directoryPath))
        {
            throw new \UnexpectedValueException('Directory owner is different to process owner!');
        }

        $resultChmod = chmod($directoryPath, $mode);
        if (!$resultChmod)
        {
            throw new \RuntimeException('Directory mode cannot be changed!');
        }

        if ($recursive)
        {
            $directoryContent = self::getDirectoryContent($directoryPath);

            foreach ($directoryContent as $entryPath => $type)
            {
                if ($type === self::CONTENT_TYPE_DIRECTORY)
                {
                    self::chmodDirectory($entryPath, $mode, $recursive, $onlyDirectories);
                }
                elseif (!$onlyDirectories)
                {
                    self::chmodFile($entryPath, $mode);
                }
            }
        }
    }

    // FILE STUFF

    /**
     * Deletes a file if it exists
     * @param string $filePath The file to delete
     * @throws \UnexpectedValueException Throws if the file is not writable
     * @throws \RuntimeException         Throws if the delete process fails
     * @return bool Returns true if file was successfully deleted or false if file already did not exist
     * @see https://secure.php.net/manual/en/function.unlink.php
     */
    public static function deleteFileIfExists($filePath)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }
        if (!is_writable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not writable!');
        }

        if (!is_file($filePath))
        {
            return false;
        }

        $unlinkResult = unlink($filePath);
        if (!$unlinkResult)
        {
            throw new \RuntimeException('File cannot be deleted!');
        }

        return true;
    }

    /**
     * Checks the preconditions for tile copy and move
     * @param string             $mode        The operation mode (copy or move)
     * @param string             $oldFilePath The source path
     * @param string             $newFilePath The destination path
     * @param array<string,bool> $options     Operation options ([bool] createDirectoryStructure, [bool] overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the destination folder could not be created
     * @return bool Returns true if the destination path will be overwritten
     */
    private static function checkFilePreconditions($mode, $oldFilePath, $newFilePath, array $options = array())
    {
        $options = array_merge(array('createDirectoryStructure' => true, 'overwrite' => false), $options);

        self::checkIsInAllowedDirectories($oldFilePath);
        self::checkIsInAllowedDirectories($newFilePath);

        $oldParentDirectoryPath = dirname($oldFilePath);
        if ($mode === 'move' && !is_executable($oldParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Source parent directory is not executable!');
        }
        if (!is_writable($oldParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Source parent directory is not writable!');
        }

        if (!is_file($oldFilePath))
        {
            throw new \UnexpectedValueException('Source file does not exist!');
        }
        if ($mode === 'copy' && !is_readable($oldFilePath))
        {
            throw new \UnexpectedValueException('Source file is not readable!');
        }

        $newParentDirectoryPath = dirname($newFilePath);
        if (!is_dir($newParentDirectoryPath))
        {
            if ($options['createDirectoryStructure'])
            {
                self::createDirectoryIfNotExists($newParentDirectoryPath);

                return false;
            }

            throw new \UnexpectedValueException('Destination parent directory does not exist!');
        }
        if (!is_executable($newParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination parent directory is not executable!');
        }
        if (!is_writable($newParentDirectoryPath))
        {
            throw new \UnexpectedValueException('Destination parent directory is not writable!');
        }

        if (!is_file($newFilePath))
        {
            return false;
        }
        if ($options['overwrite'])
        {
            return true;
        }

        throw new \UnexpectedValueException('Destination file already exist!');
    }

    /**
     * Copies a file
     * @param string             $oldFilePath The file to copy
     * @param string             $newFilePath The path where to copy to
     * @param array<string,bool> $options     Operation options ([bool] createDirectoryStructure, [bool] overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the copy process fails
     * @return bool Returns true if the destination path was overwritten
     * @see https://secure.php.net/manual/en/function.copy.php
     */
    public static function copyFile($oldFilePath, $newFilePath, array $options = array())
    {
        $returnValue = self::checkFilePreconditions('copy', $oldFilePath, $newFilePath, $options);

        $copyResult = copy($oldFilePath, $newFilePath);
        if (!$copyResult)
        {
            throw new \RuntimeException('File cannot be copied!');
        }

        return $returnValue;
    }

    /**
     * Moves a file
     * @param string             $oldFilePath The file to move
     * @param string             $newFilePath The path where to move to
     * @param array<string,bool> $options     Operation options ([bool] createDirectoryStructure, [bool] overwrite)
     * @throws \UnexpectedValueException Throws if a precondition is not fulfilled
     * @throws \RuntimeException         Throws if the move process fails
     * @return bool Returns true if the destination path was overwritten
     * @see https://secure.php.net/manual/en/function.rename.php
     */
    public static function moveFile($oldFilePath, $newFilePath, array $options = array())
    {
        $returnValue = self::checkFilePreconditions('move', $oldFilePath, $newFilePath, $options);

        $renameResult = rename($oldFilePath, $newFilePath);
        if (!$renameResult)
        {
            throw new \RuntimeException('File cannot be moved!');
        }

        return $returnValue;
    }

    /**
     * @param string $filePath The file to chmod
     * @param int    $mode     The mode to set in octal notation (e.g. 0755)
     * @throws \UnexpectedValueException Throws if the file does not exist or is not chmod-able
     * @throws \RuntimeException         Throws if the chmod process fails
     * @see https://secure.php.net/manual/en/function.chmod.php
     */
    public static function chmodFile($filePath, $mode)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!is_file($filePath))
        {
            throw new \UnexpectedValueException('File does not exist!');
        }
        if (!self::hasPathOwnerRight($filePath))
        {
            throw new \UnexpectedValueException('File owner is different to process owner!');
        }

        $chmodResult = chmod($filePath, $mode);
        if (!$chmodResult)
        {
            throw new \RuntimeException('File mode cannot be changed!');
        }
    }

    /**
     * @param string $filePath The file to read
     * @throws \UnexpectedValueException Throws if the file does not exist or is not readable
     * @throws \RuntimeException         Throws if the read process fails
     * @return string Returns the file content
     * @see https://secure.php.net/manual/en/function.file-get-contents.php
     */
    public static function readFile($filePath)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (!is_file($filePath))
        {
            throw new \UnexpectedValueException('File does not exist!');
        }
        if (!is_readable($filePath))
        {
            throw new \UnexpectedValueException('File is not readable!');
        }

        $data = file_get_contents($filePath);
        if ($data === false)
        {
            throw new \RuntimeException('File cannot be read!');
        }

        return $data;
    }

    /**
     * Write some data into a file
     * @param string $filePath The file to write
     * @param string $data     The data to write
     * @param bool   $append   If true the data gets appended instead of overwriting the content
     * @throws \UnexpectedValueException Throws if the file or parent directory is not writable
     * @throws \RuntimeException         Throws if the write process fails
     * @return int Returns the written bytes
     * @see https://secure.php.net/manual/en/function.file-put-contents.php
     */
    public static function writeFile($filePath, $data, $append = false)
    {
        self::checkIsInAllowedDirectories($filePath);

        $parentDirectoryPath = dirname($filePath);
        if (!is_executable($parentDirectoryPath))
        {
            throw new \UnexpectedValueException('Parent directory is not executable!');
        }

        if (is_file($filePath))
        {
            if (!is_writable($filePath))
            {
                throw new \UnexpectedValueException('File is not writable!');
            }
        }
        else
        {
            if (!is_writable($parentDirectoryPath))
            {
                throw new \UnexpectedValueException('Parent directory is not writable!');
            }
        }

        $flags = 0;
        if ($append)
        {
            $flags = FILE_APPEND;
        }

        $bytes = file_put_contents($filePath, $data, $flags);
        if ($bytes === false)
        {
            throw new \RuntimeException('File cannot be written!');
        }

        return $bytes;
    }
}
