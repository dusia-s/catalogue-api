-- Symfony's test environment appends a `_test` suffix to the database name.
-- MYSQL_USER only receives grants on MYSQL_DATABASE, so the test schema has to
-- be created and granted here or `bin/phpunit` cannot connect.
CREATE DATABASE IF NOT EXISTS `catalogue_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON `catalogue_test`.* TO 'app'@'%';
FLUSH PRIVILEGES;
