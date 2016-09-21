<?php

// 

register_shutdown_function(function () {
    if ($e = error_get_last()) {
        switch ($e['type']) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                printf('[PHP_ERROR]%s[PHP_ERROR]', json_encode($e));
        }
    }
});

define('REQUEST_SCHEME', empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https');
define('DS', DIRECTORY_SEPARATOR);
define('PATH_TO_ROOT', '../../../../..');
define('ROOT', getenv('HTML_APP_ROOT') ? getenv('HTML_APP_ROOT') : realpath(PATH_TO_ROOT));
define('HTTP_ROOT', REQUEST_SCHEME . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . PATH_TO_ROOT);
define('TMP', ROOT . DS . 'tmp');

function unpackFso($fso, $path) {
    if (!is_array($fso['items'])) {
        return;
    }

    mk_d($path);

    if (!is_writable($path)) {
        throw new Exception('Permission denied: ' . $path);
    }

    foreach ($fso['items'] as $name => $file) {
        if (isset($file['content']) && isset($file['type'])) {
            if ($file['content'] === '[DELETED]') {
                if (is_writable($path . DS . $name)) {
                    unlink($path . DS . $name);
                }
            } else {
                switch ($file['type']) {
                    case 'text':
                        file_put_contents($path . DS . $name, $file['content']);
                        break;
                    case 'data':
                        file_put_contents($path . DS . $name, base64_decode($file['content']));
                        break;
                }
            }
        } else if (isset($file['items']) && isset($file['type'])) {
            unpackFso($file, $path . DS . $name);
        }
    }
}

function packFso($path) {
    $result = array();

    if (is_file($path)) {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new Exception('Permission denied: ' . $path);
        }
        $type = 'text';
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        if (in_array($ext, ['jpg', 'jpeg', 'bmp', 'png', 'gif', 'svg'])) {
            $type = 'data';
            $content = base64_encode($content);
        }

        $result = array('type' => $type, 'content' => $content);
    } else if (is_dir($path)) {
        $result = array('type' => 'dir', 'items' => array());

        if ($d = opendir($path)) {
            while (($name = readdir($d)) !== false) {
                if (in_array($name, array('.', '..'))) {
                    continue;
                }

                $result['items'][$name] = packFso($path . DS . $name);
            }
            closedir($d);
        }
    }

    return $result;
}

function uploadChunk($uploadPath)
{
    $result = array();

    $contentRange = getHeader('Content-Range');
    $isLast = getParam('last');

    if (!isset($_FILES['chunk']) || !file_exists($_FILES['chunk']['tmp_name'])) {
        $result = array(
            'status' => 'error',
            'message' => 'Empty chunk data'
        );
    } else if (!$contentRange && !$isLast) {
        $result = array(
            'status' => 'error',
            'message' => 'Empty Content-Range header'
        );
    } else {
        $rangeBegin = 0;

        if ($contentRange) {
            $contentRange = str_replace('bytes ', '', $contentRange);
            list($range, ) = explode('/', $contentRange);
            list($rangeBegin, ) = explode('-', $range);
        }

        $tmpPath = $uploadPath . '.upload';
        mk_d(dirname($tmpPath));

        $f = fopen($tmpPath, 'c');

        if (flock($f, LOCK_EX)) {
            fseek($f, (int) $rangeBegin);
            fwrite($f, file_get_contents($_FILES['chunk']['tmp_name']));

            flock($f, LOCK_UN);
            fclose($f);
        } else {
            throw new PermissionException('Permission denied: ' . $tmpPath);
        }

        if ($isLast) {
            if (file_exists($uploadPath) && is_writable($uploadPath)) {
                unlink($uploadPath);
            }

            mk_d(dirname($uploadPath));

            if (!is_writable(dirname($uploadPath))) {
                throw new PermissionException('Permission denied: ' . $uploadPath);
            }

            if (!is_writable($tmpPath)) {
                throw new PermissionException('Permission denied: ' . $tmpPath);
            }

            rename($tmpPath, $uploadPath);

            $result = array(
                'status' => 'done'
            );
        } else {
            $result['status'] = 'processed';
        }
    }

    return $result;
}

