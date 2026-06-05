```markdown
# 🎓 StudyBuddyHub - College Management System

> A complete web-based College Management System for diploma and engineering colleges. Streamline academic operations with role-based dashboards for Admin, Teacher, and Student.

---

## 📌 Project Overview

StudyBuddyHub is a comprehensive College Management System designed to digitize and automate academic operations. It serves three user roles with dedicated dashboards and functionalities, making college management efficient, transparent, and paperless.

**Live Demo:** *Coming Soon* | **Documentation:** *See below*

---

## 👥 User Roles & Access

| Role | Access Level | Key Features |
|------|--------------|--------------|
| 👑 **Admin** | Full System Control | Branch, Student, Teacher, Subject, Timetable, Result, Notification Management |
| 👨‍🏫 **Teacher** | Subject-wise Access | Attendance, Assignments, Results, Study Materials |
| 👨‍🎓 **Student** | Personal Access | Study Materials, Assignments, Results, Attendance Tracking |

---

## 🛠️ Technology Stack

| Category | Technologies |
|----------|--------------|
| **Backend** | PHP 8.x |
| **Database** | MySQL 8.x |
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript, jQuery, AJAX |
| **Libraries** | DataTables, SweetAlert2, AOS, jsPDF |
| **Server** | Apache (XAMPP/WAMP) |

---

## ✨ Features

### 👑 Admin Panel

| Module | Features |
|--------|----------|
| **Dashboard** | Real-time statistics (Students, Teachers, Pending Approvals) |
| **Branch Management** | Add, Edit, Delete, Toggle Status, Cascading Delete |
| **Student Management** | View with filters, Promote/Demote Semester, Delete with Cascade |
| **Teacher Management** | View, Assign Subjects, Toggle Active/Inactive |
| **Subject Management** | Branch-wise, Semester-wise CRUD operations |
| **Timetable Management** | Create schedules with Time Clash Detection |
| **Assignment Oversight** | View all assignments, Grade submissions |
| **Result Management** | Add/Edit/Delete results with Automatic Grade Calculation |
| **Notification System** | Send targeted notifications, View history |

### 👨‍🏫 Teacher Panel

| Module | Features |
|--------|----------|
| **Dashboard** | Overview of assigned subjects |
| **My Students** | View students with attendance & performance analytics |
| **Study Materials** | Upload/Download PDF materials |
| **Assignments** | Create, Edit, Delete, Grade submissions |
| **Attendance** | Mark daily attendance with date filtering |
| **Results** | Add/Edit exam results with auto grade calculation |
| **Timetable** | View personal teaching schedule |

### 👨‍🎓 Student Panel

| Module | Features |
|--------|----------|
| **Dashboard** | Personalized stats (Attendance, Marks, Pending) |
| **Study Materials** | View/Download subject-wise materials |
| **Assignments** | View pending, Submit solutions (PDF/DOC/Images) |
| **Attendance** | Track percentage with color-coded badges |
| **Results** | View exam results with grade cards |
| **Timetable** | View class schedule |
| **Notifications** | Receive real-time updates |

---

## 🗄️ Database Design

### Database Statistics

| Metric | Value |
|--------|-------|
| Total Tables | 21 Physical + 3 Views = 24 |
| Foreign Keys | 25+ with CASCADE DELETE |
| Normalization | Up to 3NF (Third Normal Form) |
| Transactions | 3 (Branch Delete, Student Approval, Semester Promotion) |

### Key Tables

| Table Name | Purpose |
|------------|---------|
| `user` | Authentication (Login, Password Hash, Usertype) |
| `admission` | Student personal & academic details |
| `teacher` | Teacher details (Qualification, Experience, Branch) |
| `branches` | Branch master (CSE, ECE, EE, etc.) |
| `subjects` | Subject master (Branch-wise, Semester-wise) |
| `teacher_subjects` | Teacher-Subject assignment mapping |
| `timetable` | Class schedule with day and time |
| `assignments` | Assignment details with due dates |
| `submissions` | Student assignment submissions |
| `attendance` | Daily attendance records |
| `results` | Exam results with automatic grades |
| `notifications` | System notifications |
| `admission_requests` | Pending student registrations |

---

## 🔥 Unique Features

### 1. Cascading Delete with Transaction
When a branch is deleted, ALL associated data is automatically removed:
- ✅ Students + their login accounts
- ✅ Teachers + their login accounts
- ✅ Subjects, assignments, submissions
- ✅ Materials, syllabus files
- ✅ Attendance records, results
- ✅ Timetable entries

**Uses MySQL transactions** - If any delete fails, ALL changes are rolled back.

### 2. Teacher Time Clash Detection
Prevents assigning a teacher to overlapping time slots. Complex SQL query checks for time conflicts before saving timetable.

### 3. Automatic Grade Calculation
Marks are automatically converted to grades:

| Marks Range | Grade | Performance |
|-------------|-------|-------------|
| 90-100 | A+ | Excellent |
| 80-89 | A | Very Good |
| 70-79 | B+ | Good |
| 60-69 | B | Above Average |
| 50-59 | C | Average |
| 40-49 | D | Pass |
| Below 40 | F | Fail |

### 4. Real-time Notifications
AJAX-powered notification system with read/unread status and badge count.

### 5. PDF Report Generation
Download result reports and grade cards as PDF using jsPDF.

---

## 🔒 Security Features

| Feature | Implementation |
|---------|----------------|
| **Password Security** | bcrypt hashing with `password_hash()` |
| **Session Management** | `session_start()` with timeout |
| **SQL Injection Prevention** | `mysqli_real_escape_string()` |
| **XSS Prevention** | `htmlspecialchars()` |
| **Role-based Access** | Session usertype validation on every page |
| **File Upload Security** | Type validation, size limit (5MB), unique naming |

---

## 📂 Project Structure

```
StudyBuddyHub/
├── admin/
│   ├── home.php (Dashboard)
│   ├── branches.php
│   ├── view_student.php
│   ├── view_teacher.php
│   ├── subjects.php
│   ├── timetable.php
│   ├── assignments.php
│   ├── results.php
│   ├── send_notification.php
│   └── profile.php
├── teacher/
│   ├── dashboard.php
│   ├── students.php
│   ├── materials.php
│   ├── assignments.php
│   ├── attendance.php
│   ├── results.php
│   ├── timetable.php
│   └── profile.php
├── student/
│   ├── dashboard.php
│   ├── materials.php
│   ├── assignments.php
│   ├── attendance.php
│   ├── results.php
│   ├── timetable.php
│   ├── notifications.php
│   └── profile.php
├── ajax/
│   ├── get_notifications.php
│   ├── mark_notifications_read.php
│   └── update_profile_pic.php
├── uploads/
│   ├── assignments/
│   ├── materials/
│   └── profile_pics/
├── config.php (Database connection)
├── login.php
├── signup.php
├── logout.php
├── index.php (Landing page)
├── users.sql (Database dump)
└── README.md
```

---

## 🚀 Installation Guide

### Prerequisites

- XAMPP/WAMP server installed
- PHP 8.x or higher
- MySQL 8.x or higher
- Web browser (Chrome/Firefox/Edge)

### Step-by-Step Installation

#### Step 1: Download/Clone the Repository

```bash
git clone https://github.com/your-username/StudyBuddyHub.git
```

Or download ZIP from GitHub and extract.

#### Step 2: Move to htdocs folder

Copy the project folder to:
- **XAMPP:** `C:/xampp/htdocs/StudyBuddyHub/`
- **WAMP:** `C:/wamp/www/StudyBuddyHub/`

#### Step 3: Create Database

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" to create database
3. Database name: `users`
4. Charset: `utf8mb4_general_ci`
5. Click "Create"

#### Step 4: Import Database

1. Select the `users` database
2. Click "Import" tab
3. Choose file: `users.sql` (from project folder)
4. Click "Import"

#### Step 5: Configure Database Connection

Edit `config.php`:

```php
<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);
if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($data, "utf8mb4");
?>
```

#### Step 6: Start Server

- Open XAMPP Control Panel
- Start Apache and MySQL services

#### Step 7: Run the Project

Open browser and go to: `http://localhost/StudyBuddyHub/`

### Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@gmail.com` | `1234` |
| Teacher | `teacher@gmail.com` | `123456` |
| Student | `student@gmail.com` | `000000` |

---

## 📊 Database Schema

### ER Diagram
*See project documentation for complete ER diagram*

### Key Relationships

| Relationship | Type | Cascade |
|--------------|------|---------|
| user → admission | One-to-One | CASCADE DELETE |
| user → teacher | One-to-One | CASCADE DELETE |
| branches → admission | One-to-Many | RESTRICT |
| branches → subjects | One-to-Many | CASCADE |
| teacher → teacher_subjects | One-to-Many | CASCADE |
| subjects → teacher_subjects | One-to-Many | CASCADE |
| subjects → assignments | One-to-Many | CASCADE |
| assignments → submissions | One-to-Many | CASCADE |
| admission → attendance | One-to-Many | CASCADE |
| admission → results | One-to-Many | CASCADE |

---

## 👨‍💻 Team Members

| S.No | Name | Registration No | Role |
|------|------|-----------------|------|
| 1 | Nitin Kumar Gorai | 23407065003 | Lead Developer / Team Leader |
| 2 | Rohit Gope | 23407060024 | Backend Developer |
| 3 | Kundan Kumar Singh | 23407090010 | Frontend Developer |
| 4 | Mahima Surin | 23407060011 | UI/UX Designer |
| 5 | Pakhi Mahato | 23407060014 | Quality Assurance |

