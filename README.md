<div align="center">
  
  # ⚡ TaskForge

  **An Enterprise-Grade Task Management & Collaboration Platform**

  [![PHP Version](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net/)
  [![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com/)
  [![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
  [![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

  <p align="center">
    TaskForge is a state-of-the-art, self-hosted task management application built with pure PHP. It features a stunning glassmorphic UI, real-time collaboration, AI integration, and powerful analytics.
  </p>

</div>

---

## ✨ Key Features

### 🎨 Enterprise Visual Design
- **Glassmorphic UI**: Premium frosted glass components, ambient gradient backgrounds, and refined `Inter` typography.
- **Micro-interactions**: Fluid stagger animations, hover states, input focus rings, and ripple effects.
- **Customization**: Built with vanilla CSS and Bootstrap 5 for maximum extensibility without the overhead.

### 📋 Powerful Task Management
- **Hierarchical Tasks**: Support for parent tasks and infinite nested subtasks.
- **Multiple Views**: Switch seamlessly between List View and a rich **Calendar View** (via FullCalendar).
- **Kanban-Ready**: Drag-and-drop reordering powered by SortableJS.
- **Rich Context**: WYSIWYG editor (TinyMCE) for descriptions, custom tags, prioritized workflows, and due dates.

### 👥 Team Collaboration
- **Workspaces**: Create teams, assign granular roles (Admin, Manager, Member, Viewer), and invite via secure tokenized email links.
- **Threaded Discussions**: Nested comments with emoji reactions and @mention support.
- **Shared Resources**: Upload and attach team-specific files and documentation.
- **Activity Feed**: Real-time auditing of who did what, and when.

### 🤖 AI-Powered Productivity
- **Google Gemini Integration**: 
  - Automatically categorize tasks and assign priorities based on natural language descriptions.
  - Generate actionable subtasks from complex parent tasks.
  - Parse meeting notes directly into structured workflow items.

### 📊 Advanced Analytics
- **Visual Dashboards**: Interactive Chart.js integration featuring category doughnuts, priority bar charts, and 7-day completion timelines.
- **Productivity Metrics**: Track velocity and team workload distribution at a glance.

### ⚡ Real-World Engineering
- **Offline First**: Service Worker and IndexedDB integration caches tasks for seamless offline viewing.
- **Robust Security**: Bcrypt (cost 12) hashing, strict CSRF tokens, PDO parameterized queries, and sanitized output.
- **Exporting**: Generate instant CSV spreadsheets or PDF reports of your task lists.
- **Keyboard Navigation**: Global hotkeys (`N` for new task, `/` for search, `Esc` to close modals) for power users.

---

## 🏗️ Architecture

TaskForge implements a custom, lightweight MVC (Model-View-Controller) architecture in vanilla PHP, proving you don't need heavy frameworks for enterprise features.

```text
TaskForge/
├── app/
│   ├── Controllers/        # Route handlers (TaskController, AuthController, etc.)
│   ├── Models/             # Active Record pattern (Task, User, Team)
│   └── Services/           # Complex business logic & integrations
├── config/                 # Environment overrides
├── core/
│   ├── Bootstrap.php       # Dependency injection, error handling
│   └── Database.php        # PDO Singleton wrapper
├── database/
│   ├── schema.sql          # v1 base schema
│   └── migration_v2.sql    # v2 schema (Subtasks, Threads, Reactions)
├── public/                 # Web root (DocumentRoot)
│   ├── index.php           # Front Controller / Router
│   ├── css/                # Enterprise Design System (style.css)
│   ├── js/                 # Vanilla JS SPA logic (app.js)
│   └── index.html          # Main application skeleton
└── storage/                # Logs and user uploads
```

---

## 🚀 Quick Start (XAMPP / Local Server)

### Prerequisites

- **PHP 8.2+** with `pdo_mysql` and `curl` extensions enabled.
- **MySQL 8.0+** or **MariaDB**.
- Apache or Nginx (with URL rewriting enabled).

### 1. Installation

Clone the repository into your web server's root directory (e.g., `C:\xampp\htdocs\TaskForge`):

```bash
git clone https://github.com/sm-altamash/TaskForge.git
cd TaskForge
```

### 2. Configuration

Copy the example environment variables:

```bash
cp .env.example .env
```

Edit your `.env` file. To enable AI features, you **must** provide a Google Gemini API Key:

```ini
DB_HOST=127.0.0.1
DB_DATABASE=taskforge
DB_USERNAME=root
DB_PASSWORD=

# Required for AI categorization and subtask generation
GEMINI_API_KEY=your_gemini_api_key_here
```

### 3. Database Setup

1. Open MySQL/phpMyAdmin and create a database named `taskforge`.
2. Import the base schema:
   ```bash
   mysql -u root -p taskforge < database/schema.sql
   ```
3. Import the v2 features migration:
   ```bash
   mysql -u root -p taskforge < database/migration_v2.sql
   ```

### 4. Running the App

Navigate to the public directory in your browser:

```text
http://localhost/TaskForge/public/
```

*(Note: In a production environment, you should configure your web server's DocumentRoot to point directly to the `/public` folder).*

---

## 🛡️ Security

TaskForge takes data protection seriously:
- All database queries utilize **PDO Prepared Statements**.
- Passwords are salted and hashed using **Bcrypt**.
- **CSRF Tokens** are required for all state-changing POST/PUT/DELETE requests.
- Sessions use `HttpOnly` and `SameSite=Strict` cookie flags.
- User input is aggressively sanitized via `htmlspecialchars()` to prevent XSS.

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---
<div align="center">
  <i>Developed to demonstrate modern, framework-less PHP engineering.</i><br>
  <b>Author:</b> Muhammad Altamash
</div>
