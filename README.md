# FoundIt: Lost and Found Tracking System

FoundIt is a web-based Lost and Found Management System developed for the Department of Computer, Information Sciences, and Mathematics (DCISM) at the University of San Carlos – Bunzel Building. It allows users to report lost items, view found items, and securely claim ownership through a verification process.

## About the Project

Losing personal belongings on campus is a common issue. FoundIt aims to simplify and organize the process by providing a centralized platform where users can report, track, and claim items in a more efficient and accountable manner. It addresses the limitations of manual systems by digitizing the workflow and increasing the chances of item recovery.

## Features
### User Management
- User registration for students and staff
- Secure login system
- Role-based access (Admin, Student)

### Reporting and Posting
- Lost item report submission form
- Admin approval or rejection of reports
- Logging of found items by authorized personnel
- Public feed for verified lost and found items

### Browsing and Search
- Searchable item feed
- Filters by category, location, and date

### Claiming and Verification
- Contact request form for submitting proof of ownership
- Admin matching and review process
- Interrogation notes and claim status tracking

### Lifecycle and Archiving
- Expiration of reports after 30 days
- Archiving of unclaimed items
- Disposal or donation according to institutional policy

### Admin Tools
- Dashboard for managing reports and claims
- Tagging and status management
- Email notifications for updates and claim results

## System Roles

- User – (Student, Teacher, Staff, Visitor) Can report lost items, view found items, and submit contact requests
- Admin – Reviews reports and claims, manages posts and user submissions

## Technologies Used

| Category       | Tools and Technologies             |
|----------------|------------------------------------|
| Frontend       | HTML, CSS, JavaScript              |
| Backend        | PHP                                |
| Database       | MySQL                              |
| Hosting        | Localhost (XAMPP) / Apache         |
| Email Service  | SendGrid or alternative Email API  |

## Installation Guide

1. Clone the repository or download the ZIP file.
2. Import the provided SQL file into your MySQL database.
3. Configure `config.php` with your database credentials and email API key.
4. Launch the system through your local server (e.g., `localhost/foundit`).
5. Log in using the appropriate user roles to test functionality.

## Database Overview

- `Person` – Stores personal details of users
- `User` – Contains login credentials and user roles
- `Report` – Holds data about submitted lost item reports
- `FeedPost` – Stores posts visible on the public feed
- `ContactRequest` – Used for proof of ownership claims
- `Claim` – Tracks admin-reviewed claims
- `ApprovalStatus` – Status labels for item and claim processing

## Contributors

CIS 1204 (Information Management 1)
- Zsofia Ysabel Antolijao  
- Zowee Mhey Aquino  
- Johnfranz Impas  
- Hannah Marie Martinez

CIS 2204 (Information Management 2)
- Zsofia Ysabel Antolijao   
- Johnfranz Impas  
- Hannah Marie Martinez
- Carmela Suico

## Future Plans

- Expansion to other university buildings
- Mobile-friendly interface or standalone app
- Smart matching features using photo-based recognition
- SMS and additional notification options