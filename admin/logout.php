<?php
// 开启会话
session_start();

// 销毁会话
session_destroy();

// 重定向回登录页面
header('Location: login.php');
exit;
?>