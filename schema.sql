PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user'
        CHECK(role IN ('admin', 'user', 'viewer')),
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS user_groups (
    user_id INTEGER NOT NULL REFERENCES users(id),
    group_id INTEGER NOT NULL REFERENCES groups(id),
    PRIMARY KEY (user_id, group_id)
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    due_date TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS task_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL REFERENCES tasks(id),
    group_id INTEGER NOT NULL REFERENCES groups(id),
    status TEXT NOT NULL DEFAULT 'On plan'
        CHECK(status IN (
            'On plan', 'On development',
            'On testing', 'Completed'
        )),
    progress TEXT DEFAULT '',
    updated_by INTEGER REFERENCES users(id),
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(task_id, group_id)
);

CREATE TABLE IF NOT EXISTS task_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INTEGER NOT NULL
        REFERENCES task_assignments(id),
    old_status TEXT,
    new_status TEXT NOT NULL,
    description TEXT DEFAULT '',
    updated_by INTEGER REFERENCES users(id),
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO groups(name) VALUES
    ('IT Development'),
    ('IT Infrastructure'),
    ('IT Security'),
    ('IT Strategy'),
    ('IT Digital');
