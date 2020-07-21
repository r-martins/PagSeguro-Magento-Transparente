<?php

// 

include 'common.php';

define('THEME', basename(getenv('HTML_THEME_ROOT') ? getenv('HTML_THEME_ROOT') : realpath('../../../')));
define('THEME_TMP', TMP . DS . THEME);

define('MANIFEST',               ROOT . DS . 'manifest');
define('THEMES',                 ROOT . DS . 'themes');
define('THEME_ROOT',             THEMES . DS . THEME);
define('THEME_PREVIEW',          join(DS, [THEME_ROOT, '__editor__', 'data', 'preview']));
define('THEME_MANIFEST',         join(DS, [THEME_ROOT, '__manifest__']));
define('THEME_RUNTIME',          join(DS, [THEME_ROOT, '__editor__', 'runtime']));
define('THEME_RUNTIME_PREVIEW',  join(DS, [THEME_ROOT, '__editor__', 'runtime', '__preview__']));
define('THEME_RUNTIME_MANIFEST', join(DS, [THEME_ROOT, '__editor__', 'runtime', '__manifest__']));

define('THEME_URL',         HTTP_ROOT . '/themes/' . THEME);
define('THEME_PREVIEW_URL', HTTP_ROOT . '/themes/' . THEME . '/__editor__/runtime');

function startAction()
{
    mk_d(TMP);
    mk_d(THEME_TMP);

    rm_r(THEME_RUNTIME);

    cp_r(THEME_ROOT, THEME_RUNTIME, ['__editor__']);
    cp_r(THEME_PREVIEW, THEME_RUNTIME_PREVIEW);

    return ['status' => 'done'];
}

function clearAction()
{
    $result = ['status' => 'done'];

    Chunk::clear();

    return $result;
}

function exportAction()
{
    return Chunk::process(function ($content) {
        $method = getPost('method');

        if ($content) {
            switch ($method) {
                case 'update':
                case 'save':
                    unpackFso($content, THEME_RUNTIME);
                    break;
                case 'setFiles':
                    unpackFso($content, THEME_ROOT);
                    break;
            }
        }

        if ('save' === $method) {
            rm_r(THEME_ROOT, ['__editor__']);
            rm_r(THEME_PREVIEW);

            cp_r(THEME_RUNTIME, THEME_ROOT, ['__preview__', '__manifest__']);
            cp_r(THEME_RUNTIME_PREVIEW, THEME_PREVIEW);
            if (is_dir(THEME_RUNTIME_MANIFEST)) {
                cp_r(THEME_RUNTIME_MANIFEST, MANIFEST);
            }
        } else if ('setFiles' === $method) {
            if (is_dir(THEME_MANIFEST)) {
                cp_r(THEME_MANIFEST, MANIFEST);
                rm_r(THEME_MANIFEST, [], true);
            }
        }

        if (getPost('publish') && file_exists(join(DS, [THEME_ROOT, 'assets', 'css', 'style.min.css']))) {
            $pageFiles = enumerateDir(THEME_ROOT, function ($current) {
                $ext = pathinfo($current->getFilename(), PATHINFO_EXTENSION);
                return strpos($current->getRealPath(), '__editor__') === false && $ext === 'html';
            });

            foreach ($pageFiles as $pageFile) {
                $content = file_get_contents($pageFile->realPath);
                $content = preg_replace('#(style|bootstrap|style\.ie)\.(css)"#', '$1.min.$2"', $content);
                file_put_contents($pageFile->realPath, $content);
            }
        }

        return ['status' => 'done', 'result' => 'done'];
    });
}

function getFilesAction()
{
    $mask   = getPost('mask', '*');
    $filter = getPost('filter', '');

    $files = [];

    foreach (performGlob(THEME_ROOT . '/{' . $mask . '}', GLOB_BRACE) as $file) {
        $filename = preg_replace('#[\\/]+#', '/', $file);
        $filename = str_replace(THEME_ROOT, '', $filename);

        if (is_dir($file) ||
            $filter && preg_match("#$filter#", $filename) ||
            strpos($filename, '__editor__') !== false) {

            continue;
        }

        if (!is_readable($file)) {
            throw new PermissionException('Read permission denied: ' . $file);
        }

        $files[$filename] = file_get_contents($file);
    }

    return ['status' => 'done', 'files' => $files];
}

