<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$file = isset($_GET['file']) ? $_GET['file'] : '';
$post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;

// 基础安全校验
if (!$file || !$post_id || strpos($file, '..') !== false) {
    http_response_code(403);
    exit('非法请求');
}

// 检查用户是否登录
if (!is_logged_in()) {
    exit('请先登录后下载');
}

// 检查用户是否已评论
$db = Database::getInstance()->getConnection();
$comments = get_comments_by_post($post_id);
$user_commented = false;
foreach ($comments as $comment) {
    if ($comment['user_id'] == $_SESSION['user_id']) {
        $user_commented = true;
        break;
    }
    if (!empty($comment['replies'])) {
        foreach ($comment['replies'] as $reply) {
            if ($reply['user_id'] == $_SESSION['user_id']) {
                $user_commented = true;
                break 2;
            }
        }
    }
}
if (!$user_commented) {
    exit('请评论后再下载');
}

$real_path = __DIR__ . '/uploads/files/' . basename($file);
if (!file_exists($real_path)) {
    http_response_code(404);
    exit('文件不存在');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($real_path));
readfile($real_path);
exit; 