<?php
/**
 * includes/admin_auth.php — gate ของเครื่องมือ "ผู้ดูแลระบบ"
 *   (backup/restore ฐานข้อมูล, เลื่อนชั้นทั้งระบบ)
 * include บรรทัดแรกของหน้า/endpoint ระดับระบบ:
 *   1) ต้อง login เป็นครู/ผู้ดูแล (auth.php)
 *   2) SMIS ต้องอยู่ใน ADMIN_SMIS (require_admin)
 */
require __DIR__ . '/auth.php';
require_admin();