function performGlob($mask, $flags)
{
    $files = glob($mask, $flags);

    if (!is_array($files)) {
        $files = array();
    }

    $folders = glob(dirname($mask) . '/*', GLOB_ONLYDIR | GLOB_NOSORT);

    if (!is_array($folders)) {
        $folders = array();
    }

    foreach ($folders as $dir) {
        $files = array_merge($files, performGlob($dir . '/' . basename($mask), $flags));
    }

    return $files;
}

function enumerateDir($dir, $filter = null, $option = RecursiveIteratorIterator::SELF_FIRST) {
    $list = [];

    if (!$filter) {
        $filter = function () {
            return true;
        };
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            $filter
        ),
        $option
    );

    foreach ($iterator as $item) {
        $f = new stdClass;
        $f->isDir = $item->isDir();
        $f->subPathName = $iterator->getSubPathName();
        $f->realPath = $item->getRealPath();
        $f->fileName = $item->getFilename();
        $list[] = $f;
    }

    $iterator = null;

    return $list;
}

function rm_r($dir, $exclude = [], $deleteRoot = false)
{
    if (!is_dir($dir)) {
        if (is_writable($dir)) {
            unlink($dir);
        }
        return false;
    }

    if (!file_exists($dir)) {
        return false;
    }

    $list = enumerateDir($dir, function ($current) use ($exclude) {
        return !in_array($current->getFilename(), $exclude);
    }, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($list as $item) {
        if (!is_writable($item->realPath)) continue;
        if ($item->isDir) {
            @rmdir($item->realPath);
        } else {
            unlink($item->realPath);
        }
    }

    if ($deleteRoot && is_writable($dir)) {
        return @rmdir($dir);
    }

    return true;
}

function cp_r($source, $dest, $exclude = [])
{
    mk_d($dest);

    $list = enumerateDir($source, function ($current) use ($exclude) {
        return !in_array($current->getFilename(), $exclude);
    });

    foreach ($list as $item) {
        if ($item->isDir) {
            mk_d($dest . DS . $item->subPathName);
        } else {
            if (!copy($item->realPath, $dest . DS . $item->subPathName)) {
                throw new PermissionException(
                    'Permission denied: copy ' . $item->realPath . ' to ' . $dest . DS . $item->subPathName
                );
            }
        }
    }
}

function mk_d($dir, $mode = 0777, $recursive = true) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, $mode, $recursive)) {
            throw new PermissionException('Permission denied: ' . $dir);
        }
    }
}

function checkDirPermissions($path)
{
    return is_dir($path) && is_writable($path) && is_readable($path);
}

function checkFilePermissions($path)
{
    return file_exists($path) && is_writable($path) && is_readable($path);
}

function dispatch()
{
    $action = empty($_REQUEST['action']) ? '' : preg_replace('/[^a-z]/i', '', $_REQUEST['action']);

    if (function_exists($action . 'Action')) {
        try {
            checkThemeInstallation();
            $result = call_user_func($action . 'Action');
        } catch (PermissionException $e) {
            $result = [
                'status' => 'error',
                'result' => 'error',
                'type' => 'permission',
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ];
        } catch (Exception $e) {
            $result = [
                'status' => 'error',
                'result' => 'error',
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ];
        }
    } else {
        $result = ['status' => 'error', 'message' => 'no action'];
    }

    echo is_array($result) ? json_encode($result) : $result;
}

function getParam($name, $default = null)
{
    return empty($_REQUEST[$name]) ? $default : $_REQUEST[$name];
}

function getPost($name, $default = null)
{
    return empty($_POST[$name]) ? $default : $_POST[$name];
}

function getHeader($header)
{
    $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
    if (isset($_SERVER[$temp])) {
        return $_SERVER[$temp];
    }

    return null;
}

