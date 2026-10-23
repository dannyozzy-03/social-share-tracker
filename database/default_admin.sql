-- Optional default admin for manual installs: admin / Admin@12345
-- Change it after first sign-in: php bin/create-admin.php admin "a-new-strong-password"

INSERT INTO admins (username, password_hash) VALUES
    ('admin', '$2y$10$VXKJFlQGMsW.Y.pa7bUp1ecePbOMaES3OfCQXORy9r./6YWR/CoWu')
ON DUPLICATE KEY UPDATE username = username;
