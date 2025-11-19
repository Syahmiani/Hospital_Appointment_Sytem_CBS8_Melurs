# Hospital Management System

A web-based application for managing hospital appointments, built with PHP and MySQL. This system allows patients to book appointments, doctors to manage their schedules, and administrators to oversee operations.

## Features

- **User Registration and Login**: Secure authentication for patients, doctors, and administrators.
- **Appointment Booking**: Patients can easily book appointments with available doctors.
- **Dashboard Views**: Separate dashboards for admins, doctors, and patients with role-based access.
- **Doctor Listings**: Browse and select doctors by specialization.
- **Contact Form**: Send messages to the hospital with PHPMailer integration.
- **Patient Resources**: Information on pre-visit checklists, insurance, FAQs, and first aid tutorials.
- **Responsive Design**: Mobile-friendly interface with theme toggle.

## Technologies Used

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Email**: PHPMailer
- **Styling**: Custom CSS with Google Fonts (Poppins)

## Installation

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/yourusername/hospital-management-system.git
   cd hospital-management-system
   ```

2. **Install Dependencies**:
   - Ensure PHP and MySQL are installed on your system.
   - Install Composer if not already installed.
   - Run `composer install` to install PHPMailer.

3. **Database Setup**:
   - Create a MySQL database.
   - Import the `latest.sql` file to set up tables.
   - Update `config/db.php` with your database credentials.

4. **Configure Email**:
   - Update PHPMailer settings in relevant files (e.g., `message_send.php`) with your SMTP details.

5. **Run the Application**:
   - Start your web server (e.g., Apache or use PHP's built-in server).
   - Access the application at `http://localhost/yourpath/index.php`.

## Usage

- **Homepage**: View hospital information, doctors, and resources.
- **Register/Login**: Create an account or log in.
- **Book Appointment**: Select a doctor and schedule an appointment.
- **Dashboards**: Access role-specific features after logging in.
- **Contact**: Use the form to send inquiries.

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request with your changes.

## License

This project is licensed under the MIT License. See the LICENSE file for details.
