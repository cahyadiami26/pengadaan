-- =====================================================================
-- Migrasi: Modul Peminjaman Kendaraan (idempotent, aman dijalankan berulang)
-- Jalankan pada database aplikasi (pengadaan_lju):
--   mysql -u USER -p pengadaan_lju < database/migrate_vehicle_loans.sql
-- Atau lewat phpMyAdmin: import file ini.
-- =====================================================================

-- 1) Pastikan tabel peminjaman & log pemakaian ada (untuk install lama)
CREATE TABLE IF NOT EXISTS vehicle_loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_no VARCHAR(40) NOT NULL UNIQUE,
  vehicle_id INT NOT NULL,
  user_id INT NOT NULL,
  purpose TEXT, driver VARCHAR(120), passengers TEXT,
  start_date DATETIME, end_date DATETIME,
  start_km INT, end_km INT, fuel_cost DECIMAL(15,2) DEFAULT 0,
  proof_file VARCHAR(255),
  return_condition TEXT, return_note TEXT,
  status ENUM('MENUNGGU','DISETUJUI','DIGUNAKAN','DIKEMBALIKAN','DITOLAK') DEFAULT 'MENUNGGU',
  approved_by INT NULL, approve_note TEXT, rejected_at DATETIME NULL,
  handover_out_id INT NULL, handover_in_id INT NULL,
  returned_at DATETIME NULL, confirmed_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  loan_id INT NULL,
  user_id INT NULL,
  destination VARCHAR(200),
  borrow_date DATETIME,
  return_date DATETIME NULL,
  start_km INT, end_km INT,
  status ENUM('DIGUNAKAN','SELESAI') DEFAULT 'DIGUNAKAN',
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2) Tambah kolom yang hilang secara dinamis (MySQL/MariaDB tidak punya ADD COLUMN IF NOT EXISTS)
DROP PROCEDURE IF EXISTS fix_vehicle_loans;
DELIMITER $$
CREATE PROCEDURE fix_vehicle_loans()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE col_name VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT c.t FROM (
      SELECT 'driver' t UNION SELECT 'passengers' UNION SELECT 'fuel_cost' UNION SELECT 'proof_file'
      UNION SELECT 'return_condition' UNION SELECT 'return_note' UNION SELECT 'approve_note'
      UNION SELECT 'rejected_at' UNION SELECT 'handover_out_id' UNION SELECT 'handover_in_id'
      UNION SELECT 'returned_at' UNION SELECT 'confirmed_by' UNION SELECT 'updated_at'
    ) c
    LEFT JOIN information_schema.columns ic
      ON ic.table_schema = DATABASE() AND ic.table_name = 'vehicle_loans' AND ic.column_name = c.t
    WHERE ic.column_name IS NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  add_loop: LOOP
    FETCH cur INTO col_name;
    IF done = 1 THEN LEAVE add_loop; END IF;
    SET @ddl = CONCAT('ALTER TABLE vehicle_loans ADD COLUMN ', col_name, ' ',
      CASE col_name
        WHEN 'driver'            THEN 'VARCHAR(120) NULL AFTER purpose'
        WHEN 'passengers'        THEN 'TEXT NULL AFTER driver'
        WHEN 'fuel_cost'         THEN 'DECIMAL(15,2) DEFAULT 0 AFTER end_km'
        WHEN 'proof_file'        THEN 'VARCHAR(255) NULL AFTER fuel_cost'
        WHEN 'return_condition'  THEN 'TEXT NULL AFTER proof_file'
        WHEN 'return_note'       THEN 'TEXT NULL AFTER return_condition'
        WHEN 'approve_note'      THEN 'TEXT NULL AFTER approved_by'
        WHEN 'rejected_at'       THEN 'DATETIME NULL AFTER approve_note'
        WHEN 'handover_out_id'   THEN 'INT NULL AFTER rejected_at'
        WHEN 'handover_in_id'    THEN 'INT NULL AFTER handover_out_id'
        WHEN 'returned_at'       THEN 'DATETIME NULL AFTER handover_in_id'
        WHEN 'confirmed_by'      THEN 'INT NULL AFTER returned_at'
        WHEN 'updated_at'        THEN 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
      END);
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  END LOOP;
  CLOSE cur;

  -- Ubah DATE -> DATETIME bila masih DATE (agar tanggal+jam tersimpan)
  IF EXISTS (SELECT 1 FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='vehicle_loans'
               AND column_name='start_date' AND data_type='date') THEN
    ALTER TABLE vehicle_loans MODIFY start_date DATETIME NULL;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='vehicle_loans'
               AND column_name='end_date' AND data_type='date') THEN
    ALTER TABLE vehicle_loans MODIFY end_date DATETIME NULL;
  END IF;

  -- Perluas status kendaraan agar sesuai modul peminjaman
  ALTER TABLE vehicles MODIFY status
    ENUM('TERSEDIA','DIPINJAM','DIGUNAKAN','SERVICE','RUSAK','NON OPERASIONAL') DEFAULT 'TERSEDIA';
END$$
DELIMITER ;

CALL fix_vehicle_loans();
DROP PROCEDURE IF EXISTS fix_vehicle_loans;

SELECT 'Migrasi vehicle_loans selesai. Struktur:' AS info;
SHOW COLUMNS FROM vehicle_loans;
