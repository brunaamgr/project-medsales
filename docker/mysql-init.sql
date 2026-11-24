-- Banco separado para os testes automatizados.
CREATE DATABASE IF NOT EXISTS medsales_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON medsales_test.* TO 'medsales'@'%';
