# Remember

<p align="center">
  <strong>A private, self-hosted family timeline for preserving the moments that matter.</strong>
</p>

<p align="center">
  Remember brings memories, milestones, events, photographs and private letters together in one shared family archive. It is built for small, invitation-only groups and wrapped in a colourful neo-brutalist interface that works across desktop and mobile.
</p>

<p align="center">
  <img src="public/images/examples/initial-timeline.png" alt="The Remember family timeline" width="100%">
</p>

## What Remember offers

- A chronological family timeline with memories, milestones, events and private letters
- Image and file attachments with automatic WebP image optimisation
- Comments and hearts on shared posts
- Author-only private letters
- Search and filtering by post type, family member and hashtag
- A gallery with full-size image previews and downloads
- Invitation-only account registration
- A one-time browser setup flow for naming the timeline and creating its first account
- Responsive desktop and mobile navigation
- Installable web-app metadata and safe-area-aware mobile styling

## A look around

<table>
  <tr>
    <td width="50%">
      <img src="public/images/examples/setup.png" alt="Remember first-time setup screen">
      <br><strong>First-time setup</strong><br>
      Name the timeline and create the first account from one guided screen.
    </td>
    <td width="50%">
      <img src="public/images/examples/preserve-memory.png" alt="Preserve a new memory form">
      <br><strong>Preserve a memory</strong><br>
      Add stories, dates, hashtags and media without leaving the timeline.
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img src="public/images/examples/milestone-shared-with-comment.png" alt="A milestone with a family comment">
      <br><strong>Share the moment</strong><br>
      Family members can respond to shared posts with comments and hearts.
    </td>
    <td width="50%">
      <img src="public/images/examples/searching-the-timeline.png" alt="Searching and filtering the timeline">
      <br><strong>Find it again</strong><br>
      Search the archive and narrow it by type, tag or author.
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <img src="public/images/examples/register-invite-screen.png" alt="Invitation-only registration screen">
      <br><strong>Keep the timeline private</strong><br>
      New family members join through single-use invitation links generated from the application console.
    </td>
  </tr>
</table>

## Technology

- PHP 8.4 and Laravel 13
- Laravel Fortify for authentication
- Inertia.js 3 and Vue 3
- Tailwind CSS 4
- Laravel Wayfinder for typed frontend routes
- MySQL for application data
- Redis for tagged timeline caching
- Intervention Image for media optimisation
- Google Cloud Storage or a Laravel filesystem disk for uploaded media

## Requirements

Before installing Remember, make sure the host has:

- PHP 8.4
- Composer 2
- MySQL 8 or a compatible MySQL/MariaDB server
- Redis
- The PHP Redis extension (`phpredis`), unless the application is adapted to use another supported Redis client
- A PHP image extension supported by Intervention Image, such as GD or Imagick
- Node.js 22 LTS and npm
- A web server such as Nginx, Apache or Laravel's development server
- Writable `storage` and `bootstrap/cache` directories

For production use, SMTP details are also recommended so password-reset and account-related mail can be delivered.

> [!IMPORTANT]
> Remember uses Laravel cache tags for timeline caching and cache invalidation. The database, file and array cache drivers do **not** support cache tags. Redis must be running and `CACHE_STORE=redis` must remain configured, otherwise timeline requests and post updates will fail.

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url> remember
cd remember
composer install
npm install
```

### 2. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

Set the application URL and database credentials in `.env`:

```dotenv
APP_NAME="Remember"
APP_URL=https://remember.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=remember
DB_USERNAME=remember
DB_PASSWORD=your-database-password
```

If the local site is served over plain HTTP, set the secure-cookie option to false during local development:

```dotenv
SESSION_SECURE_COOKIE=false
```

### 3. Configure Redis

Redis is required by the application, even when the database queue driver is used.

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

The hostnames in `.env.example` (`mysql` and `redis`) are suitable for a container network where those services use those names. For services running directly on the host, use `127.0.0.1` instead.

Once configured, this command provides a quick connection check:

```bash
php artisan cache:clear
```

### 4. Configure media storage

Remember stores uploaded files on the disk selected by `MEDIA_DISK`. Google Cloud Storage is the default production-oriented option.

For Google Cloud Storage:

```dotenv
FILESYSTEM_DISK=gcs
MEDIA_DISK=gcs
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_STORAGE_BUCKET=your-private-bucket
GOOGLE_CLOUD_KEY_FILE=/absolute/path/to/service-account.json
```

Alternatively, a local installation can use Laravel's public disk:

```dotenv
FILESYSTEM_DISK=public
MEDIA_DISK=public
```

When using the public disk, create Laravel's conventional storage link:

```bash
php artisan storage:link
```

Verify that the selected media disk can write, read and delete files:

```bash
php artisan media:verify-storage
```

### 5. Create the database and frontend build

```bash
php artisan migrate
npm run build
```

The default session and queue drivers use the database. Their required tables are included in the migrations.

### 6. Start the application

For local development, the Composer development command starts Laravel, the queue listener, log viewer and Vite together:

```bash
composer run dev
```

You can also run the processes separately:

```bash
php artisan serve
npm run dev
php artisan queue:work
```

### 7. Create the timeline

Open `/setup` in the browser—for example, `https://remember.test/setup`—and provide:

- The timeline name
- An optional short timeline description
- The first user's name, username and email address
- A secure password

The timeline and first account are created together, and the first user is signed in immediately. After setup succeeds, both setup endpoints return a 404 and cannot be used again.

## Inviting family members

Registration is invitation-only. Create a single-use invitation URL with:

```bash
php artisan users:create family-member
```

The command prints a registration URL. `APP_URL` must be correct before generating links. Each invitation reserves its username and is invalidated after use.

## Development checks

Run the backend test suite:

```bash
php artisan test --compact
```

Check and format the frontend:

```bash
npm run types:check
npm run lint:check
npm run format:check
```

Build production assets:

```bash
npm run build
```

Format PHP changes with Laravel Pint:

```bash
vendor/bin/pint --format agent
```

## Production notes

- Serve the application over HTTPS and keep `SESSION_SECURE_COOKIE=true`.
- Keep Redis supervised and persistent enough for the desired cache behaviour.
- Run a queue worker under a process supervisor when using an asynchronous queue connection.
- Set `APP_DEBUG=false` and configure production logging and mail delivery.
- Configure the web server document root as the project's `public` directory.
- Ensure uploaded family media is stored privately and backed up appropriately.
- Run `php artisan optimize` after deploying configuration and application changes.

## Privacy

Remember is intended for private family content. Authentication protects the timeline, account creation is invitation-only, and letters are visible only to their author. Deployment security, backups, access controls and storage privacy remain the responsibility of the person hosting the application.

## License

Remember is open-source software released under the MIT license.
