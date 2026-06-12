<?php
/**
 * includes/db_backup.php — เครื่องมือ backup / restore ฐานข้อมูล MySQL ด้วย PHP
 *
 *   • พยายามใช้ mysqldump/mysql ผ่าน exec ก่อน (เร็ว เหมาะกับตารางใหญ่ 6M+ แถว)
 *   • ถ้า host ปิด exec → fallback เป็น PHP ล้วน (stream ผ่าน gzopen ทีละก้อน ไม่กิน RAM)
 *   • เขียนเป็น .sql.gz, ใช้ temp my.cnf (สิทธิ์ 600) → รหัสผ่านไม่โผล่ใน process list
 *   • บีบอัด/คลายด้วย zlib ของ PHP เอง (ไม่พึ่ง gzip binary) → ใช้ได้ทั้ง Linux/Windows
 *
 * ฟังก์ชันหลัก:
 *   dbk_backup(array $tables=[])  → ['ok'=>bool,'file'=>..,'bytes'=>..,'rows'?,'method'=>'exec'|'php','error'?]
 *   dbk_restore(string $file)     → ['ok'=>bool,'method'=>..,'statements'?,'error'?]
 *   dbk_list()                    → [['name','bytes','mtime'], ...] (ใหม่ก่อน)
 *   dbk_safe_path(string $name)   → path เต็มในโฟลเดอร์ backup (กัน path traversal) | null
 *
 * ใช้ผ่าน CLI ได้ (เลี่ยง timeout ของเว็บกับตารางใหญ่):
 *   php includes/db_backup.php backup                 # ทั้งฐานข้อมูล
 *   php includes/db_backup.php backup evaluations     # เฉพาะตาราง evaluations (6M+)
 *   php includes/db_backup.php restore backups/backup_xxx.sql.gz
 *   php includes/db_backup.php list
 */
require_once __DIR__ . '/db.php';   // โหลด config (DB_*) + db()

/* ---------- โฟลเดอร์เก็บ backup (ค่าเริ่มต้น <app>/backups, กันเข้าถึงผ่านเว็บ) ---------- */
function dbk_dir(): string
{
    $dir = (defined('BACKUP_DIR') && BACKUP_DIR) ? BACKUP_DIR : __DIR__ . '/../backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    // กัน dump ข้อมูลนักเรียนถูกโหลดผ่านเว็บ (เผื่อโฟลเดอร์อยู่ใน web root)
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    if (!file_exists($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return rtrim(str_replace('\\', '/', $dir), '/');
}

/* ---------- exec/proc_open เปิดใช้ได้ไหม ---------- */
function dbk_exec_enabled(): bool
{
    static $en = null;
    if ($en !== null) {
        return $en;
    }
    if (!function_exists('proc_open') || !function_exists('shell_exec')) {
        return $en = false;
    }
    $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    foreach (['proc_open', 'shell_exec'] as $f) {
        if (in_array($f, $disabled, true)) {
            return $en = false;
        }
    }
    return $en = true;
}

/* ---------- หา binary (mysqldump / mysql) ---------- */
function dbk_bin(string $name): ?string
{
    static $cache = [];
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }
    $constName = strtoupper($name) === 'MYSQLDUMP' ? 'MYSQLDUMP_BIN' : 'MYSQL_BIN';
    if (defined($constName) && constant($constName) && @is_executable(constant($constName))) {
        return $cache[$name] = constant($constName);
    }
    $isWin = stripos(PHP_OS, 'WIN') === 0;
    if (dbk_exec_enabled()) {
        $probe = $isWin ? "where $name 2>NUL" : "command -v $name 2>/dev/null";
        $out   = @shell_exec($probe);
        if ($out) {
            $line = trim(strtok($out, "\n"));
            if ($line !== '' && (@is_executable($line) || $isWin)) {
                return $cache[$name] = $line;
            }
        }
    }
    $candidates = $isWin
        ? ['D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/' . $name . '.exe']
        : ["/usr/bin/$name", "/usr/local/bin/$name", "/usr/local/mysql/bin/$name", "/opt/cpanel/ea-mysql80/root/usr/bin/$name"];
    foreach ($candidates as $c) {
        if (@is_executable($c)) {
            return $cache[$name] = $c;
        }
    }
    return $cache[$name] = null;
}

/* ---------- temp my.cnf (รหัสผ่านไม่ผ่าน argv) ---------- */
function dbk_write_cnf(): string
{
    $cnf = tempnam(sys_get_temp_dir(), 'hbk');
    @chmod($cnf, 0600);
    $esc = fn(string $s): string => '"' . addcslashes($s, "\"\\") . '"';
    file_put_contents(
        $cnf,
        "[client]\nhost=" . DB_HOST . "\nuser=" . $esc(DB_USER) . "\npassword=" . $esc(DB_PASS)
        . "\ndefault-character-set=" . DB_CHARSET . "\n"
    );
    return $cnf;
}

/* ---------- ไฟล์เป็น gzip ไหม (อ่าน magic bytes — ใช้ได้กับไฟล์อัปโหลดที่ไม่มีนามสกุล) ---------- */
function dbk_is_gz(string $file): bool
{
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return false;
    }
    $magic = fread($fh, 2);
    fclose($fh);
    return $magic === "\x1f\x8b";
}

