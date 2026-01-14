# BitBlog - Lightweight PHP Blog System

**A lightweight, file-based blog system written in PHP with built-in admin interface.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)

## ✨ Features

- 📝 **File-based**: No database required - posts stored as Markdown files
- 🎨 **Built-in Editor**: Web-based admin interface for writing posts
- 🌍 **Multilingual**: Built-in i18n support (English, German)
- 🏷️ **Tag System**: Organize posts with tags and tag clouds
- 📱 **Responsive**: Mobile-friendly design
- 🔒 **Secure**: CSRF protection, bcrypt password hashing, session management
- 🚀 **Fast**: Cached index for quick page loads
- 📡 **RSS Feed**: Automatic RSS generation
- 🗺️ **Sitemap**: SEO-friendly XML sitemap
- 🔐 **Private Posts**: Share drafts via secure token URLs
- ⏱️ **Reading Time**: Automatic calculation with manual override option
- 🔗 **Share Button**: Native Web Share API with clipboard fallback

<br />
<br />
<br />
<img width="2496" height="1489" alt="blog" src="https://github.com/user-attachments/assets/40cb1a14-9fe0-4ddb-84b6-e155cb02ca09" />
<br />
<br />
<img width="2496" height="1486" alt="search" src="https://github.com/user-attachments/assets/20afc2ad-1adc-46a8-8505-691f28315d56" />
<br />
<br />
<img width="2496" height="1489" alt="admin" src="https://github.com/user-attachments/assets/39164e53-7c67-4c27-b986-b50fbdedd883" />
<br />
<br />
<img width="2496" height="1490" alt="editor" src="https://github.com/user-attachments/assets/0f0633fb-53ea-42f1-8665-4c478cbe7931" />  
<br />
<br />
<img width="2496" height="1485" alt="signature-editor" src="https://github.com/user-attachments/assets/cff633e3-8520-4442-bea7-47fa445d17d0" />  
<br />
<br />
<img width="2496" height="1486" alt="settings" src="https://github.com/user-attachments/assets/965293ac-2dc3-472e-8624-023963d76905" />  
<br />
<br />
<img width="2496" height="1481" alt="archive" src="https://github.com/user-attachments/assets/842cb42d-857f-4f74-ba36-4095836be9a9" />
<br />
<br />
<img width="2496" height="1485" alt="index3" src="https://github.com/user-attachments/assets/c87f74d0-f949-430d-a259-3fd419a19717" />  
<br />
<br />
<img width="2496" height="1483" alt="index2" src="https://github.com/user-attachments/assets/2f8984e9-d778-4eeb-a544-780276de6fb5" />
<br />
<br />
<br />

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher (tested with PHP 8.4)
- Web server with PHP support

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/jurgen178/bitblog.git
   cd bitblog
   ```

2. **Set up admin credentials:**
   
   First time setup - visit `http://localhost:8000/admin.php` and the setup wizard will guide you through creating an admin account.
   
   OR manually edit `src/Config.php`:
   ```php
   public const ADMIN_USER = 'yourusername';
   public const ADMIN_PASSWORD_HASH = '$2y$12$...'; // Use generate_password.php
   ```

3. **Start the development server (local testing):**
   ```bash
   cd bitblog
   php -S localhost:8000
   ```

4. **Access your blog:**
   - Homepage: http://localhost:8000
   - Admin Panel: http://localhost:8000/admin.php

### Production Deployment

#### Linux/Apache

1. **Upload files to your web server:**
   - Copy all files to your web server's document root
   - Ensure the web server user (e.g., `www-data`, `apache`) has write permissions for:
     - The entire BitBlog directory (for generating index files, static pages, etc.)
     - `cache/` directory (for generated cache files)
     - `content/` directory (for creating/editing posts)
     - `settings.json` (for saving configuration)

2. **Set permissions:**
   ```bash
   cd /path/to/bitblog
   chmod -R 775 .
   chown -R www-data:www-data .
   ```
   
   **Note:** The `archive/` directory will be created automatically when you create your first archive.

3. **Configure Apache:**
   - Point document root to the BitBlog directory
   - Enable `.htaccess` if using URL rewriting
   - Ensure PHP is enabled and version 8.0+ is available