**Project Guide:** Rohit Kumar Mandal

**Institution:** Chandil Polytechnic, Chandil  
**Department:** Computer Science & Engineering  
**Session:** 2024-2026

---

## 📈 Project Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 50+ |
| Lines of Code (PHP) | 8,000+ |
| Lines of Code (JS) | 3,000+ |
| Lines of Code (CSS) | 5,000+ |
| Database Tables | 24 |
| Unique Screens | 30+ |
| AJAX Calls | 10+ |
| External Libraries | 7+ |

---

## 🔧 Troubleshooting

| Issue | Solution |
|-------|----------|
| Database connection failed | Check username/password in `config.php` |
| Blank page | Enable PHP error reporting |
| Session not working | Check `session_start()` at top of pages |
| File upload not working | Check folder permissions in `uploads/` |
| 404 error | Check .htaccess or file paths |

---

## 🚀 Future Scope

- 📱 Mobile App (Android/iOS)
- 🔌 REST API for third-party integration
- ☁️ Cloud Deployment (AWS/Azure)
- 🤖 AI-based student recommendations
- 💬 Chatbot for student support
- 📝 Online Examination System
- 👪 Parent Portal
- 🎥 Video Lectures Integration
- 💳 Payment Gateway for fees
- 📲 SMS/WhatsApp notifications

---

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 🙏 Acknowledgements

- Chandil Polytechnic for providing the platform
- Project Guide Mr. Rohit Kumar Mandal for guidance
- All team members for their dedication and hard work

---

## 📧 Contact

For any queries or suggestions:

**Nitin Kumar Gorai**  
📧 nitinkumargorai2004@gmail.com  
📱 +91 98352 89540

---

## ⭐ Show Your Support

If you found this project helpful, please give it a ⭐ on GitHub!

---

**Made with ❤️ by Team StudyBuddyHub**  
*Chandil Polytechnic, Chandil | Department of Computer Science & Engineering*
```
