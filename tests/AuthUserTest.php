<?php
/**
 * tests/AuthUserTest.php — บัญชีผู้ใช้ (ตาราง users, migration 011) + role ตามลำดับชั้น
 *   - user_authenticate(): รหัสถูก/ผิด, บัญชีปิด (is_active=0), ไม่พบ, ค่าว่าง
 *   - password เก็บเป็น hash (ไม่ใช่ plaintext) · username UNIQUE
 *   - is_superadmin / is_saoadmin / is_admin / current_area_code จาก $_SESSION (role-based)
 */

/** seed user 1 คน (password เป็น hash) */
function aut_seed(PDO $pdo, string $user, string $pass, string $role = 'school', array $o = []): void
{
    $pdo->prepare(
        'INSERT INTO users (username, password_hash, name, role, area_code, sc_id, is_active, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,NOW(),NOW())'
    )->execute([
        $user, password_hash($pass, PASSWORD_DEFAULT), $o['name'] ?? $user,
        $role, $o['area_code'] ?? null, $o['sc_id'] ?? null, $o['is_active'] ?? 1,
    ]);
}

run_test('AuthUser: user_authenticate ยอมรับเฉพาะรหัสที่ถูกต้อง', function () {
    $pdo = create_test_db();
    aut_seed($pdo, '57030000', 'p@5703', 'saoadmin', ['area_code' => '5703', 'name' => 'สพป.เชียงราย เขต 3']);

    $ok = user_authenticate('57030000', 'p@5703', $pdo);
    ok($ok && $ok['role'] === 'saoadmin' && $ok['area_code'] === '5703', 'รหัสถูก → คืนแถว saoadmin + area');
    ok(user_authenticate('57030000', 'wrong', $pdo) === null, 'รหัสผิด → null');
    ok(user_authenticate('nope', 'p@5703', $pdo) === null, 'ไม่พบ user → null');
    ok(user_authenticate('', '', $pdo) === null, 'ค่าว่าง → null');
});

run_test('AuthUser: บัญชีปิด (is_active=0) login ไม่ได้', function () {
    $pdo = create_test_db();
    aut_seed($pdo, 'off01', 'secret123', 'school', ['sc_id' => '57030129', 'is_active' => 0]);
    ok(user_authenticate('off01', 'secret123', $pdo) === null, 'is_active=0 → null แม้รหัสถูก');
});

run_test('AuthUser: password เก็บเป็น hash ไม่ใช่ plaintext', function () {
    $pdo = create_test_db();
    aut_seed($pdo, 'h01', 'secret123', 'superadmin');
    $row = $pdo->query("SELECT password_hash FROM users WHERE username='h01'")->fetch();
    ok($row['password_hash'] !== 'secret123', 'ไม่เก็บ plaintext');
    ok(password_verify('secret123', (string)$row['password_hash']), 'password_verify ผ่าน');
});

run_test('AuthUser: username ซ้ำไม่ได้ (UNIQUE)', function () {
    $pdo = create_test_db();
    aut_seed($pdo, 'dup', 'secret123');
    $threw = false;
    try {
        aut_seed($pdo, 'dup', 'other123');
    } catch (PDOException $e) {
        $threw = ((int)$e->errorInfo[1] === 1062);
    }
    ok($threw, 'username ซ้ำ → ER_DUP_ENTRY (1062)');
});

run_test('AuthUser: role จาก session คุม is_superadmin/is_saoadmin/is_admin', function () {
    $_SESSION = ['sc_smis' => ''];                 // ไม่มี SMIS → is_admin อิง role อย่างเดียว

    $_SESSION['user_role'] = 'superadmin';
    ok(is_superadmin() === true,  'superadmin → is_superadmin');
    ok(is_admin() === true,       'superadmin → is_admin (เข้าเครื่องมือระบบ)');
    ok(is_saoadmin() === false,   'superadmin → ไม่ใช่ saoadmin');

    $_SESSION['user_role'] = 'saoadmin';
    $_SESSION['area_code'] = '5703';
    ok(is_saoadmin() === true,    'saoadmin → is_saoadmin');
    ok(is_admin() === false,      'saoadmin → ไม่ใช่ admin (ระบบ)');
    ok(current_area_code() === '5703', 'current_area_code = 5703');

    $_SESSION['user_role'] = 'school';
    ok(is_admin() === false && is_saoadmin() === false && is_superadmin() === false, 'school → ไม่ใช่ทั้งสาม');
    ok(current_user_role() === 'school', 'current_user_role = school');

    $_SESSION = [];                                // cleanup
});