4. **Security:**
   - Set up admin credentials in `src/Config.php`
   - Use HTTPS in production
   - Consider restricting `/admin.php` access by IP if possible

#### Windows/IIS

1. **Copy files to IIS:**
   - Copy all files to your IIS website directory (e.g., `C:\inetpub\wwwroot\bitblog`)
   - Ensure the IIS application pool identity (e.g., `IIS_IUSRS`, `NETWORK SERVICE`) has write permissions for:
     - The entire BitBlog directory (for generating index files, static pages, etc.)
     - All subdirectories and files

2. **Set permissions (using PowerShell):**
   ```powershell
   # Grant write access to entire BitBlog directory
   icacls "C:\inetpub\wwwroot\bitblog" /grant "IIS_IUSRS:(OI)(CI)M" /T
   ```
   
   **Note:** The `archive\` directory will be created automatically when you create your first archive.

3. **Configure IIS:**
   - Install PHP using Web Platform Installer or manually
   - Enable PHP FastCGI in IIS
   - Set default document to `index.php`
   - Configure `web.config` for URL rewriting (already included in repo)

4. **Security:**
   - Set up admin credentials in `src\Config.php`
   - Enable HTTPS binding in IIS
   - Consider IP restrictions for `/admin.php` in IIS Manager

## 📂 Project Structure

```
blog-test/
├── index.php              # Main blog frontend
├── admin.php              # Admin interface router
├── src/                   # Application classes
│   ├── Config.php         # Site configuration
│   ├── Content.php        # Content management
│   ├── Router.php         # URL routing
│   └── ...
├── admin/                 # Admin interface files
│   ├── login.php          # Login form
│   ├── editor.php         # Post editor
│   ├── archive.php        # Archive management
│   └── admin.css          # Admin styles
├── templates/             # HTML templates and CSS
├── content/               # Your content
│   ├── posts/             # Blog posts (.md files)
│   └── pages/             # Static pages
├── archive/               # Generated archives and backups
└── cache/                 # Generated cache files
```

## 📝 URL Structure

- **Homepage**: `/` or `/index.php`
- **Single Post**: `/index.php?id=123`
- **Tag Archive**: `/index.php?tag=php`
- **Page**: `/index.php?page=about`
- **RSS Feed**: `/feed.xml` or `/index.php?feed=1`
- **Sitemap**: `/sitemap.xml`
- **Admin Panel**: `/admin.php`

## 🔧 Configuration

Edit `src/Config.php` to customize defaults:

```php
public const SITE_TITLE = 'Your Blog Name';
public const POSTS_PER_PAGE = 5;
public const RSS_POSTS_LIMIT = 100;
public const TIMEZONE = 'Europe/Berlin';
public const DEFAULT_LANGUAGE = 'en'; // or 'de'
```

Runtime settings can also be changed via the admin panel (`/admin.php?settings`).

## 🛡️ Security

- **Password Hashing**: Uses bcrypt (cost 12) for secure password storage
- **CSRF Protection**: All forms protected against CSRF attacks
- **Session Security**: Secure session management with httponly cookies
- **Input Validation**: All user inputs are sanitized and validated
- **Private Posts**: Secure token-based access for draft sharing

**⚠️ Important Security Notes:**
- Change admin credentials before production deployment
- Use HTTPS in production
- Keep PHP updated

## 🌍 Internationalization

BitBlog supports multiple languages out of the box:
- English (en)
- German (de)

To add a new language:
1. Copy `src/lang/template.json` to `src/lang/xx.json` (e.g., `fr.json`)
2. Translate all strings
3. Set `_locale` field (e.g., `"_locale": "fr_FR"`)

See [TRANSLATION_GUIDE.md](TRANSLATION_GUIDE.md) for details.

## 📖 Writing Posts

### Using the Web Editor (Recommended)

1. **Access the admin panel:**
   - Navigate to `http://yoursite.com/admin.php`
   - Log in with your credentials

2. **Create a new post:**
   - Click "📝 New Post" in the admin panel
   - Fill in the title, date, and status (draft/published/private)
   - Add tags to organize your content
   - Write your content using the built-in Monaco Editor (Visual Studio Code editor)
   - See live preview of your post in real-time

