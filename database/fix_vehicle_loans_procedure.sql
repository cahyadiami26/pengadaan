-- =====================================================================
-- FIX vehicle_loans: tambah kolom yang hilang + ubah DATE -> DATETIME
-- Aman dijalankan berulang (idempotent) & tidak menghapus data.
-- Cara pakai (phpMyAdmin): paste SELURUH file ini ke tab SQL lalu Go,
-- ATAU via SSH:  mysql -u USER -p DBNAME < fix_vehicle_loans.sql
-- =====================================================================

DROP PROCEDURE IF EXISTS fix_vehicle_loans;

DELIMITER $$

CREATE PROCEDURE fix_vehicle_loans()
BEGIN
  DECLARE dbname VARCHAR(64);
  SET dbname = DATABASE();

  -- ---------- Tambah kolom yang mungkin belum ada ----------
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'nik') THEN
    ALTER TABLE vehicle_loans ADD COLUMN nik VARCHAR(50) NULL AFTER user_id;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'purpose') THEN
    ALTER TABLE vehicle_loans ADD COLUMN purpose TEXT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'driver') THEN
    ALTER TABLE vehicle_loans ADD COLUMN driver VARCHAR(100) NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'passengers') THEN
    ALTER TABLE vehicle_loans ADD COLUMN passengers TEXT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'start_datetime') THEN
    ALTER TABLE vehicle_loans ADD COLUMN start_datetime DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'end_datetime') THEN
    ALTER TABLE vehicle_loans ADD COLUMN end_datetime DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'km_start') THEN
    ALTER TABLE vehicle_loans ADD COLUMN km_start INT UNSIGNED NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'approved_by') THEN
    ALTER TABLE vehicle_loans ADD COLUMN approved_by INT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'approved_at') THEN
    ALTER TABLE vehicle_loans ADD COLUMN approved_at DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'approve_note') THEN
    ALTER TABLE vehicle_loans ADD COLUMN approve_note VARCHAR(255) NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'rejected_at') THEN
    ALTER TABLE vehicle_loans ADD COLUMN rejected_at DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'handover_out_id') THEN
    ALTER TABLE vehicle_loans ADD COLUMN handover_out_id INT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'returned_at') THEN
    ALTER TABLE vehicle_loans ADD COLUMN returned_at DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'return_datetime') THEN
    ALTER TABLE vehicle_loans ADD COLUMN return_datetime DATETIME NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'km_end') THEN
    ALTER TABLE vehicle_loans ADD COLUMN km_end INT UNSIGNED NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'return_condition') THEN
    ALTER TABLE vehicle_loans ADD COLUMN return_condition ENUM('BAIK','RUSAK_RINGAN','RUSAK_BERAT') NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'return_notes') THEN
    ALTER TABLE vehicle_loans ADD COLUMN return_notes TEXT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'handover_in_id') THEN
    ALTER TABLE vehicle_loans ADD COLUMN handover_in_id INT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'confirmed_by') THEN
    ALTER TABLE vehicle_loans ADD COLUMN confirmed_by INT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans' AND COLUMN_NAME = 'updated_at') THEN
    ALTER TABLE vehicle_loans ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
  END IF;

  -- ---------- Perluas ENUM status jika masih versi lama ----------
  ALTER TABLE vehicle_loans
    MODIFY COLUMN status ENUM('MENUNGGU','DISETUJUI','DITOLAK','DISERAHKAN','DIGUNAKAN','DIKEMBALIKAN','SELESAI')
    NOT NULL DEFAULT 'MENUNGGU';

  -- ---------- Konversi DATE -> DATETIME (pertahankan data lama) ----------
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'vehicle_loans'
               AND COLUMN_NAME = 'start_date' AND DATA_TYPE = 'date') THEN
    ALTER TABLE vehicle_loans
      MODIFY COLUMN start_date DATETIME NULL,
      MODIFY COLUMN end_date   DATETIME NULL;
  END IF;

END$$

DELIMITER ;

CALL fix_vehicle_loans();
DROP PROCEDURE fix_vehicle_loans;

-- Bersihkan kolom duplikat hasil migrasi ganda (hanya jika tabel punya keduanya
-- dan datanya identik/kosong) — opsional, aman diabaikan bila ragu.
-- ALTER TABLE vehicle_loans DROP COLUMN start_datetime;
-- ALTER TABLE vehicle_loans DROP COLUMN end_datetime;