function zipAction()
{
    $source = getParam('sourceName');
    $target = getParam('targetName', $source);
    $includeEditor = (bool) json_decode(getParam('editor'));

    $sourcePath = $source ?
        THEMES . DS . $source :
        THEME_ROOT;
    $exclude = $includeEditor ? ['runtime'] : ['__editor__'];

    if (!file_exists($sourcePath)) {
        throw new Exception('Invalid source');
    }

    if ($includeEditor) {
        $result = ZipHelper::zip($sourcePath, 'themes/' . $target, $exclude);

        $manifest = $sourcePath . "/__editor__/themler.manifest";
        $editor = $sourcePath . "/__editor__/data/project/editor.json";

        $z = new ZipArchive();
        $z->open($result['path']);

        if ($result['status'] === 'done' && file_exists($manifest) && file_exists($editor)) {
            $cfg = json_decode(file_get_contents($editor));

            if ($cfg && isset($cfg->version)) {
                $z->addFile($manifest, 'manifest/' . $cfg->version . '.manifest');
            }
        }

        $readme = <<<DOC
To run Themler open this url in your browser:
http://host/themes/[theme_name]/__editor__/app.html

We strongly recommend to password protect http://host/themes/[theme_name]/__editor__/app.html folder to prevent unauthorized access to the content by a third party.
DOC;

        $z->addFromString('README.txt', $readme);
        $z->close();
    } else {
        $result = ZipHelper::zip($sourcePath, $target, $exclude);
    }

    if ($result['status'] === 'error') {
        return $result;
    } else {
        if (!getenv('NO_HEADERS')) {
            header('Content-Type: application/zip');
            header('Content-Disposition: inline; filename="' . $target . '.zip"');
        }
        $file = file_get_contents($result['path']);
        unlink($result['path']);
        return $file;
    }
}

function canRenameAction()
{
    $result = [
        'status' => 'done',
        'canRename' => false
    ];

    $themeName = getParam('themeName');

    if ($themeName) {
        $result['canRename'] = !file_exists(THEMES . DS . $themeName);
    }

    return $result;
}

function renameAction()
{
    $source = getParam('source');
    $target = getParam('target');

    $result = ['status' => 'done'];

    if (!$source || !file_exists(THEMES . DS . $source)) {
        $result = [
            'status' => 'error',
            'message' => 'Invalid source theme'
        ];
    } else if ($source === THEME && !$target) {
        $result = [
            'status' => 'error',
            'message' => 'Operation permitted'
        ];
    } else if ($target && file_exists(THEMES . DS . $target)) {
        $result = [
            'status' => 'error',
            'message' => 'Target already exists'
        ];
    } else if (!checkDirPermissions(THEMES . DS . $source)) {
        $result = [
            'status'  => 'error',
            'type'    => 'permission',
            'message' => 'Access denied: ' . THEMES . DS . $source
        ];
    } else if (!$target) {
        rm_r(THEMES . DS . $source, [], true);
    } else {
        rename(THEMES . DS . $source, THEMES . DS . $target);
    }

    return $result;
}

function copyAction()
{
    $source = getParam('source');
    $target = getParam('target');

    $result = ['status' => 'done'];

    if (!$source || !file_exists(THEMES . DS . $source)) {
        $result = [
            'status' => 'error',
            'message' => 'Invalid source theme'
        ];
    } else {
        cp_r(THEMES . DS . $source, THEMES . DS . getAvailableThemeName(THEMES, $target));
    }

    return $result;
}

function imageAction()
{
    $filename = getParam('filename');
    $isContent = getParam('isContent') && false; // TODO

    if (!$filename) {
        $result = array(
            'status' => 'error',
            'message' => 'Empty file name'
        );
    } else {
        $uploadPath = THEME_RUNTIME . '/assets/images/' . $filename;

        try {
            $result = uploadChunk($uploadPath);
            if ($result['status'] === 'done') {
                $result['url'] = ($isContent ? '' : THEME_PREVIEW_URL . '/assets/images/') . $filename;
            }
        } catch (Exception $e) {
            if (is_writable($uploadPath)) {
                unlink($uploadPath);
            }
            throw $e;
        }
    }

    return $result;
}

