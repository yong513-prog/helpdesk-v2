<?php
/*
|--------------------------------------------------------------------------
| Entity Upload Folder Helper
|--------------------------------------------------------------------------
| Creates clean per-record folders for Helpdesk uploads:
| - uploads/tickets/{ticket_no}/
| - uploads/assets/{asset_code}/
| - uploads/knowledge_base/KB-{article_id}/
| - uploads/announcements/ANN-{announcement_id}/
*/

if(!function_exists('hd_safe_folder_name'))
{
    function hd_safe_folder_name($name, $fallback = 'item')
    {
        $name = trim((string)$name);
        if($name === '') $name = $fallback;
        $name = str_replace(['\\','/'], '-', $name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $name = trim($name, '._-');
        if($name === '') $name = $fallback;
        return substr($name, 0, 120);
    }
}

if(!function_exists('hd_entity_upload_dir'))
{
    function hd_entity_upload_dir($module, $entityName)
    {
        $module = hd_safe_folder_name($module, 'misc');
        $entityName = hd_safe_folder_name($entityName, 'item');
        $dir = __DIR__ . '/uploads/' . $module . '/' . $entityName . '/';
        if(!is_dir($dir)) mkdir($dir, 0777, true);

        $rootHt = __DIR__ . '/uploads/.htaccess';
        if(!file_exists($rootHt)) {
            @file_put_contents($rootHt, "Options -Indexes\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .phar\n");
        }

        $ht = dirname($dir) . '/.htaccess';
        if(!file_exists($ht)) {
            @file_put_contents($ht, "Options -Indexes\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .phar\n");
        }

        return $dir;
    }
}

if(!function_exists('hd_entity_upload_relative'))
{
    function hd_entity_upload_relative($module, $entityName, $filename)
    {
        return 'uploads/' . hd_safe_folder_name($module, 'misc') . '/' . hd_safe_folder_name($entityName, 'item') . '/' . ltrim((string)$filename, '/\\');
    }
}

if(!function_exists('hd_delete_empty_upload_folder'))
{
    function hd_delete_empty_upload_folder($module, $entityName)
    {
        $dir = __DIR__ . '/uploads/' . hd_safe_folder_name($module, 'misc') . '/' . hd_safe_folder_name($entityName, 'item') . '/';
        if(is_dir($dir)) {
            $items = array_values(array_diff(scandir($dir), ['.','..','.htaccess']));
            if(count($items) === 0) @rmdir($dir);
        }
    }
}
