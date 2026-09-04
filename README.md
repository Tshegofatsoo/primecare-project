PrimeCare Medical Centre

Project Description
PrimeCare is a healthcare management solution designed to improve the way patients and clinic staff manage appointments, patient information and medical services.

The project consists of a patient-facing website and a mobile application for clinic users. The website allows patients to create accounts, log in, view available services, book appointments and manage their personal healthcare information. The mobile application is intended to support clinic staff through role-based Admin and Doctor dashboards.

The system aims to reduce manual administration, improve appointment management and provide a more convenient experience for both patients and clinic staff.

Client Problem
The clinic requires a more organised way of managing patient appointments and healthcare information. Manual processes can result in longer waiting times, difficulty managing appointments and increased risk of errors when handling patient information.

PrimeCare addresses these challenges by providing digital interfaces for appointment booking, patient information management, queue management and medical record access.

Website Scope
The PrimeCare website provides patient-facing functionality, including:

Viewing information about the medical centre
Viewing available healthcare services
Viewing doctor information
Creating a patient account
Patient login and authentication
Password recovery and reset
Booking appointments
Viewing and managing appointments
Viewing medical records
Viewing prescriptions
Requesting prescription repeats
Managing personal profile information
Viewing notifications
Contacting the medical centre
Patients must log in before accessing account-specific functionality.

Mobile Application Scope
The mobile application is designed to provide role-based access for PrimeCare users.

Administrator Dashboard
The Administrator dashboard supports functions such as:

Viewing appointments made by patients
Managing appointments
Registering walk-in patients
Generating queue numbers
Assigning patients to available doctors
Doctor Dashboard
The Doctor dashboard supports functions such as:

Viewing assigned patients
Accessing relevant patient information
Recording consultation notes
Updating medical records
Issuing prescriptions
Managing assigned patient consultations
Role-based access is used to ensure that users only access functions appropriate to their role.

Technologies and Tools
The project uses the following technologies and development tools:

HTML5
CSS3
JavaScript
PHP
MySQL
Visual Studio Code
WAMP Server
Git
GitHub
Android Studio
Kotlin
GitHub Actions
Team Members and Roles
| Team Member | Role | | | Keabetswe Mphago| Project Manager | | Buhle Dlamini| Back-End Developer | | Unathi Morake | Front-End Developer | | Stacey Sibuyi | UI Designer | | Jarriath Marais| QA Tester |

Running the Website Locally
The website currently uses PHP and MySQL and can be run using a local WAMP Server environment.

Requirements
WAMP Server
MySQL
PHP
Web browser
Setup
Install and start WAMP Server.
Place the project folder inside the WAMP www directory.
Create the primecare_db database in MySQL.
Use the provided database schema located in:
database/schema.sql

Configure the local database connection in:
api/config/db.php

Start Apache and MySQL through WAMP Server.
Open the project through the local WAMP address in a web browser.
The local database configuration file is excluded from the GitHub repository using .gitignore because it contains local database connection settings.

primecare-website/
│
├── api/
│   ├── appointments/
│   ├── auth/
│   ├── config/
│   ├── departments/
│   ├── notifications/
│   ├── prescriptions/
│   ├── profile/
│   └── records/
│
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
│
├── database/
│   └── schema.sql
│
├── includes/
│   ├── auth-check.php
│   ├── dash-header.php
│   ├── rate-limit.php
│   └── require-patient-login.php
│
├── appointments.php
├── book-appointment.php
├── contact.html
├── forgot-password.html
├── index.html
├── login.html
├── medical-records.php
├── patient-dashboard.php
├── prescriptions.php
├── profile.php
├── register.html
├── reset-password.html
├── services.html
└── .gitignore
