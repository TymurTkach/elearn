# E-learning Web Platform

Academic PHP/MySQL web application for managing courses, lessons, quizzes, users, results, and basic teacher/student workflows.

## Overview

This project was created as a Bachelor's thesis / substantial academic web application during Applied Informatics studies. It demonstrates a classic PHP application using PDO with a MySQL database, session-based authentication, role-based pages, and administrative course management.

## Features

- Student and teacher registration/login flow
- Role-based dashboard and administration pages
- Course, lesson, quiz, result, and user management
- SQL schema for recreating the database structure
- PlantUML/Mermaid use-case documentation

## Technologies

- PHP
- MySQL / SQL
- PDO
- HTML/CSS
- JavaScript
- Apache/Linux deployment concepts

## Project Structure

- `admin/` - teacher/admin management screens
- `config.php` - environment-based database configuration
- `elearn.sql` - sanitized database schema and safe sample data
- `*.php` - public application pages and authentication flow
- `use-case-diagram.*` - academic design documentation

## Getting Started

Prerequisites: PHP with PDO MySQL support, MySQL/MariaDB, and a local web server.

### Installation

1. Create a local database.
2. Import `elearn.sql`.
3. Copy `.env.example` to your local environment configuration or set the variables manually.
4. Serve the project with Apache or PHP's built-in development server.

Example:

```bash
php -S localhost:8000
```

## Configuration

The application reads database settings from environment variables:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `TEACHER_REGISTRATION_KEY`

## Academic Context

The code is preserved as academic work and was not rewritten into a modern framework application. Cleanup focused on removing private configuration and documenting how the original project can be reviewed.

## What I Learned

- Building server-rendered PHP pages
- Using PDO for database access
- Designing relational tables for an e-learning system
- Handling authentication, roles, and administrative workflows

## Publication Note

Existing GitHub repository - update existing repository instead of creating a new one.
