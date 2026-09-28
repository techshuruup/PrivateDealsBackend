# PrivateDeals V1

## Documentation (new system)

**Start with one file only:**

- **[docs/new-system/START-HERE.md](docs/new-system/START-HERE.md)** — whole story

**Diagrams:**

- **[Transaction diagrams](docs/new-system/business-diagrams/transaction/README.md)** — Buy mandate → complete
- **[Business diagrams](docs/new-system/business-diagrams/README.md)** — Use cases, swimlanes A–F, permissions, PlantUML
- [START-HERE](docs/new-system/START-HERE.md)

## Project Setup Guide

### Steps to Check `php.ini`

1. Ensure you have PHP version **8.3** installed.
2. Modify the following values in your `php.ini` file:
   - Change `upload_max_filesize = 50M` to the desired value.
   - Change `memory_limit = 512M` to the desired value.
   - Change `max_input_time = -1` to the desired value.
   - Change `max_execution_time = 0` to the desired value.
   - Change `post_max_size = 6G` to the desired value.

### Steps to Check Apache `vhost` File

1. Change the Apache `:80` port to point to your project's public folder.
2. If you want to use SSL on port `:443`, follow [this guide to set up SSL](https://docs.google.com/document/d/1M4RE8JUZfDbot3Wrj4GNWL6_rf9C7aRLRltiFG8oQbY/edit?usp=sharing).

### Steps to Set Up Laravel

1. Ensure you have PHP version **8.3** installed.
2. Run the command: `cp .env.example .env` to generate the environment file.
3. Set up environment variables in the `.env` file.
4. Go to `app/Providers/SettingsServiceProvider.php` and comment out the code inside the `boot` and `register` functions.
5. Run `composer install` to install vendor libraries.
6. Set the `FILESYSTEM_DISK` environment variable to either `s3` or `local`. If `s3`, set up the following variables:

   AWS_ACCESS_KEY_ID="AKIATKK5Y6KLKT77IG5G"
   AWS_SECRET_ACCESS_KEY="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"
   AWS_DEFAULT_REGION="ap-south-1"
   AWS_BUCKET="shuruup-v4-beta"
   AWS_USE_PATH_STYLE_ENDPOINT=false

   # Change this to https if live

   AWS_SCHEME="http"

   Change `AWS_SCHEME` to `https` if using SSL, otherwise set it to `http`.

7. Run the command: `php artisan key:generate` to generate the private key.
8. Run the command: `php artisan optimize` to refresh cache files.
9. Run the command: `php artisan migrate` to create tables in the database.
10. Go back to `app/Providers/SettingsServiceProvider.php` and uncomment the code inside the `boot` and `register` functions.

### Default Superadmin Login

- **Username:** private-deals
- **Password:** PrivateDeals@123
