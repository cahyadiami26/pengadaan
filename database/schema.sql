-- ===================================================================
-- Skema Database Sistem Pengadaan Barang/Jasa
-- PT. Lampung Jasa Utama (pengadaan.lju.co.id)
-- Catatan: tabel `users` berada di database SSO terpisah (lju_portal).
-- ===================================================================
CREATE DATABASE IF NOT EXISTS pengadaan_lju CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pengadaan_lju;

-- Pemetaan role aplikasi per user (halaman Pemetaan Role)
CREATE TABLE IF NOT EXISTS role_mapping (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  app_role ENUM('admin','staff_pengadaan','kepala_bidang','kepala_divisi','pimpinan','pegawai') NOT NULL DEFAULT 'pegawai',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Cache data pegawai dari tabel users (database SSO) agar bisa di-join lintas database.
-- Disinkronkan otomatis oleh includes/user_cache.php / halaman Data Pegawai.
CREATE TABLE IF NOT EXISTS user_cache (
  user_id INT PRIMARY KEY,
  nik VARCHAR(50),
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(30),
  position VARCHAR(100),
  nama_bidang VARCHAR(60),   -- kolom users.division (nomenklatur aplikasi: bidang)
  nama_divisi VARCHAR(60),   -- kolom users.department (nomenklatur aplikasi: divisi)
  avatar VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Data Lokasi: Gedung -> Lantai -> Ruang
CREATE TABLE IF NOT EXISTS locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NULL,
  type ENUM('gedung','lantai','ruang') NOT NULL,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(30) DEFAULT NULL,
  description TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Kebutuhan (Pengajuan Kebutuhan) - header
CREATE TABLE IF NOT EXISTS needs_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(40) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  nama_bidang VARCHAR(100),
  nama_divisi VARCHAR(100),
  purpose TEXT,
  needed_date DATE,
  status ENUM('DRAFT','DIAJUKAN','DIVERIFIKASI','DISETUJUI_KABAG','DALAM_PROSES_PENGADAAN','MASUK_RENCANA_PENGADAAN','FINALISASI_KEUANGAN','DITERIMA','DITOLAK') NOT NULL DEFAULT 'DRAFT',
  verified_by INT NULL, verified_at DATETIME NULL,
  approved1_by INT NULL, approved1_at DATETIME NULL,
  approved2_by INT NULL, approved2_at DATETIME NULL,
  finance_by INT NULL, finance_at DATETIME NULL,
  rejected_reason TEXT,
  plan_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS needs_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  need_id INT NOT NULL,
  item_name VARCHAR(200) NOT NULL,
  category ENUM('asset','inventaris','peralatan_kantor','sparepart','barang_habis_pakai') NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  unit VARCHAR(30) DEFAULT 'unit',
  est_price DECIMAL(15,2) DEFAULT 0,
  specification TEXT,
  note TEXT,
  FOREIGN KEY (need_id) REFERENCES needs_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rencana Pengadaan
CREATE TABLE IF NOT EXISTS procurement_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_no VARCHAR(40) NOT NULL UNIQUE,
  plan_date DATE NOT NULL,
  requesting_units VARCHAR(255),
  budget DECIMAL(15,2) DEFAULT 0,
  priority ENUM('Rendah','Sedang','Tinggi','Sangat Tinggi') DEFAULT 'Sedang',
  status ENUM('DRAFT','DIAJUKAN','DISSETUJUI_KADIV','DISETUJUI_DIREKTUR','FINAL_KEUANGAN','DALAM_PROSES_PENGADAAN','DITERIMA','DITOLAK') NOT NULL DEFAULT 'DRAFT',
  approved1_by INT NULL, approved1_at DATETIME NULL,
  approved2_by INT NULL, approved2_at DATETIME NULL,
  finance_by INT NULL, finance_at DATETIME NULL,
  note TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procurement_plan_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  need_item_id INT NULL,
  item_name VARCHAR(200) NOT NULL,
  category ENUM('asset','inventaris','peralatan_kantor','sparepart','barang_habis_pakai','jasa') DEFAULT 'asset',
  qty INT NOT NULL DEFAULT 1,
  unit VARCHAR(30) DEFAULT 'unit',
  est_price DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (plan_id) REFERENCES procurement_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Penerimaan Barang/Jasa
CREATE TABLE IF NOT EXISTS receptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receive_no VARCHAR(40) NOT NULL UNIQUE,
  plan_id INT NULL,
  need_id INT NULL,
  supplier VARCHAR(200),
  invoice_no VARCHAR(100),
  receive_date DATE NOT NULL,
  received_by INT,
  status ENUM('DITERIMA','DITERIMA SEBAGIAN','DITOLAK') NOT NULL DEFAULT 'DITERIMA',
  chk_qty TINYINT(1) DEFAULT 0, chk_spec TINYINT(1) DEFAULT 0,
  chk_condition TINYINT(1) DEFAULT 0, chk_docs TINYINT(1) DEFAULT 0,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES procurement_plans(id) ON DELETE SET NULL,
  FOREIGN KEY (need_id) REFERENCES needs_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reception_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reception_id INT NOT NULL,
  item_name VARCHAR(200) NOT NULL,
  category ENUM('asset','inventaris','peralatan_kantor','sparepart','barang_habis_pakai','jasa') DEFAULT 'asset',
  qty_ordered INT DEFAULT 1,
  qty_received INT DEFAULT 0,
  unit VARCHAR(30) DEFAULT 'unit',
  price DECIMAL(15,2) DEFAULT 0,
  to_stock TINYINT(1) DEFAULT 0,
  asset_id INT NULL,
  note TEXT,
  FOREIGN KEY (reception_id) REFERENCES receptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Inventaris & Aset
CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category ENUM('asset','inventaris') NOT NULL DEFAULT 'asset',
  sub_category VARCHAR(100) DEFAULT NULL,
  purchase_date DATE,
  value DECIMAL(15,2) DEFAULT 0,
  location_id INT NULL,
  user_id INT NULL,
  unit VARCHAR(100),
  condition_status ENUM('BAIK','RUSAK RINGAN','RUSAK BERAT') DEFAULT 'BAIK',
  status ENUM('AKTIF','RUSAK','DIPINDAHKAN','DIHAPUS','HILANG') DEFAULT 'AKTIF',
  qr_file VARCHAR(255) DEFAULT NULL,
  serial_number VARCHAR(120) DEFAULT NULL,
  brand VARCHAR(100), model VARCHAR(100), year INT,
  note TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Mutasi Aset (histori otomatis)
CREATE TABLE IF NOT EXISTS asset_mutations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  from_unit VARCHAR(100), from_location VARCHAR(150), from_user VARCHAR(150),
  to_unit VARCHAR(100), to_location VARCHAR(150), to_user VARCHAR(150),
  reason TEXT,
  mutated_by INT, mutated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Penghapusan Aset
CREATE TABLE IF NOT EXISTS asset_disposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  disposal_no VARCHAR(40) NOT NULL UNIQUE,
  asset_id INT NOT NULL,
  reason ENUM('RUSAK BERAT','HILANG','TIDAK EKONOMIS','TIDAK DIPAKAI','LAINNYA') DEFAULT 'RUSAK BERAT',
  description TEXT,
  status ENUM('PENGAJUAN','VERIFIKASI','PERSETUJUAN','BERITA_ACARA','DIHAPUS') DEFAULT 'PENGAJUAN',
  verified_by INT NULL, approved_by INT NULL,
  ba_no VARCHAR(100) NULL, ba_date DATE NULL,
  created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Laporan Kerusakan
CREATE TABLE IF NOT EXISTS damage_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_no VARCHAR(40) NOT NULL UNIQUE,
  asset_id INT NULL,
  item_name VARCHAR(200),
  location_id INT NULL,
  reported_by INT NOT NULL,
  report_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  damage_type VARCHAR(80) DEFAULT 'Fisik',
  damage_desc TEXT NOT NULL,
  additional_note TEXT,
  photo VARCHAR(255) DEFAULT NULL,
  status ENUM('DILAPORKAN','DIVERIFIKASI','DIAGNOSIS','PERBAIKAN','DALAM PERBAIKAN','SELESAI','DITOLAK') DEFAULT 'DILAPORKAN',
  verified_by INT NULL,
  repair_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Pemeliharaan & Perbaikan
CREATE TABLE IF NOT EXISTS maintenances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maint_no VARCHAR(40) NOT NULL UNIQUE,
  asset_id INT NULL,
  damage_report_id INT NULL,
  damage_desc TEXT,
  maint_date DATE NOT NULL,
  vendor VARCHAR(200),
  cost DECIMAL(15,2) DEFAULT 0,
  action_taken TEXT,
  technician VARCHAR(150),
  status ENUM('TERENCANA','JANJI','PENGERJAAN','DALAM PERBAIKAN','SELESAI','BATAL') DEFAULT 'TERENCANA',
  created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Stock Opname
CREATE TABLE IF NOT EXISTS stock_opnames (
  id INT AUTO_INCREMENT PRIMARY KEY,
  opname_no VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(200),
  location_id INT NULL, unit VARCHAR(100),
  opname_date DATE NOT NULL,
  status ENUM('OPEN','VALIDATED','REPORTED') DEFAULT 'OPEN',
  created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_opname_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  opname_id INT NOT NULL,
  asset_id INT NULL,
  scanned_code VARCHAR(120),
  match_status ENUM('COOK','MISSING','EXTRA') DEFAULT 'COOK',
  checked_condition VARCHAR(50),
  note TEXT,
  FOREIGN KEY (opname_id) REFERENCES stock_opnames(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Perlengkapan / ATK (stok)
CREATE TABLE IF NOT EXISTS supplies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category ENUM('atk','perlengkapan','sparepart','barang_habis_pakai','peralatan') DEFAULT 'atk',
  unit VARCHAR(30) DEFAULT 'pcs',
  stock INT DEFAULT 0,
  min_stock INT DEFAULT 0,
  stock_minimum_warning TINYINT(1) DEFAULT 0,
  last_price DECIMAL(15,2) DEFAULT 0,
  location_id INT NULL,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Transaksi stok (masuk/keluar) + riwayat
CREATE TABLE IF NOT EXISTS supply_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supply_id INT NOT NULL,
  type ENUM('MASUK','KELUAR','MUTASI') NOT NULL,
  qty INT NOT NULL,
  ref_type VARCHAR(40) DEFAULT NULL,
  ref_id INT DEFAULT NULL,
  from_unit VARCHAR(100), to_unit VARCHAR(100),
  note TEXT,
  created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Permintaan ATK + Workflow Distribusi
CREATE TABLE IF NOT EXISTS supply_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(40) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  nama_bidang VARCHAR(100), nama_divisi VARCHAR(100),
  purpose TEXT,
  status ENUM('MENUNGGU','DIPROSES','DISETUJUI','DISTRIBUSI','SELESAI','TIDAK_TERSEDIA','DITOLAK') DEFAULT 'MENUNGGU',
  stock_available TINYINT(1) DEFAULT NULL,
  verified_by INT NULL, approved_by INT NULL, distributed_by INT NULL,
  handover_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supply_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  supply_id INT NOT NULL,
  qty_requested INT DEFAULT 1,
  qty_approved INT DEFAULT 0,
  FOREIGN KEY (request_id) REFERENCES supply_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Form Serah Terima (handover) dengan tanda tangan canvas
CREATE TABLE IF NOT EXISTS handovers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  handover_no VARCHAR(40) NOT NULL UNIQUE,
  ref_type VARCHAR(40) DEFAULT 'permintaan_atk',
  ref_id INT DEFAULT NULL,
  from_user_id INT, to_user_id INT,
  handover_date DATE NOT NULL,
  items_desc TEXT,
  sig_giver MEDIUMTEXT, sig_receiver MEDIUMTEXT,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Fasilitas Kantor
CREATE TABLE IF NOT EXISTS facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(80) DEFAULT 'Lainnya', -- AC, Proyektor, Meja, Kursi, Lemari, Printer, Televisi, Toilet, Instalasi Listrik, Air, Peralatan Umum, Lainnya
  location_id INT NULL,
  quantity INT DEFAULT 1,
  capacity VARCHAR(100) DEFAULT NULL,
  status ENUM('BAIK','RUSAK','DIPINJAM','BERMASALAH','PERBAIKAN','TIDAK AKTIF') DEFAULT 'BAIK',
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facility_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(40) NOT NULL UNIQUE,
  facility_id INT NULL,
  location_id INT NULL,
  problem_type VARCHAR(80),
  problem_desc TEXT NOT NULL,
  photo VARCHAR(255),
  priority ENUM('Rendah','Normal','Tinggi','Segera') DEFAULT 'Normal',
  reported_by INT NOT NULL,
  status ENUM('LAPORAN','VERIFIKASI','PENUGASAN','PERBAIKAN','PEMERIKSAAN','SELESAI') DEFAULT 'LAPORAN',
  assigned_to VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Kendaraan
CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tnkb VARCHAR(20) NOT NULL,
  brand_model VARCHAR(120),
  year YEAR,
  frame_no VARCHAR(60), engine_no VARCHAR(60),
  condition_status ENUM('BAIK','RUSAK RINGAN','RUSAK BERAT') DEFAULT 'BAIK',
  location VARCHAR(120),
  pic VARCHAR(150),
  status ENUM('TERSEDIA','DIPINJAM','DIGUNAKAN','SERVICE','RUSAK','NON OPERASIONAL') DEFAULT 'TERSEDIA',
  last_km INT DEFAULT 0,
  service_interval_km INT DEFAULT 10000,
  next_service_km INT DEFAULT 0,
  photo VARCHAR(255),
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Peminjaman Kendaraan
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

-- Perawatan Kendaraan
CREATE TABLE IF NOT EXISTS vehicle_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  service_date DATE NOT NULL,
  service_type VARCHAR(120),
  km_at_service INT,
  vendor VARCHAR(200), cost DECIMAL(15,2) DEFAULT 0,
  note TEXT,
  created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Log pemakaian kendaraan (diisi otomatis dari siklus peminjaman vehicle_loans)
CREATE TABLE IF NOT EXISTS vehicle_usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  loan_id INT NULL,
  user_id INT NULL,
  destination VARCHAR(200),
  borrow_date DATETIME,
  return_date DATETIME NULL,
  start_km INT DEFAULT 0,
  end_km INT NULL,
  status VARCHAR(30) DEFAULT 'DIGUNAKAN',
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed lokasi contoh
INSERT INTO locations (id, parent_id, type, name, code) VALUES
 (1, NULL, 'gedung', 'Gedung A', 'GA'),
 (2, 1, 'lantai', 'Lantai 1', 'GA-L1'),
 (3, 1, 'lantai', 'Lantai 2', 'GA-L2'),
 (4, 2, 'ruang', 'Ruang Direktur', 'DIR'),
 (5, 2, 'ruang', 'Ruang Keuangan', 'KEU'),
 (6, 2, 'ruang', 'Ruang Umum', 'UMUM'),
 (7, 3, 'ruang', 'Ruang IT', 'IT'),
 (8, 3, 'ruang', 'Ruang Operasional', 'OPS')
ON DUPLICATE KEY UPDATE name = VALUES(name);
