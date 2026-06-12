#!/usr/bin/env bash
# ============================================================================
# scripts/backup_evaluations.sh
#   สำรองตาราง `evaluations` (6 ล้าน+ แถว) บน server แบบปลอดภัยกับ production:
#     • --single-transaction : สแนปช็อตคงเส้นคงวา ไม่ล็อกตาราง (InnoDB) → app ใช้ต่อได้
#     • --quick              : สตรีมทีละแถว ไม่ buffer 6M แถวลง RAM
#     • | gzip               : บีบอัดระหว่างเขียน (ตารางนี้เป็น int ล้วน บีบได้เยอะ)
#     • รหัสผ่านไม่โผล่ใน `ps` (ใช้ temp my.cnf สิทธิ์ 600 ลบทิ้งอัตโนมัติ)
#     • เขียนไฟล์ "นอก web root" กัน dump ข้อมูลนักเรียนถูกโหลดผ่านเว็บ
#     • portable: ตรวจ flag ของ mysqldump เอง (ใช้ได้ทั้ง MySQL และ MariaDB)
#
# วิธีใช้ (บน server ผ่าน SSH):
#   chmod +x scripts/backup_evaluations.sh
#   ./scripts/backup_evaluations.sh                 # อ่าน DB creds จาก config/config.php เอง
#
# override ได้ด้วย env เช่น:
#   BACKUP_DIR=/path/safe RETENTION_DAYS=30 ./scripts/backup_evaluations.sh
#   DB_HOST=127.0.0.1 DB_NAME=ssrainfo_hittest DB_USER=... DB_PASS=... ./scripts/backup_evaluations.sh
#
# ตั้ง cron (ทุกวันตี 2:15, เก็บ log):
#   15 2 * * * /home/USER/public_html/scripts/backup_evaluations.sh >> $HOME/backups/hittest/backup.log 2>&1
#
# กู้คืน (ระวัง: ไฟล์มี DROP TABLE → กู้ลง DB ทดสอบก่อน อย่ากู้ทับ production ตรง ๆ):
#   gunzip -c evaluations_xxx.sql.gz | mysql --defaults-extra-file=~/.my.cnf ssrainfo_hittest_restore
#
# รันบน Windows/Laragon (ถ้า server คือเครื่องนี้): ใช้ Git Bash แล้วตั้ง
#   MYSQLDUMP_BIN="D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe" MYSQL_BIN=".../mysql.exe"
# ============================================================================
set -euo pipefail
export LC_ALL=C

# ---------- ตั้งค่า (override ผ่าน environment ได้ทุกตัว) ----------
APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PHP="${CONFIG_PHP:-$APP_ROOT/config/config.php}"
TABLE="${TABLE:-evaluations}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/hittest}"   # นอก public_html — ห้ามวางใน web root
RETENTION_DAYS="${RETENTION_DAYS:-14}"              # ลบ backup เก่ากว่ากี่วัน (0 = ไม่ลบ)
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"
# เว้น DB_* ว่างไว้ = อ่านจาก config.php อัตโนมัติ (แหล่งเดียว ไม่ก็อปรหัสผ่านมาไว้ในสคริปต์)
DB_HOST="${DB_HOST:-}"; DB_NAME="${DB_NAME:-}"; DB_USER="${DB_USER:-}"; DB_PASS="${DB_PASS:-}"

log(){ printf '%s  %s\n' "$(date '+%F %T')" "$*"; }
die(){ printf '%s  ERROR: %s\n' "$(date '+%F %T')" "$*" >&2; exit 1; }

# ---------- อ่าน DB creds จาก config.php ถ้าไม่ได้ตั้งผ่าน env ----------
if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
  command -v php >/dev/null 2>&1 || die "ไม่พบ php CLI — ตั้ง DB_HOST/DB_NAME/DB_USER/DB_PASS ผ่าน env แทน"
  [[ -f "$CONFIG_PHP" ]] || die "ไม่พบ config: $CONFIG_PHP (ตั้ง CONFIG_PHP=/path/to/config.php)"
  eval "$(php -r '
    require $argv[1];
    printf("DB_HOST=%s\nDB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n",
      escapeshellarg(defined("DB_HOST")?DB_HOST:"127.0.0.1"),
      escapeshellarg(DB_NAME), escapeshellarg(DB_USER), escapeshellarg(DB_PASS));
  ' "$CONFIG_PHP")"
