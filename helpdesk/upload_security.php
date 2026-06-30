<?php
/*
|--------------------------------------------------------------------------
| Upload Security Helper
|--------------------------------------------------------------------------
| Central helper for upload directory hardening and safe filename handling.
*/

if(!function_exists('hd_secure_upload_dir'))
{
    function hd_secure_upload_dir(string $relativeDir): string
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $absoluteDir = __DIR__ . '/' . $relativeDir;

        if(!is_dir($absoluteDir))
        {
            mkdir($absoluteDir, 0775, true);
        }

        $htaccess = $absoluteDir . '/.htaccess';
        if(!file_exists($htaccess))
        {
            file_put_contents($htaccess, "Options -Indexes\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .asp .aspx .jsp\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|asp|aspx|jsp|sh|bat|cmd|exe|com|js|html|htm)$\">\nRequire all denied\n</FilesMatch>\n");
        }

        $index = $absoluteDir . '/index.html';
        if(!file_exists($index))
        {
            file_put_contents($index, '');
        }

        return $absoluteDir;
    }
}

if(!function_exists('hd_safe_uploaded_filename'))
{
    function hd_safe_uploaded_filename(string $originalName): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $base = trim($base, '._-');
        if($base === '') $base = 'file';
        return date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '_' . $base . ($ext ? '.'.$ext : '');
    }
}

if(!function_exists('hd_validate_upload'))
{
    function hd_validate_upload(array $file, array $allowedExt, int $maxSize = 10485760): void
    {
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
        {
            throw new Exception('Upload failed. Please try again.');
        }

        if(($file['size'] ?? 0) > $maxSize)
        {
            throw new Exception('File size cannot exceed '.round($maxSize/1024/1024).'MB.');
        }

        $original = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = array_map('strtolower', $allowedExt);

        $dangerous = ['php','phtml','php3','php4','php5','php7','php8','phar','cgi','pl','asp','aspx','jsp','sh','bat','cmd','exe','com','js','html','htm'];
        if($ext === '' || in_array($ext, $dangerous, true) || !in_array($ext, $allowedExt, true))
        {
            throw new Exception('File type not allowed.');
        }
    }
}

if(!function_exists('hd_save_upload'))
{
    function hd_save_upload(array $file, string $relativeDir, array $allowedExt, int $maxSize = 10485760): array
    {
        hd_validate_upload($file, $allowedExt, $maxSize);
        $absoluteDir = hd_secure_upload_dir($relativeDir);
        $newName = hd_safe_uploaded_filename((string)$file['name']);
        $target = $absoluteDir . '/' . $newName;
        if(!move_uploaded_file($file['tmp_name'], $target))
        {
            throw new Exception('Cannot save uploaded file. Please check folder permission.');
        }
        return [
            'path' => trim($relativeDir, '/') . '/' . $newName,
            'original_name' => (string)$file['name'],
            'size' => (int)$file['size']
        ];
    }
}
?>
