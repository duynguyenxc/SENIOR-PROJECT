# Veg Buffet

[![Live Demo](https://img.shields.io/badge/Live%20Demo-veg--buffet--web.onrender.com-2e7d32?style=for-the-badge)](https://veg-buffet-web.onrender.com/)
[![User Manual](https://img.shields.io/badge/User%20Manual-docs-1d4ed8?style=for-the-badge)](https://duynguyenxc.github.io/SENIOR-PROJECT/)

Veg Buffet is a web-based restaurant ordering system built as a senior project. The project focuses on a vegetarian restaurant workflow where customers can browse the weekly menu, place takeout orders, complete payment online, and check their order status, while staff and admin users manage the operational side of the system.

Live site: [https://veg-buffet-web.onrender.com/](https://veg-buffet-web.onrender.com/)

## What the project does

The system is split into two main sides.

On the customer side, users can:
- create an account and log in
- browse the weekly buffet menu
- order takeout sets
- use a custom takeout box option
- review their cart and complete checkout
- track their previous orders

On the internal side, staff and admin users can:
- monitor incoming orders
- update order status
- review order history
- manage weekly menu items
- manage takeout sets
- manage staff accounts
- view sales reports and simple analytics

## Main features

- Customer registration and login
- Separate staff and admin portal
- Weekly menu organized by day
- Takeout ordering with fixed sets and custom selection
- Stripe sandbox payment flow
- Customer order history
- Staff order queue
- Admin reporting dashboard

## User roles

### Customer
Customers use the public side of the website. They can register, log in, place orders, and check order history.

### Staff
Staff members use the internal portal to monitor orders and update statuses during the order handling process.

### Super Admin
The super admin has full internal access, including menu management, takeout management, staff account management, and reporting.

## Project structure

```text
db/init/      Database schema and seed files
web/          Main PHP application
web/admin/    Staff and admin pages
web/lib/      Shared business logic
web/assets/   CSS and JavaScript files
```

## Tech stack

- PHP 8.3
- Apache
- MariaDB / MySQL-compatible database
- Docker for local development
- Stripe Checkout (test mode)
- Render for hosting
- TiDB Cloud for the deployed database

## Notes

This project was built to be practical rather than overly complex. The focus was on making the main restaurant workflow work clearly from both the customer side and the internal management side.

The current deployed version is intended for demonstration, review, and testing. Stripe is used in test mode, and the project is hosted on a free deployment setup, so the first request after inactivity may be a little slow.

## About the database design

The database is organized into a few clear groups:

- account tables: `Admin`, `Customer`
- menu tables: `Day`, `Dish`, `DayMenuItem`
- takeout table: `TakeoutSet`
- transaction tables: `Order`, `OrderItem`, `Payment`

This structure keeps menu management, takeout ordering, and payment tracking separated in a way that is easier to maintain and explain.