fi
DB_HOST="${DB_HOST:-127.0.0.1}"
[[ -n "$DB_NAME" && -n "$DB_USER" ]] || die "DB creds ไม่ครบ"

command -v "$MYSQLDUMP_BIN" >/dev/null 2>&1 || die "ไม่พบ $MYSQLDUMP_BIN — ตั้ง MYSQLDUMP_BIN=/path/mysqldump"
command -v "$MYSQL_BIN"     >/dev/null 2>&1 || die "ไม่พบ $MYSQL_BIN — ตั้ง MYSQL_BIN=/path/mysql"

# ---------- temp my.cnf (สิทธิ์ 600) เพื่อไม่ให้รหัสผ่านโผล่ใน process list ----------
CNF="$(mktemp "${TMPDIR:-/tmp}/hittest_bk.XXXXXX")"
TMP=""
trap 'rm -f "$CNF"; [[ -n "$TMP" && -f "$TMP" ]] && rm -f "$TMP"' EXIT
chmod 600 "$CNF"
cat >"$CNF" <<EOF
[client]
host=$DB_HOST
user=$DB_USER
password=$DB_PASS
default-character-set=utf8mb4
EOF

# ---------- pre-flight: ต่อ DB ได้ไหม + มีกี่แถว ----------
ROWS="$("$MYSQL_BIN" --defaults-extra-file="$CNF" -N -B \
        -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$TABLE\`;" 2>/dev/null)" \
  || die "เชื่อมต่อ DB หรืออ่านตาราง $DB_NAME.$TABLE ไม่ได้ (ตรวจ creds/ชื่อตาราง)"
log "ตาราง $DB_NAME.$TABLE : $ROWS แถว"

# ---------- ประกอบ options ของ mysqldump (เติม flag เฉพาะที่ build นี้รองรับ) ----------
OPTS=(--single-transaction --quick --no-tablespaces --add-drop-table
      --default-character-set=utf8mb4 --max-allowed-packet=256M)
if "$MYSQLDUMP_BIN" --help 2>/dev/null | grep -q -- '--set-gtid-purged'; then
  OPTS+=(--set-gtid-purged=OFF)          # MySQL: กัน statement GTID ที่ทำ restore พัง/ต้องสิทธิ์สูง
fi
if "$MYSQLDUMP_BIN" --help 2>/dev/null | grep -q -- '--column-statistics'; then
  OPTS+=(--column-statistics=0)          # MySQL 8 client: กัน error ตอน dump ข้าม server เวอร์ชันเก่า
fi

# ---------- dump → gzip (เขียนเป็น .partial ก่อน แล้วค่อย mv เมื่อสำเร็จ) ----------
mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
TS="$(date '+%Y%m%d_%H%M%S')"
OUT="$BACKUP_DIR/${TABLE}_${DB_NAME}_${TS}.sql.gz"
TMP="$OUT.partial"
log "เริ่ม dump → $OUT"
"$MYSQLDUMP_BIN" --defaults-extra-file="$CNF" "${OPTS[@]}" "$DB_NAME" "$TABLE" | gzip -c > "$TMP"
# pipefail (จาก set -o) ทำให้ถ้า mysqldump ล้ม สคริปต์หยุดทันที (ไฟล์ .partial จะถูกลบโดย trap)

gzip -t "$TMP" || die "ไฟล์ gzip เสีย — ยกเลิก"
mv "$TMP" "$OUT"; TMP=""
log "สำเร็จ: $OUT ($(du -h "$OUT" | cut -f1) จาก $ROWS แถว)"

# ---------- ลบ backup เก่าตาม RETENTION_DAYS ----------
if [[ "${RETENTION_DAYS}" -gt 0 ]]; then
  find "$BACKUP_DIR" -maxdepth 1 -type f -name "${TABLE}_*.sql.gz" -mtime +"$RETENTION_DAYS" \
       -printf 'ลบ backup เก่า: %p\n' -delete 2>/dev/null || true
fi
log "เสร็จสิ้น"
