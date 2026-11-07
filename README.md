# classroombookings - open source booking system designed for schools.
[![License: AGPLv3](https://img.shields.io/static/v1?label=License&message=AGPLv3&color=3DA639&style=flat-square)](https://www.gnu.org/licenses/agpl-3.0.html)

This is a web-based system that allows users to book resources such as rooms or equipment. Set up your bookable resources, day schedule and timetable for the year. Add user accounts, and allow them to make and manage bookings from anywhere.

Key features:
- User login and automatic provisioning with LDAP or Microsoft Entra SSO
- Granular permissions system with sensible defaults
- Ability to create one-time or recurring bookings
- Multiple users can book a resource within the same time slot if it has sufficient capacity
- Support for alternating timetable weeks

## Install
Requirements:
- Web server with PHP 8.4+ with modules: mysql/mysqli/pdo_mysql, intl, gd
- MySQL or MariaDB database

Install steps:
1. Upload the app files to your web server's document root 
2. Allow the user account which runs your webserver permission to write to the "local" and "uploads" folder

```
chmod -R 775 local
chmod -R 775 uploads
```
3. Create database and database user
```
CREATE DATABASE crbs_db;
GRANT ALL ON crbs_db.* TO 'crbs_user' IDENTIFIED BY 'ReplaceMe8zFQooA8z';
```
4. Visit the IP address or URL of your server, this should load the web based installer where you will configure the app and database connection

## Important information
This is a fork of [classroombookings by Craig A Rodway](https://www.classroombookings.com) which adds features such as Microsoft SSO, shared booking slots, light/dark modes and other modern UI changes.


## Credits

This project makes use of several third parties, some of which are listed below.

- [CodeIgniter](https://codeigniter.com/) (MIT)
- [Unpoly](https://unpoly.com/) (MIT)
- [FamFamFam Silk Icons](http://www.famfamfam.com/lab/icons/silk/) ([CC BY 3.0](https://creativecommons.org/licenses/by/3.0/), Unmodified)
