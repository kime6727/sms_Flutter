<?php
/**
 * 调试端点 V2 - 专门探测 nginx 实际配置
 * 访问: /debug2.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== /etc/nginx 目录 ===\n";
$dir = '/etc/nginx';
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        echo "  " . $f->getPathname() . " (" . (is_file($f) ? filesize($f) : 'dir') . ")\n";
    }
} else {
    echo "  /etc/nginx 不存在\n";
}

echo "\n=== /assets 目录 ===\n";
$dir = '/assets';
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        echo "  " . $f->getPathname() . " (" . (is_file($f) ? filesize($f) : 'dir') . ")\n";
    }
} else {
    echo "  /assets 不存在\n";
}

echo "\n=== 找 nginx.conf 相关文件 ===\n";
$found = [];
exec('find / -name "nginx*.conf" -type f 2>/dev/null', $found);
foreach ($found as $f) {
    echo "  $f\n";
}

echo "\n=== /proc/1/cmdline ===\n";
$cmd = @file_get_contents('/proc/1/cmdline');
if ($cmd) {
    echo "  " . str_replace("\0", " | ", $cmd) . "\n";
} else {
    echo "  无法读取\n";
}

echo "\n=== nginx 进程列表 ===\n";
$procs = [];
exec('ps -ef 2>/dev/null | grep -E "nginx|php-fpm|prestart" | grep -v grep', $procs);
foreach ($procs as $p) echo "  $p\n";

echo "\n=== nginx open files (worker) ===\n";
$pids = [];
exec('pgrep nginx 2>/dev/null', $pids);
foreach ($pids as $pid) {
    echo "  PID $pid:\n";
    $files = [];
    @exec("ls -la /proc/$pid/cwd /proc/$pid/exe 2>/dev/null", $files);
    foreach ($files as $f) echo "    $f\n";
    $fds = @file_get_contents("/proc/$pid/cmdline");
    if ($fds) echo "    cmd: " . str_replace("\0", " ", $fds) . "\n";
}

echo "\n=== DONE ===\n";
