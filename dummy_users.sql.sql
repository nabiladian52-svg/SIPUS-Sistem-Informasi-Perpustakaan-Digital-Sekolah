-- =====================================================
-- Data Dummy Akun Login - SIPUS
-- Password sudah di-hash menggunakan bcrypt (kompatibel dengan password_verify() PHP)
-- =====================================================

INSERT INTO users (username, password, role, nama) VALUES
('siswa1', '$2b$10$rUeu4lclnoCNM2/aGWHgRuU5kGLEYwib.5HB9PchxdHNn00oNtyza', 'siswa', 'Nabila'),
('siswa2', '$2b$10$z3bY1XV7V0OL.aOJOHRlIef5DoLrdQ4tMDmbV5PcivEPzXaUX.DXq', 'siswa', 'Meilani'),
('siswa3', '$2b$10$O7z3H4DoneqZa72GlZW0sepYuJaL7zdfHagONARfnZq7vh6DOBMKe', 'siswa', 'Elyn'),
('siswa4', '$2b$10$wr//VVIVsLEinRBoXN570uhNXDLNPWovkGHtIbfSU2/W1whwRSmBW', 'siswa', 'Elpie'),
('siswa5', '$2b$10$NL5UtOQdtUmRfYzq4ZTBLOl74CbR9AjZU9Y.B.CstqJmP9bOnYECq', 'siswa', 'Aina'),
('admin',  '$2b$10$xgj.TklL/smCCbT3RQWqme7.WF1T4QWOESEpBUCt5YBVM8wqjIwO6', 'admin', 'Admin SIPUS');

-- =====================================================
-- DAFTAR PASSWORD LOGIN (untuk dipakai saat testing login)
-- =====================================================
-- username: siswa1  | password: nabila123   | role: siswa
-- username: siswa2  | password: meilani123  | role: siswa
-- username: siswa3  | password: elyn123     | role: siswa
-- username: siswa4  | password: elpie123    | role: siswa
-- username: siswa5  | password: aina123     | role: siswa
-- username: admin   | password: admin123    | role: admin
-- =====================================================
