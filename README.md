<h1>PrimeCare Medical Centre</h1>

**Clinic Queue and Patient Record Management System**<br>
Developed by **Tech Titans**.<br>

<h2>Team Members and Roles</h2><br>

 Keabetswe Mphago - Project Manager <br>
 Buhle Dlamini - Back-End Developer <br>
 Unathi Morake - Front-End Developer <br>
 Stacey Sibuyi - UI/UX Designer <br>
 Jarriath Marais - QA Tester <br>

<h2>Project Description</h2>

PrimeCare is a healthcare management solution designed to improve the way patients and clinic staff manage appointments, patient information and medical services.<br>
<br>
The project consists of a patient-facing website and an Android mobile application for clinic staff. The website helps patients manage their appointments and healthcare information, while the application supports administrators and doctors through role-based dashboards.<br>
<br>
The system aims to reduce manual administration, improve patient flow and provide a more convenient experience for patients and clinic staff.<br>

<h2>Client Problem</h2>

PrimeCare Medical Centre relies on manual, paper-based processes for patient registration, appointment scheduling and medical records. These can cause long waiting times, duplicate or misplaced records and difficulties coordinating care.<br>
<br>
The solution provides digital appointment booking, patient information management, queue management and secure medical record access.<br>

<h2>Website Scope</h2>

The patient-facing website includes the following planned or documented functions:<br>
- Viewing information about the medical centre<br>
- Viewing healthcare services and doctor information<br>
- Creating a patient account and logging in<br>
- Password recovery and reset<br>
- Booking, rescheduling and cancelling appointments<br>
- Viewing upcoming appointments and appointment history<br>
- Viewing medical records and prescriptions<br>
- Requesting prescription repeats<br>
- Updating personal profile information<br>
- Viewing notifications and contacting the medical centre<br>
<br>
Patients must log in to access account-specific functionality.<br>

<h2>Mobile Application Scope</h2>

The Android application is designed for authorised clinic staff, with role-based access.<br>

<h3>Administrator Dashboard</h3>

- View and manage patient appointments and daily schedules<br>
- Register and check in walk-in patients<br>
- Generate or manage queue numbers<br>
- Assign patients to available doctors<br>
- Update patient queue statuses<br>
- Manage users and clinic operations where authorised<br>

<h3>Doctor Dashboard</h3>

- View daily schedules and assigned patients<br>
- Access relevant patient histories and consultation information<br>
- Record consultation notes and diagnoses<br>
- Update medical records<br>
- Issue prescriptions<br>
- Mark consultations as completed or no-show<br>

<h3>Nursing Workflow</h3>

The project documentation also describes nursing responsibilities, including viewing patient queues, recording vital signs and adding preliminary patient notes. Availability of a separate nursing dashboard should be confirmed against the final application.<br>

<h2>Queue Management</h2>

Clinic staff can track patient progress using statuses such as:<br>
- Checked-in<br>
- In consultation<br>
- Awaiting test results<br>
- Completed<br>
<br>
The design also calls for real-time updates and notifications about appointment changes and urgent tasks.<br>

<h2>Technologies and Tools</h2>

- HTML5, CSS3 and JavaScript — website interface<br>
- PHP — server-side logic and APIs<br>
- MySQL — relational database<br>
- Visual Studio Code — development environment<br>
- WAMP Server — local PHP/MySQL development<br>
- Android Studio and Kotlin — Android application development<br>
- Git and GitHub — version control and collaboration<br>
- GitHub Actions — development workflow automation<br>
- Azure DevOps - version control and work items <br>
<br>
These technologies reflect the project description; exact versions and workflow configuration should be checked in the source repositories.<br>

<h2>System Architecture</h2>

The documented design follows a three-tier structure:<br>
- **Presentation layer:** Patient website and staff mobile application<br>
- **Business logic layer:** PHP backend for appointments, queues, authentication and record handling<br>
- **Data layer:** MySQL database for patient, doctor and appointment information<br>

<h2>Running the Website Locally</h2>

<h3>Requirements</h3>

- WAMP Server with PHP and MySQL<br>
- A modern web browser<br>
- The website project files and database schema<br>

<h3>Setup</h3>

1. Install and start WAMP Server.<br>
2. Copy the website project folder into the WAMP `www` directory.<br>
3. Create a MySQL database named `primecare_db`.<br>
4. Import the database schema from `database/schema.sql` (if this file is present in your repository).<br>
5. Configure the database credentials in `api/config/db.php` (if this is the connection file in your project).<br>
6. Open `http://localhost/<project-folder>/` in your browser, replacing `<project-folder>` with the actual folder name.<br>
<br>
**Note:** Database paths, credentials and the website entry point must be verified against the final source code. Do not commit real passwords or sensitive patient information.<br>

<h2>Running the Android Application</h2>

1. Open the Android project in Android Studio.<br>
2. Allow Gradle to synchronise and install required dependencies.<br>
3. Configure the application API endpoint to point to the running backend.<br>
4. Run the application on an Android emulator or connected Android device.<br>
<br>
**Note:** The final Android project location, minimum Android version and exact API configuration are not specified in the supplied documentation.<br>

<h2>Security and Privacy</h2>

The system requirements include authentication, role-based access control, protection of patient records and compliance with South Africa's Protection of Personal Information Act (POPIA). The documentation calls for encryption of sensitive data in transit and at rest. These are project requirements and should be verified in the deployed implementation.<br>

<h2>Testing and Deployment</h2>

The project plan includes testing website navigation, forms, authentication, database interactions, appointment functions and role restrictions. Planned deliverables include a deployed website, Android APK, test documentation and user manual.<br>
<br>
**Website URL:** Website is a downloadable file in repository.<br>
**Android APK:** APK is a downloadable file in repository.<br>
**GitHub repository:** (https://github.com/Tshegofatsoo/primecare-project)<br>
**Azure DevOps Repository:** (https://dev.azure.com/ST10457058/_git/Prime%20Care%20Medical%20Center)</br>
**Youtube Demonstration Video Link:**(https://youtu.be/e0TeuPSbWnw)</br>
<br>
**Running the Website Locally**
The website currently uses PHP and MySQL and can be run using a local WAMP Server environment.<br>

**Requirements**<br>
-WAMP Server<br>
-MySQL<br>
-PHP<br>
-Web browser<br>

**Setup**
-Install and start WAMP Server.<br>
-Place the project folder inside the WAMP www directory.<br>
-Create the primecare_db database in MySQL.<br>
-Use the provided database schema located in: database/schema.sql<br>
-Configure the local database connection in: api/config/db.php<br>

**Start Apache and MySQL through WAMP Server.**<br>
Open the project through the local WAMP address in a web browser.<br>
The local database configuration file is excluded from the GitHub repository using .gitignore because it contains local database connection settings.<br>


