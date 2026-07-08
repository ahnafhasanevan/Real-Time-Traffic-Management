# Real-Time Traffic Management System

A web-based traffic management platform built with PHP, MySQL, Bootstrap, and JavaScript. The system allows users to view traffic information, report incidents, manage vehicles, access penalties, plan routes, and monitor live traffic conditions. It also provides administrative tools for managing users, sensors, events, emergency vehicles, and reports.

## Project Overview

This project is designed to help cities or organizations monitor and manage road traffic more efficiently. It combines traffic data, weather information, user reporting, and administrative controls in a single dashboard-style web application.

## Main Features

- User authentication and account creation
- Admin and traffic-manager dashboards
- Live traffic data monitoring
- Route planning assistance
- Weather dashboard integration
- Incident reporting and report review
- Vehicle registration and management
- Penalty and violation tracking
- Notifications and system logs
- Sensor and event management
- Emergency vehicle monitoring

## Technologies Used

- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript
- Bootstrap 5
- Font Awesome
- MapTiler API for mapping and geocoding

## Project Structure

- index.php – main user dashboard
- admin_dashboard.php – administrator dashboard
- login.php – login page
- create_account.php – user registration page
- config.php – database and session configuration
- auth.php – authentication logic
- navbar.php – shared navigation bar
- traffic_data.php – live traffic page
- route_planner.php – route planning interface
- weather_dashboard.php – weather monitoring page
- report_incident.php – incident reporting form
- view_reports.php – user report history
- manage_sensors.php – sensor administration
- manage_events.php – traffic event management
- emergency_vehicles.php – emergency vehicle tracking
- penalties.php / issue_penalty.php – fine and violation management
- user_management.php – user administration
- notifications.php – user notifications
- analytics.php – analytics and reporting views

## Prerequisites

Before running the project, make sure you have the following installed:

- XAMPP, WAMP, or any local PHP + MySQL server
- PHP 7.4 or newer
- MySQL database server
- A web browser

## Installation and Setup

1. Place the project folder inside your local web server directory.
   - For XAMPP, place it in: htdocs

2. Start Apache and MySQL from your local server control panel.

3. Create a MySQL database named:
   - real_t_traffic_m

4. Update the database settings in config.php if needed:
   - DB_HOST
   - DB_USER
   - DB_PASS
   - DB_NAME

5. Update the MapTiler configuration in maptiler_config.php with your own API key if required.

6. Open the project in your browser:
   - http://localhost/Real-Time-Traffic-Management

## Default Login Flow

- New users can create an account from create_account.php
- Existing users can log in from login.php
- Admin users are redirected to admin_dashboard.php after login
- Regular users are redirected to index.php

## Notes

- The project expects the database tables referenced throughout the PHP files to be present.
- The database credentials in config.php are currently set to the common local development values:
  - Username: root
  - Password: empty
- If you are using a different local server setup, update those values accordingly.
- The MapTiler API key is currently stored in maptiler_config.php and may need to be replaced for full map functionality.

## Recommended Development Setup

For local development, a package such as XAMPP or WAMP is recommended because the project depends on:

- Apache web server
- PHP runtime
- MySQL database

## Future Improvements

Possible enhancements for the project include:

- Real-time GPS integration
- SMS/email notifications
- Advanced charts and analytics
- Mobile-friendly improvements
- Admin audit trails and reporting exports
- AI-based traffic prediction

## License

This project is intended for educational and academic use. Please ensure you comply with any applicable licensing terms for third-party libraries and APIs used in the project.