function getAvailableThemeName($path, $themeName)
{
    while (file_exists($path . DS . $themeName)) {
        preg_match('#(.*?)(\d{0,4})$#', $themeName, $m);
        $themeName = $m[1];
        $suffix = (int) $m[2];
        $suffix++;
        $themeName .= $suffix;
    };

    return $themeName;
}

function checkThemeInstallation() {
    if (!file_exists(THEMES)) {
        die('[PHP_NOT_ERROR]{"message": "Incorrect theme installation. Please follow the steps described at <a href=\"http://answers.themler.com/articles/25259\" target=\"_blank\">http://answers.themler.com/articles/25259</a>"}[PHP_NOT_ERROR]');
    }
}

class ZipHelper
{
    public static function zip($source, $wrapDir = '', $exclude = []) {
        if ($wrapDir && substr($wrapDir, -1) !== '/')
            $wrapDir .= '/';

        $outPath = tempnam('tmp', 'zip');
        $z = new ZipArchive();
        $res = $z->open($outPath, ZipArchive::CREATE);

        if ($res === true) {
            $list = enumerateDir($source, function ($current) use ($exclude) {
                return !in_array($current->getFilename(), $exclude);
            });

            foreach ($list as $item) {
                if (!$item->isDir) {
                    $z->addFile($item->realPath, $wrapDir . $item->subPathName);
                }
            }

            $z->close();
        } else {
            return ['status' => 'error', 'message' => self::message($res)];
        }

        return ['status' => 'done', 'path' => $outPath];
    }

    public static function unzip($file, $target) {
        $zip = new ZipArchive;
        $res = $zip->open($file);
        mk_d($target);

        if ($res === true) {
            for($i = 0; $i < $zip->numFiles; $i++) {
                $data = $zip->getFromIndex($i);
                $filename = preg_replace('#[/\\\]#', DS, $zip->getNameIndex($i));
                $dest = $target . DS . $filename;

                if (substr($dest, -1) !== DS) {
                    mk_d(dirname($dest));
                    file_put_contents($dest, $data);
                }
            }

            $zip->close();
            return ['status' => 'done'];
        } else {
            return ['status' => 'error', 'message' => self::message($res)];
        }
    }

    public static function unzipString($str, $key) {
        $zipPath = tempnam('tmp', 'str_zip');
        file_put_contents($zipPath, $str);

        $unzipPath = THEME_TMP . DS . 'unzip_string';
        mk_d($unzipPath);

        $result = self::unzip($zipPath, $unzipPath);
        if ($result['status'] === 'done' && file_exists($unzipPath . DS . $key)) {
            $result['data'] = file_get_contents($unzipPath . DS . $key);
        } else {
            $result['message'] = 'unzip error';
        }

        rm_r($unzipPath, [], true);

        return $result;
    }

    public static function message($code)
    {
        switch ($code)
        {
            case 0:
            return 'No error';

            case 1:
            return 'Multi-disk zip archives not supported';

            case 2:
            return 'Renaming temporary file failed';

            case 3:
            return 'Closing zip archive failed';

            case 4:
            return 'Seek error';

            case 5:
            return 'Read error';

            case 6:
            return 'Write error';

            case 7:
            return 'CRC error';

            case 8:
            return 'Containing zip archive was closed';

            case 9:
            return 'No such file';

            case 10:
            return 'File already exists';

            case 11:
            return 'Can\'t open file';

            case 12:
            return 'Failure to create temporary file';

            case 13:
            return 'Zlib error';

            case 14:
            return 'Malloc failure';

            case 15:
            return 'Entry has been changed';

            case 16:
            return 'Compression method not supported';

            case 17:
            return 'Premature EOF';

            case 18:
            return 'Invalid argument';

            case 19:
            return 'Not a zip archive';

            case 20:
            return 'Internal error';

            case 21:
            return 'Zip archive inconsistent';

            case 22:
            return 'Can\'t remove file';

            case 23:
            return 'Entry has been deleted';

            default:
            return 'An unknown error has occurred(' . intval($code) . ')';
        }
    }
}


