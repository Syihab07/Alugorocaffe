CREATE DATABASE IF NOT EXISTS alugoro_cafe;-
USE alugoro_cafe;

-- Tabel 1: users (untuk login)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    role ENUM('admin', 'kasir') DEFAULT 'kasir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel 2: menu (daftar menu makanan/minuman)
CREATE TABLE menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('makanan', 'minuman', 'dessert') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    stock INT DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel 3: tables (daftar meja)
CREATE TABLE tables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_number VARCHAR(10) UNIQUE NOT NULL,
    capacity INT NOT NULL,
    status ENUM('tersedia', 'terisi') DEFAULT 'tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel 4: orders (pesanan - berelasi dengan menu, tables, dan users)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    table_id INT,
    menu_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    customer_name VARCHAR(100),
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'selesai', 'dibatalkan') DEFAULT 'pending',
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
    FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert data default user (password: admin123 dan kasir123 - sudah di-hash dengan md5)
INSERT INTO users (username, password, fullname, role) VALUES
('admin', MD5('admin123'), 'Administrator', 'admin'),
('kasir1', MD5('kasir123'), 'Kasir Satu', 'kasir');

-- Insert data default menu
INSERT INTO menu (name, category, price, description, stock) VALUES
('Nasi Goreng Spesial', 'makanan', 25000, 'Nasi goreng dengan telur, ayam, dan sayuran', 50),
('Mie Goreng', 'makanan', 20000, 'Mie goreng spesial dengan topping lengkap', 40),
('Ayam Bakar', 'makanan', 30000, 'Ayam bakar dengan bumbu khas', 30),
('Sate Ayam', 'makanan', 28000, 'Sate ayam 10 tusuk dengan bumbu kacang', 35),
('Es Teh Manis', 'minuman', 5000, 'Teh manis dingin segar', 100),
('Es Jeruk', 'minuman', 8000, 'Jeruk peras segar', 80),
('Kopi Hitam', 'minuman', 10000, 'Kopi hitam original', 60),
('Jus Alpukat', 'minuman', 15000, 'Jus alpukat segar', 40),
('Es Krim Vanilla', 'dessert', 12000, 'Es krim vanilla premium', 50),
('Pisang Goreng', 'dessert', 10000, 'Pisang goreng crispy', 45);

-- Insert data default tables
INSERT INTO tables (table_number, capacity, status) VALUES
('M01', 2, 'tersedia'),
('M02', 4, 'tersedia'),
('M03', 4, 'tersedia'),
('M04', 6, 'tersedia'),
('M05', 2, 'tersedia'),
('M06', 8, 'tersedia'),
('M07', 4, 'tersedia'),
('M08', 2, 'tersedia');

-- Insert beberapa sample orders
INSERT INTO orders (order_number, table_id, menu_id, quantity, total_price, customer_name, user_id, status) VALUES
('ORD001', 1, 1, 2, 50000, 'Budi Santoso', 1, 'selesai'),
('ORD002', 2, 3, 1, 30000, 'Siti Aminah', 1, 'selesai'),
('ORD003', 1, 5, 3, 15000, 'Ahmad Rizki', 2, 'pending'),
('ORD004', 3, 2, 2, 40000, 'Dewi Lestari', 2, 'pending');