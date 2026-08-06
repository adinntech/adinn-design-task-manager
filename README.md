# Adinn Design Task Manager — Laravel

This is the first implementation of **BD Login → Create Task → Outdoor vertical**.

## Included

- Role-ready users: Admin, Designer, BD and Designer Head
- Secure BD login and role middleware
- Outdoor task natures:
  - Mock-up Requirements
  - Creative Adaptation
  - New Creative Design
  - 3D Cut-out Size Calculation
- Dynamic form fields based on task nature
- Client / Agency manual details
- One assigned designer
- Total creative count
- Feet + inches board-size entry and automatic square-foot calculation
- Multiple supporting uploads, ZIP/open-artwork upload and client audio
- Auto task ID such as `DT-2026-00001`
- SQLite database for easy first setup

## Windows setup

Install PHP 8.3+, Composer, Node.js and Git. Then run in PowerShell:

```powershell
cd "D:\Projects\adinn-design-task-manager-laravel"
Copy-Item .env.example .env
composer install
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open `http://127.0.0.1:8000`.

Demo BD login:

- Email: `bd@adinn.com`
- Password: `Password@123`

## macOS / Linux setup

```bash
cp .env.example .env
composer install
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

## Important upload limits

Laravel validates application-level sizes, but PHP also has server limits. For large artwork files, update `php.ini`:

```ini
upload_max_filesize = 120M
post_max_size = 150M
max_file_uploads = 30
```

Restart PHP or the web server after changing these values.
