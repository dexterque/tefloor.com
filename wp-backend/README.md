# Weiyintex WordPress Backend

This folder contains a local WordPress backend for the mirrored static site.

## Local URLs

- Frontend: `http://127.0.0.1:4174/`
- Admin: `http://127.0.0.1:4174/wp-admin/`

Local admin credentials:

- User: `admin`
- Password: `admin123456`

## Run

From the repository root:

```sh
php -S 127.0.0.1:4174 -t wp-backend
```

## Seed Demo Content

```sh
php .tools/wp-cli.phar eval-file wp-backend/tools/seed-content.php --path=wp-backend
```

## Migrate Media

```sh
php .tools/wp-cli.phar eval-file wp-backend/tools/migrate-media.php --path=wp-backend
```

This registers image files under `wp-content/uploads/YYYY/MM` as WordPress Media Library attachments, generates attachment metadata, binds homepage image settings to attachment IDs, and sets product/post featured images.

The homepage is rendered by `wp-content/themes/weiyintex-static/front-page.php`.
Homepage copy, images, contact details, footer copy, and popup form labels are managed in WordPress admin under `Appearance -> Site Content`.
Products are managed in WordPress admin under `Products`; blog cards use normal Posts.

SEO defaults are also managed under `Appearance -> Site Content`.
Individual Pages, Posts, and Products can override SEO title, description, and keywords from the `SEO 配置` meta box in the editor.

The seed script also creates the main pages (`About Us`, `Blog`, `Contact Us`), dynamic products/posts, the `Main Menu`, and footer menus assigned to the theme's `Primary Menu`, `Footer Company Menu`, and `Footer Product Menu` locations. The theme rewrites mirrored static URLs to the local WordPress URL and uses absolute `/wp-content/...` asset paths so subpages keep their CSS.