3. **Editor features:**
   - **Markdown toolbar**: Quick buttons for bold, italic, code, links, tables
   - **Live preview**: See rendered output as you type
   - **Fullscreen mode**: Distraction-free writing (F11)
   - **Dark/Light theme**: Switch editor theme
   - **Font size control**: Adjust text size (A+/A-)
   - **Keyboard shortcuts**: Ctrl+B (bold), Ctrl+I (italic), Ctrl+K (link), Ctrl+S (save)

4. **Post status:**
   - **Published**: Visible to everyone
   - **Draft**: Hidden from public, only visible in admin
   - **Private**: Only accessible via secure token URL (perfect for sharing drafts)

### Manual File Creation

Posts are Markdown files with YAML front matter stored in `content/posts/`:

```markdown
---
title: My Blog Post
status: published
tags: [php, blog, web]
reading_time: 5  # Optional: Manual override (in minutes). Omit for auto-calculation.
---

# My Blog Post

Content goes here in **Markdown** format.
```

**File naming convention:** `YYYY-MM-DDTHHMM.ID.md` (e.g., `2025-11-15T1430.123.md`)
- The date in the filename is in UTC
- The ID is auto-generated and unique

## 🎯 Admin Features

- **📝 Editor**: Write posts in Markdown with live preview powered by Visual Studio Code
- **📊 Dashboard**: Overview of all posts with status
- **🔄 Index Rebuild**: Regenerate cache after bulk changes
- **📦 Archive Management**: Create, download, restore, and delete blog archives
- **🗑️ Delete Posts**: Remove posts with confirmation
- **👀 Preview**: View posts before publishing

### Archive & Migration

BitBlog includes a comprehensive archive management system at `/admin.php?action=archive`:

**1. Create Archive:**
- Click "🔄 Create Archive Now" to create a ZIP archive
- No index rebuild required - just packages current content
- Archives are timestamped: `blog-content-YYYY-MM-DD_HHmmss.zip`
- All archives appear in the Archive History table below

**2. Upload Archive:**
- Drag & drop or select a ZIP file (max 5 MB)
- Automatic validation (must contain `posts/` and `pages/` folders with Markdown files)
- Current content is automatically saved as archive before extraction
- Index is automatically rebuilt after upload
- Perfect for migration between BitBlog instances

**3. Archive History:**
All archives are managed in one table with three actions per archive:
- **⬇️ Download**: Download the archive ZIP file
- **↩️ Restore**: Replace current content with this archive (creates automatic backup first)
- **🗑️ Delete**: Remove archive from server

**Archive Types:**
- **🔄 Auto Archive** (`archive-*.zip`): Created automatically before upload or restore
- **📦 Manual Archive** (`blog-content-*.zip`): Created via "Create Archive Now" button

**Security Features:**
- Maximum file size: 5 MB
- Validates ZIP structure (prevents path traversal)
- Only allows Markdown files in `posts/` and `pages/`
- Automatic backup before any restore operation
- CSRF protection on all actions

**Migration Workflow:**
1. Source blog: Create archive
2. Download the ZIP file
3. Target blog: Upload archive
4. Done! All posts and pages migrated

## 📱 Responsive Design

- Mobile-friendly interface
- Tag cloud for easy navigation
- Pagination for large blogs
- Clean, readable typography

## 🔍 SEO Features

- Automatic sitemap generation
- RSS feed for subscribers
- Clean URLs
- Meta tags and descriptions
- Performance optimization with caching

## 🚦 Post Status

- **`published`**: Visible on the blog
- **`draft`**: Hidden from public view
- **`private`**: Accessible only via direct link
- **`idea`**: Hidden from public view, accessible only via name link

## 📋 Requirements

- PHP 8.0+ (tested with PHP 8.4)
- No database required
- Web server with PHP support

## 🎨 Customization

- Edit templates in `/templates/` for layout changes
- Modify CSS in `/templates/style.css` for styling
- Admin interface styles in `/admin/admin.css`

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with [Parsedown](https://github.com/erusev/parsedown) and [ParsedownExtra](https://github.com/erusev/parsedown-extra) for Markdown processing

---

**BitBlog** - Lightweight, secure, and fast!  
See live blog [https://www.bitfabrik.io/blog/](https://www.bitfabrik.io/blog/)

