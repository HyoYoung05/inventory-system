# Setup Guide

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL or MariaDB
- XAMPP or another local PHP stack

## Project Location

Use this folder:

```text
c:\xampp\htdocs\inventory-system
```

## Base URL

Current local URL:

```env
app.baseURL = 'http://localhost:8080/'
```

Update both:
- `.env`
- `app/Config/App.php`

## Database Setup

Set your database values in `.env`:

```env
database.default.hostname = localhost
database.default.database = database_name
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
```

Run:

```bash
php spark migrate
```

If you use seeders for testing, run the ones you need.

## Run the App

Option 1: CodeIgniter local server

```bash
php spark serve
```

Open:

```text
http://localhost:8080/
```

Option 2: Apache through XAMPP

Point your server setup to the project's `public` folder if needed.

## Main Access Points

- Public browse: `/browse`
- Buyer login: `/buyer/login`
- Buyer register: `/buyer/register`
- Admin or staff login: `/login`

## Roles

### Admin
Can access:
- dashboard metrics
- revenue and sales charts
- categories
- users
- inventory management
- orders

### Staff
Can access:
- dashboard metrics
- inventory status view
- orders

### Buyer
Can access:
- dashboard
- categories
- browse
- cart
- orders
- profile

## Optional Cloudinary Setup

Use either:

```env
cloudinary.cloudName = 'your_cloud_name'
cloudinary.apiKey = 'your_api_key'
cloudinary.apiSecret = 'your_api_secret'
cloudinary.folder = 'inventory-system/products'
```

or:

```env
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
cloudinary.folder = 'inventory-system/products'
```

## Troubleshooting

### Base URL issues
- confirm `.env` is not overriding `App.php` unexpectedly
- hard refresh the browser after URL changes

### Missing tables or columns
- rerun migrations
- confirm the app is using the intended database

### Product images not showing
- check whether `image_path` is local or a Cloudinary URL
- verify uploaded files exist or the Cloudinary config is valid

### Charts not appearing
- refresh the admin dashboard after changes
- confirm there are completed orders with sales totals
