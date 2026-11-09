# UniCanteen

## Open locally

With Apache and MySQL running in XAMPP, open [http://localhost/Ordering/login_register.php](http://localhost/Ordering/) in your browser.

## Project layout

```
app/
  actions/       Request handlers and AJAX endpoints
  config/        Database configuration
  pages/         Rendered pages, grouped by visitor role
database/        SQL exports for setting up the database
images/          Static image assets
uploads/foods/   Uploaded food photos
```

The root-level PHP files are compatibility routes. Each one forwards to its
organized implementation under `app/`, preserving existing links, form
actions, AJAX requests, redirects, and bookmarked URLs.

Import `database/unicanteen.sql` to create the full database, or
`database/unicanteen_tables_only.sql` when only the schema is needed.

## Seller inventory

Each food item has a stock quantity and an availability switch. Sellers manage
these from **Menu & Inventory**; items at zero stock or marked unavailable
cannot be ordered. Existing local databases receive the inventory columns
automatically when they next connect to the app.

## Email verification and password reset

Registration and **Forgot Password** both use a six-digit code sent through
the configured Gmail SMTP account. Reset codes are hashed in the database,
expire after five minutes, accept no more than five incorrect attempts, and
can be resent after 60 seconds. A password change or administrator support
reset invalidates any active code for that account. To test password recovery,
open the login page, choose the matching Customer or Seller account type,
select **Forgot Password**, and enter the School ID or email address.

Administrators use the same code-based recovery flow from
`admin_login.php` → **Forgot Password**. The old reset-link route now returns
to the login page and is no longer a valid recovery method.

## Demo payments

The cart includes a **GCash (Sandbox demo)** option that generates a demo QR
code and saves a sandbox payment reference. It does not connect to GCash and
does not process real payments.

## Administrator setup

Admins are created manually and do not use public registration. Create a row
in the `admins` table with a PHP `password_hash()` value, then sign in at
`admin_login.php`. New seller registrations are pending until approved from
the admin dashboard; student registration remains open.

Open this in your browser:
['http://localhost/Ordering/admin_login.php']

Then enter:
Email: ['Unicanteen.isucyn@gmail.com']
Password: Admin@12345
