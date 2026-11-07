# TaskFlow

**A Lightweight Group Task & Action Tracker**

TaskFlow is a lightweight web application for managing tasks, audit findings, remediation actions, and work assignments across multiple IT groups.

It provides a centralized platform for tracking task assignments, monitoring group-specific progress, managing responsibilities, and maintaining status history.

The application is designed as a simple, practical demonstration of multi-group task management and accountability.

## Key Features

- **Task Management** – Create, edit, search, and manage tasks with descriptions and due dates.
- **Multi-Group Assignment** – Assign a single task to multiple IT groups.
- **Progress Tracking** – Maintain independent status and progress descriptions for each assigned group.
- **Status History** – Record status changes, descriptions, timestamps, and responsible users.
- **Dashboard** – Monitor task assignments, status distribution, and overdue items.
- **User Management** – Manage user accounts, roles, and group memberships.
- **Role-Based Access Control** – Support Admin, User, and Viewer roles with group-based access restrictions.

## Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP |
| Database | SQLite |
| Database Access | PDO |
| Frontend | HTML5, Bootstrap 5 |
| Styling | CSS3 |
| Development Server | PHP Built-in Server |

SQLite is used to avoid the need for a separate database server. PDO provides database access and facilitates potential migration to MySQL or MariaDB.

## User Roles

- **Admin** – Manage users, groups, tasks, and group assignments.
- **User** – Create and update tasks and maintain progress within authorized groups.
- **Viewer** – View authorized tasks and group progress without making changes.

## Default IT Groups

The initial database includes five groups:

1. IT Development
2. IT Infrastructure
3. IT Security
4. IT Strategy
5. IT Digital

Users can belong to multiple groups.

## Task Workflow

Each task contains a title, description, due date, creator, and one or more assigned groups.

Each assigned group maintains its own status and progress description.

**Task Status:**
1. On plan
2. On development
3. On testing
4. Completed

A task can be assigned to several groups, with each group reporting its own progress independently.

## Application Structure

```text
taskflow/
├── index.php       # Login and logout
├── dashboard.php   # Dashboard and status summary
├── tasks.php       # Task listing and create/edit forms
├── task.php        # Task details and group progress
├── admin.php       # User and group administration
├── config.php      # SQLite database connection
├── functions.php   # Authentication and shared functions
├── schema.sql      # Database schema and initial groups
└── style.css       # Application styling
```

## Database Structure

The application uses six main database tables:

- `users` – User accounts, password hashes, and roles.
- `groups` – IT group information.
- `user_groups` – User-to-group membership.
- `tasks` – Main task records.
- `task_assignments` – Task assignments and group-specific progress.
- `task_status_history` – Historical status changes.

A task is stored once, while each assigned group maintains a separate assignment record.

## Installation and Execution

**Requirements:**
- PHP with PDO SQLite enabled.
- A web browser.
- Git (optional, for cloning the repository).

**1. Clone the repository**

```bash
git clone https://github.com/priandoyo/taskflow.git
cd taskflow
```

**2. Start the development server**

```bash
php -S 0.0.0.0:8000
```

**3. Open the application**

Navigate to:

```text
http://localhost:8000
```

The SQLite database is initialized automatically when the application starts.

**Default demonstration account:**

```text
Username: admin
Password: admin123
```

Change the default credentials before exposing the application to other users.

## Security Considerations

TaskFlow incorporates several basic security controls:

- Password hashing and verification.
- PDO prepared statements.
- CSRF token validation.
- Server-side authentication and authorization.
- Session ID regeneration after login.
- Group-based access restrictions.

**Disclaimer:** TaskFlow is a workshop prototype and educational demonstration. It is not production-ready banking software. Additional security reviews, testing, session hardening, backup and recovery procedures, and deployment controls are required before production use.

## Future Enhancements

Potential future improvements include:

- MySQL or MariaDB database migration.
- File attachments.
- Email notifications.
- Additional workflow statuses.
- Task dependencies.
- Audit-specific metadata.
- Advanced reporting and analytics.

## Project Objective

TaskFlow demonstrates how a lightweight web application can support task coordination, multi-group accountability, and progress monitoring through a simple and maintainable technology stack.

**Repository:** https://github.com/priandoyo/taskflow
URL: https://priandoyo.wordpress.com/2026/10/25/task-flow/
