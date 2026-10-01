Tindahan ni Aling Jorhina

Web-based art supplies store system in PHP and MySQL.

Setup on XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Put this folder in C:\xampp\htdocs\ (keep the folder name tnaj-art-shop).
3. In phpMyAdmin (http://localhost/phpmyadmin), open the Import tab and import db/tnajart.sql. This creates the database tnajart.
4. Open http://localhost/tnaj-art-shop/

Logins

- Admin: admin@shop.com / admin123
- Demo suki customer: suki@shop.com / suki123

Change or delete these before real use.

Notes

- Database settings are at the top of includes/config.php (default: localhost, root, no password, tnajart).
- Product photos are saved in uploads/. The folder is kept in Git, its contents are not.
- Importing db/tnajart.sql again wipes all data and starts fresh.

How a sale is saved

Every checkout and walk-in sale runs as one database transaction: the order, its lines, the stock deduction, the suki list entry (if paying sa lista), and loyalty points are saved together. If any step fails, everything rolls back, so stock and records never disagree. Canceling an order reverses all of it the same way.