/* ======================================================================
 *  BACKUP
 * ==================================================================== */
function dbk_backup(array $tables = []): array
{
    @set_time_limit(0);
    $dir    = dbk_dir();
    $stamp  = date('Ymd_His');
    $suffix = $tables ? '_' . preg_replace('/[^A-Za-z0-9]+/', '-', implode('-', $tables)) : '';
    $file   = $dir . '/backup_' . DB_NAME . $suffix . '_' . $stamp . '.sql.gz';

    if (dbk_exec_enabled() && dbk_bin('mysqldump')) {
        return dbk_backup_exec($file, $tables);
    }
    return dbk_backup_php($file, $tables);
}

/** ทางหลัก: mysqldump → อ่าน stdout → gzwrite เอง (ไม่พึ่ง gzip binary) */
function dbk_backup_exec(string $file, array $tables): array
{
    $dump = dbk_bin('mysqldump');
    $cnf  = dbk_write_cnf();
    $tmp  = $file . '.partial';
    try {
        $opts = ['--single-transaction', '--quick', '--no-tablespaces', '--add-drop-table',
                 '--default-character-set=' . DB_CHARSET];
        if (!$tables) {                       // ทั้งฐานข้อมูล → เก็บ routines/triggers/events ด้วย
            array_push($opts, '--routines', '--triggers', '--events');
        }
        $help = (string)@shell_exec(escapeshellarg($dump) . ' --help 2>&1');
        if (strpos($help, '--set-gtid-purged') !== false) {
            $opts[] = '--set-gtid-purged=OFF';
        }
        if (strpos($help, '--column-statistics') !== false) {
            $opts[] = '--column-statistics=0';
        }

        $cmd = escapeshellarg($dump) . ' --defaults-extra-file=' . escapeshellarg($cnf)
             . ' ' . implode(' ', array_map('escapeshellarg', $opts))
             . ' ' . escapeshellarg(DB_NAME);
        foreach ($tables as $t) {
            $cmd .= ' ' . escapeshellarg($t);
        }

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return ['ok' => false, 'error' => 'proc_open ล้มเหลว'];
        }
        $gz = gzopen($tmp, 'wb6');
        if (!$gz) {
            proc_close($proc);
            return ['ok' => false, 'error' => 'สร้างไฟล์ปลายทางไม่ได้: ' . $tmp];
        }
        stream_set_blocking($pipes[2], false);
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 1 << 20);
            if ($chunk === '' && feof($pipes[1])) {
                break;
            }
            if ($chunk !== false && $chunk !== '') {
                gzwrite($gz, $chunk);
            }
        }
        gzclose($gz);
        fclose($pipes[1]);
        $err  = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0) {
            @unlink($tmp);
            return ['ok' => false, 'error' => trim($err) ?: ('mysqldump exit ' . $code)];
        }
        rename($tmp, $file);
        return ['ok' => true, 'file' => $file, 'bytes' => filesize($file), 'method' => 'exec'];
    } finally {
        @unlink($cnf);
    }
}

