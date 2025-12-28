# Cosmos Salon - Setup Guide

## 🚀 Quick Setup (5 Minutes)

### Step 1: Download Files
1. Download the project files
2. Extract the ZIP file

### Step 2: Move to XAMPP
Move the **salonsystem** folder to:
- Windows: `C:\xampp\htdocs\salonsystem\`
- Mac: `/Applications/XAMPP/htdocs/salonsystem\`

### Step 3: Start XAMPP
1. Open **XAMPP Control Panel**
2. Click **Start** for **Apache**
3. Click **Start** for **MySQL**

### Step 4: Import Database
1. Go to: `http://localhost/phpmyadmin`
2. Click **"New"** → Create database named: **salonsystem**
3. Select the database → Click **"Import"** tab
4. Choose your `.sql` file → Click **"Go"**

### Step 5: Open Website
Go to: **`http://localhost/salonsystem/index.php`**

---

## 🔐 Login Credentials

**Admin Login:**
- Email: `aping060410@gmail.com`
- Password: `admin123`

---

## ⚠️ Troubleshooting

**CSS not loading?**
- Press `Ctrl + Shift + R` to refresh
- Make sure you're using: `http://localhost/salonsystem/` (not port 8000)

**404 Error?**
- Check files are in: `C:\xampp\htdocs\salonsystem\`
- Make sure Apache is running in XAMPP

**Database error?**
- Make sure MySQL is running in XAMPP
- Database name must be: **salonsystem**

---

## ✅ Done!
Your salon system is ready to use.
