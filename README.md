# TaskForge

An enterprise-grade task management application with team collaboration, AI-powered suggestions, and real-time notifications.

**Tech Stack:** PHP 8.2 · MySQL/MariaDB · Bootstrap 5 · jQuery · XAMPP  
**License:** MIT · **Author:** Muhammad Altamash

---

## Features

- **Task Management** — Full CRUD with drag-and-drop reordering, categories, priorities, due dates, and tags
- **Team Collaboration** — Create teams, invite members by email, share tasks with granular permissions
- **AI Suggestions** — Google Gemini integration for smart task descriptions and sub-task generation
- **Comments & Activity** — Threaded comments on tasks with real-time notification feeds
- **Rich Text Editor** — WYSIWYG descriptions with image upload support
- **Offline Support** — Service Worker + IndexedDB for offline-first capability
- **Security** — Bcrypt password hashing, CSRF protection, parameterized queries, session security

---

## Project Structure

```
TaskForge/
├── app/
│   ├── Controllers/        # Request handlers (Auth, Task, Team, AI, etc.)
│   ├── Models/             # Database models (User, Task, Team, Comment)
│   └── Services/           # Business logic layer
├── config/
│   ├── app.php             # Application settings
│   └── database.php        # Database credentials
├── core/
│   ├── Bootstrap.php       # Environment loader, session, error handler
│   └── Database.php        # PDO singleton
├── database/
│   └── schema.sql          # Full database schema (import into phpMyAdmin)
├── public/                 # Web root (point Apache here)
│   ├── index.php           # Front controller / router
│   ├── .htaccess           # URL rewriting
│   ├── index.html          # SPA frontend
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript (app.js, idb.js)
│   ├── manifest.json       # PWA manifest
│   └── sw.js               # Service Worker
├── storage/
│   ├── logs/               # Application & debug logs
│   └── uploads/            # User-uploaded files
├── .env.example            # Environment template
├── .gitignore
├── LICENSE
└── README.md
```

---

## Quick Start (XAMPP)

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) with PHP 8.2+ and MySQL/MariaDB
- cURL PHP extension enabled (for Gemini AI)

### 1. Clone & Configure

```bash
# Clone the repository
git clone <repository-url> TaskForge

# Copy environment config
cd TaskForge
cp .env.example .env
```

Edit `.env` with your settings (XAMPP defaults work out of the box):

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskforge
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Create the Database

1. Open **phpMyAdmin** at `http://localhost/phpmyadmin`
2. Click **Import** → choose `database/schema.sql` → click **Go**

### 3. Set Up Apache

**Option A — Symlink (recommended):**

```bash
# Windows (run as Administrator)
mklink /D "C:\xampp\htdocs\TaskForge" "E:\Downloads\TaskForge"
```

**Option B — Copy the project:**

Copy the entire `TaskForge` folder into `C:\xampp\htdocs\`

### 4. Access the App

Open your browser and navigate to:

```
http://localhost/TaskForge/public/
```

### 5. (Optional) AI Features

To enable AI-powered task suggestions, add your Google Gemini API key to `.env`:

```ini
GEMINI_API_KEY=your-actual-api-key
```

---

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/auth/register` | No | Register new user |
| `POST` | `/api/v1/auth/login` | No | Login |
| `POST` | `/api/v1/auth/logout` | Yes | Logout |
| `GET` | `/api/v1/auth/me` | Yes | Check session |
| `GET` | `/api/v1/tasks` | Yes | List all tasks |
| `POST` | `/api/v1/tasks` | Yes | Create task |
| `PUT` | `/api/v1/tasks/{id}` | Yes | Update task |
| `DELETE` | `/api/v1/tasks/{id}` | Yes | Delete task |
| `POST` | `/api/v1/tasks/reorder` | Yes | Reorder tasks |
| `GET/POST` | `/api/v1/tasks/{id}/comments` | Yes | Task comments |
| `GET/POST` | `/api/v1/tasks/{id}/shares` | Yes | Task sharing |
| `GET/POST` | `/api/v1/teams` | Yes | Team management |
| `POST` | `/api/v1/teams/{id}/members` | Yes | Add team member |
| `POST` | `/api/v1/ai/suggest` | Yes | AI suggestion |
| `GET` | `/api/v1/notifications` | Yes | Get notifications |
| `GET` | `/api/v1/status` | No | Health check |

---

## Security

- **Passwords**: Bcrypt hashing (cost factor 12)
- **Sessions**: Secure cookies with `httponly`, `samesite=Strict`
- **SQL**: Parameterized queries throughout (PDO prepared statements)
- **XSS**: Input sanitization and output encoding
- **CORS**: Configurable via `.htaccess`
- **File Uploads**: MIME type validation, safe filenames

---

## License

MIT License — see [LICENSE](LICENSE) for details.
