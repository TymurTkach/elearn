# Web Application for E-Learning

Bachelor thesis project focused on the design and implementation of a web-based e-learning platform for programming education.

## Overview

This project is a web application that supports online learning through courses, lessons, quizzes, and programming tasks.
The system is designed for three main user roles:

- **Student** - studies lessons, completes quizzes, and solves programming tasks
- **Teacher** - creates and manages courses, lessons, and student results
- **Admin** - has extended access to users, courses, and results across the system

The platform also includes additional features such as:

- classic email/password authentication
- Google OAuth login
- two-factor authentication (2FA)
- automatic evaluation of programming tasks using **Judge0**
- result tracking and basic gamification elements

## Main Features

- User registration and login
- Role-based access control
- Course and lesson management
- Quiz system with result storage
- Programming tasks with automatic code evaluation
- Student progress tracking
- Admin/teacher dashboard
- Optional external authentication and 2FA support

## Technologies Used

- **Backend:** PHP
- **Database:** MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **Web Server:** Apache
- **Code Evaluation:** Judge0
- **Environment:** Ubuntu, Virtual Machines

## System Architecture

The application follows a client-server architecture.

- **Web server VM** - hosts the PHP application
- **Database server VM** - stores persistent data in MariaDB
- Communication between the web server and database server is performed over a private network

## Database Structure

The system is built around the following main entities:

- `users`
- `courses`
- `lessons`
- `quizzes`
- `results`
- `code_tasks`
- `code_task_results`
- `user_badges`

## Project Structure

```text
project-root/
├── admin/
├── api/
├── uploads/
├── auth.php
├── config.example.php
├── index.php
├── login.php
├── register.php
├── styles.css
├── theme.js
└── README.md
```

## Configuration

The real application configuration is stored locally in `config.php`, which is excluded from the repository.

To configure the project:

1. Create a local `config.php` file based on `config.example.php`.
2. Fill in your database credentials.
3. Set your Google OAuth credentials if you want Google login enabled.
4. Set a secure teacher registration key.
5. Make sure the Judge0 service is reachable from the application.

## Notes

- `config.php` is intentionally not included in this repository for security reasons.
- The `vendor/` directory is also excluded and dependencies should be installed with Composer.
- The `uploads/` directory is ignored to avoid publishing local or user-generated files.
