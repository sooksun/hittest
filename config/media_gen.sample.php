<?php
/**
 * config/media_gen.sample.php — แม่แบบค่าตั้งระบบสร้างสื่อ (เสียง Botnoi + ภาพ ComfyUI)
 *
 * วิธีใช้: คัดลอกไฟล์นี้เป็น  config/media_gen.php  แล้วแก้ค่าให้ตรงกับ server จริง
 *   cp config/media_gen.sample.php config/media_gen.php
 * *** config/media_gen.php ถูก gitignore — ห้าม commit (มี host/คีย์ภายใน) ***
 *
 * หมายเหตุ: โทเคน Botnoi อยู่ที่ config/botnoi.php (BOTNOI_TOKEN/BOTNOI_SPEAKER/…) — ไฟล์นี้ไม่ซ้ำ
 */

// ── ComfyUI (เซิร์ฟเวอร์สร้างภาพ Stable Diffusion) ───────────────────────────────
// host ของ ComfyUI — ต้องเข้าถึงได้จากเครื่องที่รัน newhittest (เปิด /system_stats ได้)
const COMFYUI_API_URL   = 'http://203.172.184.47:8188';
// ชื่อ checkpoint (.safetensors) ที่มีบน ComfyUI — ดูรายการจริงได้ที่
//   GET {COMFYUI_API_URL}/object_info/CheckpointLoaderSimple
const COMFYUI_CHECKPOINT = 'dreamshaper_8.safetensors';
const COMFYUI_STEPS      = 20;        // จำนวน sampling steps (มาก = ชัดแต่ช้า)
const COMFYUI_CFG        = 7.0;       // classifier-free guidance (ยึดตาม prompt มากน้อย)
const COMFYUI_SAMPLER    = 'euler';
const COMFYUI_SCHEDULER  = 'normal';
const COMFYUI_WIDTH      = 1024;
const COMFYUI_HEIGHT     = 1024;
const COMFYUI_POLL_SEC   = 2;         // poll /history ทุกกี่วินาที
const COMFYUI_TIMEOUT_SEC = 300;      // เลิกรอภาพหลังกี่วินาที

// ── LLM (แปลงคำไทย → คำอธิบายภาพอังกฤษ ก่อนส่งให้ ComfyUI) ──────────────────────
// 'openai'  = OpenAI หรือ endpoint ที่เข้ากันได้ (รวม Open WebUI /api/chat/completions) — ต้องมีคีย์
// 'ollama'  = เรียก Ollama native /api/generate — ต้องเปิด endpoint (Open WebUI อยู่ใต้ /ollama + ต้องมีคีย์)
// 'none'    = ปิด LLM อัตโนมัติ → ใช้ word_overrides.json + คำที่แอดมินพิมพ์เองเท่านั้น
const LLM_PROVIDER     = 'none';

// OpenAI (หรือ OpenAI-compatible เช่น Open WebUI: ตั้ง OPENAI_BASE_URL='https://ai.cnppai.com/api')
const OPENAI_API_KEY   = '';                 // sk-... หรือคีย์ของ Open WebUI
const OPENAI_LLM_MODEL = 'gpt-4o-mini';      // ถ้าใช้ Open WebUI ให้ใส่ชื่อโมเดล เช่น 'gemma3:12b'
const OPENAI_BASE_URL  = 'https://api.openai.com/v1';

// Ollama native — ตั้งเป็น base (…/ollama) หรือ endpoint เต็ม (…/api/generate) ก็ได้ (โค้ดทนทั้งสองแบบ)
// *** อย่าใส่คีย์จริงในไฟล์ sample นี้ (ถูก commit) — ใส่ใน config/media_gen.php ที่ gitignored เท่านั้น ***
const OLLAMA_API_URL   = 'https://ollama.cnppai.com/api/generate';
const OLLAMA_MODEL     = 'gemma4:e4b';
const OLLAMA_API_KEY   = '';                 // Bearer (ถ้า endpoint ต้องใช้)
