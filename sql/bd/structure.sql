
CREATE TABLE IF NOT EXISTS users
(
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_keys
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    public_key TEXT NOT NULL,
    encrypted_private_key TEXT NOT NULL,
    private_key_iv TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_entries
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_username VARCHAR(100) NOT NULL,
    service_username_iv TEXT NOT NULL,
    service_password TEXT NOT NULL,
    service_password_iv TEXT NOT NULL,
    shared_by INTEGER NULL,
    url VARCHAR(255) DEFAULT '',
    url_iv TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    notes_iv TEXT DEFAULT '',
    category VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS password_shares
(
    id SERIAL PRIMARY KEY,
    password_id INTEGER NOT NULL,
    from_user_id INTEGER NOT NULL,
    to_user_id INTEGER NOT NULL,
    shared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (password_id) REFERENCES password_entries (id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_history
(
    id SERIAL PRIMARY KEY,
    password_id INTEGER NOT NULL,
    old_service_password TEXT NOT NULL,
    old_service_password_iv TEXT NOT NULL,
    changed_by_user_id INTEGER NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (password_id) REFERENCES password_entries (id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS encryption_settings
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    encryption_salt TEXT NOT NULL,
    encryption_iterations INTEGER NOT NULL DEFAULT 10000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS security_logs
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_devices
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    device_token VARCHAR(255) NOT NULL,
    last_used TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_categories
(
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#FFFFFF',
    icon VARCHAR(50) DEFAULT 'folder',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE (user_id, name)
);

CREATE INDEX IF NOT EXISTS idx_password_entries_user_id ON password_entries(user_id);
CREATE INDEX IF NOT EXISTS idx_password_entries_shared_by ON password_entries(shared_by) WHERE shared_by IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_password_entries_category ON password_entries(user_id, category);
CREATE INDEX IF NOT EXISTS idx_password_shares_to_user_id ON password_shares(to_user_id);
CREATE INDEX IF NOT EXISTS idx_password_shares_from_user_id ON password_shares(from_user_id);
CREATE INDEX IF NOT EXISTS idx_password_history_password_id ON password_history(password_id);
CREATE INDEX IF NOT EXISTS idx_user_keys_user_id ON user_keys(user_id);
CREATE INDEX IF NOT EXISTS idx_security_logs_user_id ON security_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_user_devices_user_id ON user_devices(user_id);
CREATE INDEX IF NOT EXISTS idx_password_categories_user_id ON password_categories(user_id);