function themeAction()
{
    $filename = getParam('filename');

    if (!$filename) {
        $result = array(
            'status' => 'error',
            'message' => 'Empty file name'
        );
    } else {
        $tempDir = THEME_TMP . DS . 'temptheme';
        $uploadPath = $tempDir . DS . $filename;

        try {
            $result = uploadChunk($uploadPath);

            if ($result['status'] === 'done') {
                $result = ZipHelper::unzip($uploadPath, $tempDir);

                if ($result['status'] === 'error') {
                    rm_r($tempDir, [], true);
                    throw new Exception($result['message']);
                }

                $list = array_diff(scandir($tempDir), ['.', '..']);
                $themeDir = $tempDir;

                while (current($list) !== false && !is_dir($tempDir . DS . current($list))) {
                    next($list);
                }

                if (current($list) !== false &&
                    !is_dir(join(DS, [$tempDir, current($list), '__editor__'])) &&
                    is_dir(join(DS, [$tempDir, 'themes'])) &&
                    is_dir(join(DS, [$tempDir, 'manifest']))) {

                    $list = array_diff(scandir(join(DS, [$tempDir, 'themes'])), ['.', '..']);
                    $themeDir = join(DS, [$tempDir, 'themes']);
                }

                if (current($list) === false) {
                    rm_r($tempDir, [], true);
                    throw new Exception('Unable to open theme.<br>Only Themler HTML themes are supported.');
                }

                $theme = getAvailableThemeName(THEMES, current($list));
                cp_r($themeDir . DS . current($list), THEMES . DS . $theme);

                $manifest = THEMES . "/$theme/__editor__/themler.manifest";
                $editor = THEMES . "/$theme/__editor__/data/project/editor.json";

                if (file_exists($manifest) && file_exists($editor)) {
                    $cfg = json_decode(file_get_contents($editor));

                    if ($cfg && isset($cfg->version)) {
                        mk_d(MANIFEST);
                        copy($manifest, MANIFEST . DS . $cfg->version . '.manifest');
                    }
                }

                rm_r($tempDir, [], true);
            }
        } catch (Exception $e) {
            rm_r($tempDir, [], true);
            throw $e;
        }
    }

    return $result;
}

function fsoToZipAction() {
    return Chunk::process(function ($content) {
        $tempDir = THEME_TMP . DS . 'tempfso';
        rm_r($tempDir, [], true);
        unpackFso($content['fso'], $tempDir);
        $result = ZipHelper::zip($tempDir);

        if ($result['status'] === 'done') {
            $zipPath = $result['path'];
            $result = [
                'status' => 'done',
                'result' => 'done',
                'data' => base64_encode(file_get_contents($zipPath))
            ];
            rm_r($zipPath);
        } else {
            $result = [
                'status' => 'error',
                'message' => $result['message']
            ];
        }

        rm_r($tempDir, [], true);

        return $result;
    });
}

function zipToFsoAction() {
    $filename = getParam('filename');

    if (!$filename) {
        $result = array(
            'status' => 'error',
            'message' => 'Empty file name'
        );
    } else {
        $tempDir = THEME_TMP . DS . 'tempzip';
        $uploadPath = $tempDir . DS . $filename;

        try {
            $result = uploadChunk($uploadPath);
            if ($result['status'] === 'done') {
                $extractPath = $uploadPath . '_contents';
                $result = ZipHelper::unzip($uploadPath, $extractPath);
                if ($result['status'] === 'done') {
                    $result['fso'] = packFso($extractPath);
                }

                rm_r($tempDir, [], true);
            }
        } catch (Exception $e) {
            rm_r($tempDir, [], true);
            throw $e;
        }
    }

    return $result;
}

function themesAction() {
    $result = [
        'status' => 'done',
        'themes' => []
    ];

    $list = array_diff(scandir(THEMES), ['.', '..']);

    foreach ($list as $theme) {
        $params = [];
        $editor = THEMES . DS . $theme . DS . '__editor__/data/project/editor.json';
        if (!file_exists($editor)) continue;

        if ($j = json_decode(file_get_contents($editor), true)) {
            if (!empty($j['version'])) {
                $params[] = 'ver=' . $j['version'];
            }
        }

        $result['themes'][$theme] = [
            'themeName' => $theme,
            'openUrl' => HTTP_ROOT . "/themes/$theme/__editor__/app.html" . ($params ? '?' . implode('&', $params) : ''),
            'thumbnailUrl' => HTTP_ROOT . "/themes/$theme/assets/images/preview.png",
            'isActive' => $theme === THEME
        ];
    }

    return $result;
}

if (!getenv('NO_DISPATCH')) {
    dispatch();
}

//