PSU Internationalization Management System
A web-based Internationalization Management System designed to streamline and manage international programs, partnerships, mobility applications, document submissions, travel information, and administrative workflows for Pangasinan State University.

Features
User Management
Multi-role authentication
Super Admin
Admin
Applicant
Faculty
Program Management
Create and manage international programs
Program categories
Partner institution management
Agreement type management
Country management
Application Management
Student and faculty applications
Program enrollment
Application review and approval workflow
Application status tracking
Document Management
Upload supporting documents
Requirement management
Program-specific requirements
Document verification
International Partnerships
Partner institution database
Agreement tracking
Country directory
Notifications
System notifications
Application status notifications
Administrative alerts
Travel Information
Travel documentation
Flight information tracking
Mobility records
Technologies Used
PHP 8+
MySQL / MariaDB
PDO Database Connection
HTML5
CSS3
JavaScript
Bootstrap
XAMPP / Laragon
Installation Guide
1. Clone the Repository
git clone https://github.com/Obero-julio/psu-internationalization-system.git
2. Move Project
Place the project inside:

xampp/htdocs/
Example:

xampp/htdocs/internalization_management
3. Create Database
Open phpMyAdmin:

http://localhost/phpmyadmin
Create a new database:

internalization_management
4. Import Database
Import:

database/internalization_management.sql
5. Configure Database Connection
Open:

config/database.php
Update database credentials if necessary:

$host = '127.0.0.1';
$port = 3306;
$db   = 'internalization_management';
$user = 'root';
$pass = '';
6. Run the Project
Open:

http://localhost/internalization_management/public
Project Structure
internalization_management/
│
├── admin/
├── api/
├── asset/
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/
├── database/
│   └── internalization_management.sql
│
├── includes/
├── public/
├── uploads/
├── user/
└── tests/
Roles and Permissions
Super Admin
Full system access
System configuration
User management
Program management
Admin
Manage applications
Manage documents
Review submissions
Manage programs
Applicant
Submit applications
Upload documents
Track application status
Receive notifications
Faculty
Participate in mobility programs
Submit requirements
Manage profile
Database
Database Name:

internalization_management
Database Backup File:

database/internalization_management.sql
Security Features
Password hashing
PDO prepared statements
Session-based authentication
Role-based access control
File upload validation
Development Environment
Recommended:

XAMPP
Laragon
Visual Studio Code
Git
GitHub
Repository
GitHub Repository:

https://github.com/Obero-julio/psu-internationalization-system
Author
Julio Obero

License
This project was developed for academic and research purposes.