/** Fallback: PHP ล้วน — stream แต่ละตารางด้วย unbuffered query เขียนลง gz ทีละก้อน */
function dbk_backup_php(string $file, array $tables): array
{
    @set_time_limit(0);
    $pdo = db();
    if (!$tables) {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }
    $tmp = $file . '.partial';
    $gz  = gzopen($tmp, 'wb6');
    if (!$gz) {
        return ['ok' => false, 'error' => 'สร้างไฟล์ปลายทางไม่ได้: ' . $tmp];
    }

    // connection แยกแบบ unbuffered สำหรับสตรีมแถว (ไม่ดึงทั้งตารางเข้า RAM)
    $stream = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
    );

    try {
        gzwrite($gz, "-- HIT-TEST PHP backup ของ `" . DB_NAME . "` @ " . date('c') . "\n"
                   . "SET NAMES " . DB_CHARSET . ";\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $total = 0;
        foreach ($tables as $t) {
            $tq      = '`' . str_replace('`', '``', $t) . '`';
            $create  = $pdo->query("SHOW CREATE TABLE $tq")->fetch(PDO::FETCH_NUM);
            gzwrite($gz, "DROP TABLE IF EXISTS $tq;\n" . $create[1] . ";\n\n");

            $rows  = $stream->query("SELECT * FROM $tq");
            $buf   = '';
            $batch = 0;
            $cols  = null;
            foreach ($rows as $row) {
                if ($cols === null) {
                    $cols = '`' . implode('`,`', array_keys($row)) . '`';
                }
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $buf .= ($batch === 0 ? "INSERT INTO $tq ($cols) VALUES " : ',')
                      . '(' . implode(',', $vals) . ')';
                $batch++;
                $total++;
                if ($batch >= 500 || strlen($buf) > (2 << 20)) {
                    gzwrite($gz, $buf . ";\n");
                    $buf   = '';
                    $batch = 0;
                }
            }
            if ($batch > 0) {
                gzwrite($gz, $buf . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        rename($tmp, $file);
        return ['ok' => true, 'file' => $file, 'bytes' => filesize($file), 'rows' => $total, 'method' => 'php'];
    } catch (Throwable $e) {
        @gzclose($gz);
        @unlink($tmp);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ======================================================================
 *  RESTORE  (⚠️ ไฟล์มี DROP TABLE — ควรกู้ลง DB ทดสอบก่อน)
 * ==================================================================== */
function dbk_restore(string $file): array
{
    @set_time_limit(0);
    if (!is_file($file)) {
        return ['ok' => false, 'error' => 'ไม่พบไฟล์'];
    }
    if (dbk_exec_enabled() && dbk_bin('mysql')) {
        return dbk_restore_exec($file);
    }
    return dbk_restore_php($file);
}

/** ทางหลัก: feed ไฟล์ (คลาย gz ใน PHP) เข้า stdin ของ mysql */
function dbk_restore_exec(string $file): array
{
    $mysql = dbk_bin('mysql');
    $cnf   = dbk_write_cnf();
    $isGz  = dbk_is_gz($file);
    try {
        $cmd  = escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf)
              . ' --default-character-set=' . DB_CHARSET . ' ' . escapeshellarg(DB_NAME);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return ['ok' => false, 'error' => 'proc_open ล้มเหลว'];
        }
        $in = $isGz ? gzopen($file, 'rb') : fopen($file, 'rb');
        while ($in && !($isGz ? gzeof($in) : feof($in))) {
            $chunk = $isGz ? gzread($in, 1 << 20) : fread($in, 1 << 20);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            fwrite($pipes[0], $chunk);
        }
        if ($in) {
            $isGz ? gzclose($in) : fclose($in);
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $err  = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return $code === 0
            ? ['ok' => true, 'method' => 'exec']
            : ['ok' => false, 'error' => trim($err) ?: ('mysql exit ' . $code)];
    } finally {
        @unlink($cnf);
    }
}

/** Fallback: อ่านทีละบรรทัด รวมเป็นคำสั่งที่จบด้วย ; แล้ว exec (best-effort กับ dump มาตรฐาน) */
function dbk_restore_php(string $file): array
{
    $pdo  = db();
    $isGz = dbk_is_gz($file);
    $in   = $isGz ? gzopen($file, 'rb') : fopen($file, 'rb');
    if (!$in) {
        return ['ok' => false, 'error' => 'เปิดไฟล์ไม่ได้'];
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $buf   = '';
    $count = 0;
    try {
        while (($line = ($isGz ? gzgets($in) : fgets($in))) !== false) {
            $trim = ltrim($line);
            if ($buf === '' && ($trim === '' || $trim[0] === '#'
                || str_starts_with($trim, '--') || str_starts_with($trim, '/*'))) {
                continue;   // ข้ามคอมเมนต์/บรรทัดว่างเมื่อยังไม่อยู่กลางคำสั่ง
            }
            $buf .= $line;
            if (preg_match('/;\s*$/', rtrim($line, "\r\n"))) {
                $pdo->exec($buf);
                $count++;
                $buf = '';
            }
        }
        if (trim($buf) !== '') {
            $pdo->exec($buf);
            $count++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        return ['ok' => true, 'method' => 'php', 'statements' => $count];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'at_statement' => $count + 1, 'method' => 'php'];
    } finally {
        $isGz ? gzclose($in) : fclose($in);
    }
}

/* ======================================================================
 *  รายการไฟล์ + utility
 * ==================================================================== */
function dbk_list(): array
{
    $dir = dbk_dir();
    $out = [];
    foreach (glob($dir . '/*.sql*') ?: [] as $f) {
        if (is_file($f) && substr($f, -8) !== '.partial') {
            $out[] = ['name' => basename($f), 'bytes' => filesize($f), 'mtime' => filemtime($f)];
        }
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

/** คืน path เต็มของไฟล์ใน backup dir อย่างปลอดภัย (กัน ../ traversal) | null ถ้าชื่อผิด/ไม่มีไฟล์ */
function dbk_safe_path(string $name): ?string
{
    $name = basename($name);
    if (!preg_match('/^[A-Za-z0-9._-]+\.sql(\.gz)?$/', $name)) {
        return null;
    }
    $path = dbk_dir() . '/' . $name;
    return is_file($path) ? $path : null;
}

function dbk_human(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $n = (float)$bytes;
    while ($n >= 1024 && $i < count($u) - 1) {
        $n /= 1024;
        $i++;
    }
    return ($i === 0 ? $n : number_format($n, $n >= 100 ? 0 : 1)) . ' ' . $u[$i];
}

/* ======================================================================
 *  CLI (เลี่ยง timeout ของเว็บกับตารางใหญ่):  php includes/db_backup.php <cmd>
 * ==================================================================== */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $cmd = $argv[1] ?? 'help';
    $say = function (array $r): void {
        echo ($r['ok'] ? 'OK ' : 'ERROR ') . json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit($r['ok'] ? 0 : 1);
    };
    if ($cmd === 'backup') {
        $say(dbk_backup(array_slice($argv, 2)));
    } elseif ($cmd === 'restore') {
        if (empty($argv[2])) {
            fwrite(STDERR, "ใช้: php includes/db_backup.php restore <file.sql[.gz]>\n");
            exit(2);
        }
        $say(dbk_restore($argv[2]));
    } elseif ($cmd === 'list') {
        foreach (dbk_list() as $b) {
            printf("%-55s %10s  %s\n", $b['name'], dbk_human($b['bytes']), date('Y-m-d H:i', $b['mtime']));
        }
    } else {
        echo "ใช้:\n  php includes/db_backup.php backup [table ...]\n"
           . "  php includes/db_backup.php restore <file.sql[.gz]>\n"
           . "  php includes/db_backup.php list\n";
    }
}
