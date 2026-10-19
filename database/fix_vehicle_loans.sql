-- =====================================================================
-- FIX vehicle_loans (kompatibel MySQL 5.7 / MariaDB — tanpa DELIMITER)
-- Menambah kolom yang hilang pada tabel versi lama agar cocok dengan
-- query aplikasi peminjaman kendaraan. Idempotent, tidak menghapus data.
--
-- CARA PAKAI phpMyAdmin: buka tab SQL database pengadaan (ljucoid_pbj),
-- jalankan file ini per-perintah (phpMyAdmin otomatis memisahkan
-- baris per titik-koma), atau via SSH:
--   mysql -u ljucoid_pbj -p ljucoid_pbj < fix_vehicle_loans.sql
--
-- ERROR "1091 Can't DROP COLUMN" = AMAN DIABAIKAN (kolom sudah ada).
-- Versi prosedur idempotent (jalankan via SSH/terminal yang mendukung DELIMITER):
--   database/fix_vehicle_loans_procedure.sql
-- =====================================================================

ALTER TABLE vehicle_loans
  ADD COLUMN nik             VARCHAR(50)  NULL AFTER user_id,
  ADD COLUMN driver          VARCHAR(120) NULL,
  ADD COLUMN passengers      TEXT         NULL,
  ADD COLUMN start_km        INT          NULL,
  ADD COLUMN end_km          INT          NULL,
  ADD COLUMN fuel_cost       DECIMAL(15,2) DEFAULT 0,
  ADD COLUMN proof_file      VARCHAR(255) NULL,
  ADD COLUMN return_condition TEXT        NULL,
  ADD COLUMN return_note     TEXT         NULL,
  ADD COLUMN approved_by     INT          NULL,
  ADD COLUMN approve_note    TEXT         NULL,
  ADD COLUMN rejected_at     DATETIME     NULL,
  ADD COLUMN handover_out_id INT          NULL,
  ADD COLUMN handover_in_id  INT          NULL,
  ADD COLUMN returned_at     DATETIME     NULL,
  ADD COLUMN confirmed_by    INT          NULL,
  ADD COLUMN updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Perluas ENUM status + default MENUNGGU
ALTER TABLE vehicle_loans
  MODIFY COLUMN status ENUM('MENUNGGU','DISETUJUI','DITOLAK','DISERAHKAN','DIGUNAKAN','DIKEMBALIKAN','SELESAI')
  NOT NULL DEFAULT 'MENUNGGU';

-- Ubah DATE -> DATETIME (tanggal+jam pemakaian) — data lama dipertahankan
ALTER TABLE vehicle_loans
  MODIFY COLUMN start_date DATETIME NULL,
  MODIFY COLUMN end_date   DATETIME NULL;