class Chunk
{
    private $_lastChunk = null;
    private $_chunkFolder = '';
    private $_lockFile = '';
    private $_isLast = false;

    public function save($info) {
        if (!$this->validate($info)) {
            return false;
        }

        $this->_lastChunk = $info;
        $this->_chunkFolder = THEME_TMP . DS . 'chunk' . DS . $info['id'];
        $this->_lockFile = $this->_chunkFolder . DS . 'lock';

        mk_d($this->_chunkFolder);

        if (!checkDirPermissions($this->_chunkFolder)) {
            throw new PermissionException('Incorrect permissions for ' . $this->_chunkFolder);
        } else {
            $f = fopen($this->_lockFile, 'c');

            if (flock($f, LOCK_EX)) {
                $chunks = array_diff(scandir($this->_chunkFolder), array('.', '..', 'lock'));

                if ((int)$this->_lastChunk['total'] === count($chunks) + 1) {
                    $this->_isLast = true;
                }

                if (!empty($this->_lastChunk['blob'])) {
                    if (empty($_FILES['content']['tmp_name'])) {
                        return false;
                    }

                    move_uploaded_file(
                        $_FILES['content']['tmp_name'],
                        $this->_chunkFolder . DS . (int) $info['current']
                    );
                } else {
                    file_put_contents($this->_chunkFolder . DS . (int) $info['current'], $info['content']);
                }

                flock($f, LOCK_UN);

                return true;
            } else {
                throw new PermissionException('Couldn\'t lock the file');
            }
        }
    }

    public function last() {
        return $this->_isLast;
    }

    public function complete() {
        $content = '';

        for ($i = 1, $count = (int) $this->_lastChunk['total']; $i <= $count; $i++) {
            if (!file_exists($this->_chunkFolder . DS . $i)) {
                throw new Exception(
                    'Missing chunk #' . $i . ' : ' . implode(' / ', scandir($this->_chunkFolder))
                );
            }

            $data = file_get_contents($this->_chunkFolder . DS . $i);

            if (!empty($this->_lastChunk['encode']) || !empty($this->_lastChunk['zip'])) {
                $data = base64_decode($data);
            }

            $content .= $data;
        }

        rm_r($this->_chunkFolder, [], true);

        if (!empty($this->_lastChunk['zip'])) {
            $result = ZipHelper::unzipString($content, 'data');
        } else if (!empty($this->_lastChunk['encode'])) {
            $result = [
                'status' => 'done',
                'data' => rawurldecode($content)
            ];
        } else {
            $result = [
                'status' => 'done',
                'data' => $content
            ];
        }

        return $result;
    }

    private function validate($info) {
        return !empty($info['id'])      &&
            isset($info['total'])       && (int) $info['total']   >= 1 &&
            isset($info['current'])     && (int) $info['current'] >= 1 &&
            (!empty($_FILES['content']) || !empty($info['content']));
    }

    public static function getInfo() {
        return [
            'id'      => getParam('id', ''),
            'content' => getParam('content', ''),
            'current' => getParam('current', ''),
            'total'   => getParam('total', ''),
            'encode'  => getParam('encode', false),
            'blob'    => getParam('blob', false),
            'zip'     => getParam('zip', false)
        ];
    }

    public static function process($success) {
        $info = self::getInfo();

        $chunk = new self();

        if (!$chunk->save($info)) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            throw new Exception('Chunk save retry');
        }

        if ($chunk->last()) {
            $result = $chunk->complete();
            if ($result['status'] === 'done') {
                $response = $success(json_decode($result['data'], true));
            } else {
                $result['result'] = 'error';
                $response = $result;
            }
            self::clear();
        } else {
            $response = ['result' => 'processed'];
        }

        return $response;
    }

    public static function clear() {
        rm_r(THEME_TMP . DS . 'chunk', [], true);
    }

}

class PermissionException extends Exception {

}

//