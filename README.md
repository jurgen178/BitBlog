# BitBlog - Simple PHP Blog System

**A lightweight, file-based blog system written in PHP with built-in admin interface.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)

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

<img width="2496" height="1488" alt="blog" src="https://github.com/user-attachments/assets/cf458ad6-90e1-4c07-a8c1-26185739e2d2" />  

<img width="2496" height="1489" alt="admin" src="https://github.com/user-attachments/assets/9cc7a2f7-b693-46b5-a744-fb47f522010f" />  

<img width="2496" height="1485" alt="index3" src="https://github.com/user-attachments/assets/c87f74d0-f949-430d-a259-3fd419a19717" />  

<img width="2496" height="1483" alt="index2" src="https://github.com/user-attachments/assets/2f8984e9-d778-4eeb-a544-780276de6fb5" />

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher (tested with PHP 8.4)
- Web server with PHP support (Apache, Nginx, or PHP built-in server)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/bitblog.git
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

3. **Configure Apache:**
   - Point document root to the BitBlog directory
   - Enable `.htaccess` if using URL rewriting
   - Ensure PHP is enabled and version 7.4+ is available

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
│   └── admin.css          # Admin styles
├── templates/             # HTML templates
├── content/               # Your content
│   ├── posts/             # Blog posts (.md files)
│   └── pages/             # Static pages
├── cache/                 # Generated cache files
└── assets/                # CSS, images, etc.
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
- Never commit `settings.json` with sensitive data
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

Posts are Markdown files with YAML front matter:

```markdown
---
title: My Blog Post
date: 2024-11-09T14:30:00Z
status: published
tags: [php, blog, web]
summary: A short description of the post
---

# My Blog Post

Content goes here in **Markdown** format.
```

## 🎯 Admin Features

- **📝 Editor**: Write posts in Markdown with live preview powered by Visual Studio Code
- **📊 Dashboard**: Overview of all posts with status
- **🔄 Index Rebuild**: Regenerate cache after bulk changes
- **🗑️ Delete Posts**: Remove posts with confirmation
- **👀 Preview**: View posts before publishing

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

## 📋 Requirements

- PHP 7.4+ (tested with PHP 8.4)
- No database required
- Web server with PHP support

## 🎨 Customization

- Edit templates in `/templates/` for layout changes
- Modify CSS in `/assets/style.css` for styling
- Admin interface styles in `/admin/admin.css`

## 🤝 Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) and [DEVELOPER_STANDARDS.md](DEVELOPER_STANDARDS.md) before submitting PRs.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with [ParsedownExtra](https://github.com/erusev/parsedown-extra) for Markdown processing
- Inspired by simple, file-based CMS systems

## 📞 Support

- 🐛 [Report bugs](https://github.com/yourusername/bitblog/issues)
- 💡 [Request features](https://github.com/yourusername/bitblog/issues)
- 📖 [Documentation](https://github.com/yourusername/bitblog/wiki)

---

**BitBlog** - Simple, secure, and fast! 🚀

Made with ❤️ by the BitBlog community
