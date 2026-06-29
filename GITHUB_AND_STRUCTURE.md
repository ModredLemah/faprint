# FA Print - Project Organization & GitHub Setup Guide

This guide explains how to organize your files for a professional production environment and how to host your code on GitHub.

## 1. Recommended Project Structure

To ensure your system works correctly on a real server (like Apache or Nginx), you should organize your files as follows. This structure separates the backend logic, frontend assets, and user-uploaded data.

```text
faprint/ (Your Repository Root)
├── .gitignore              # Files that should NOT be on GitHub (e.g., .env, uploads)
├── README.md               # Project overview
├── SETUP.md                # Technical setup instructions
├── database.sql            # The database schema file
├── faprint_er_diagram.png  # Database relationship diagram
│
├── api/                    # PHP Backend API (Core Logic)
│   ├── config.php          # Central configuration
│   ├── db.php              # Database connection helper
│   ├── users.php           # User & Auth management
│   ├── orders.php          # Order & Document management
│   ├── admin.php           # Admin operations
│   └── support.php         # Support ticket system
│
├── public/                 # Frontend Files (Accessible to users)
│   ├── css/                # (Optional) Move styles here
│   ├── js/                 # (Optional) Move scripts here
│   ├── document_inbox.html
│   ├── fa_print_student.html
│   ├── fa_print_vendor.html
│   └── ... (all other .html files)
│
├── uploads/                # User uploaded files (EMPTY on GitHub)
└── logs/                   # System error logs (EMPTY on GitHub)
```

---

## 2. Setting Up GitHub

Since you already have a GitHub account, follow these steps to upload your project:

### Step A: Create the Repository on GitHub
1. Log in to [GitHub](https://github.com).
2. Click the **"+"** icon in the top right and select **"New repository"**.
3. Name it `faprint`.
4. Set it to **Public** or **Private** (Private is safer if you have sensitive info).
5. **Do NOT** check "Initialize this repository with a README" (we will do this from your computer).
6. Click **"Create repository"**.

### Step B: Initialize Git on Your Computer
Open your terminal or command prompt in your project folder and run:

```bash
# 1. Initialize git
git init

# 2. Add all files to the staging area
git add .

# 3. Create your first commit
git commit -m "Initial commit: Production-ready FA Print system"

# 4. Create a main branch
git branch -M main

# 5. Link to your GitHub (Replace USERNAME with your actual GitHub name)
git remote add origin https://github.com/USERNAME/faprint.git

# 6. Upload the files
git push -u origin main
```

---

## 3. Important: The `.gitignore` File

You should **never** upload your database passwords or user-uploaded documents to GitHub. Create a file named `.gitignore` in your root folder with this content:

```text
# Ignore environment variables (passwords)
.env
api/config.php (if it contains real passwords)

# Ignore user uploads
uploads/*
!uploads/.gitkeep

# Ignore logs
logs/*.log

# Ignore OS files
.DS_Store
Thumbs.db
```

---

## 4. Moving from Local to Server

When you are ready to put this on a real website:
1. **Clone the code**: Run `git clone https://github.com/USERNAME/faprint.git` on your server.
2. **Set Permissions**: Ensure the `uploads/` and `logs/` folders are "writable" by the server (usually `chmod 775`).
3. **Configure Database**: Import `database.sql` and update `api/config.php` with the server's database details.
4. **Update API URL**: In your HTML files, change `const API_BASE` to point to your real domain (e.g., `https://faprint.com/api`).

---

**FA Print v1.0.0** | Prepared by Manus AI
