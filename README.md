# 🌟 GitHub Timeline Updates

<div align="center">

![GitHub Timeline Updates](https://img.shields.io/badge/GitHub-Timeline%20Updates-2ea043?style=for-the-badge&logo=github)

[![PHP Version](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](http://makeapullrequest.com)

*Stay updated with your GitHub activities through beautiful email notifications* 📨

[Features](#✨-features) • [Setup](#🚀-setup) • [Configuration](#⚙️-configuration) • [Usage](#📖-usage) • [Contributing](#🤝-contributing)

</div>

## ✨ Features

- 🎨 **Beautiful Email Design**
  - Modern GitHub-inspired dark theme
  - Responsive layout for all devices
  - Interactive elements and animations
  - Custom gradients and visual effects

- 📱 **Smart Notifications**
  - Real-time GitHub activity tracking
  - Support for multiple event types
  - Detailed event information
  - Customizable update frequency

- 🔒 **Secure Authentication**
  - Email verification system
  - Secure OTP implementation
  - Easy subscription management
  - One-click unsubscribe feature

- 🛠️ **Supported Events**
  - Push events
  - Pull requests
  - Issue activities
  - Repository creation
  - Branch operations
  - Forks and stars
  - Release publications
  - Commit comments

## 🚀 Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/GitHub-Timeline-Updates.git
   cd GitHub-Timeline-Updates
   ```

2. **Install Dependencies**
   ```bash
   composer require phpmailer/phpmailer
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your settings:
   # - GitHub API Token
   # - SMTP Configuration
   # - Email Settings
   ```

4. **Set Permissions**
   ```bash
   chmod 755 codes/ logs/
   chmod 644 *.php
   chmod 600 .env
   ```

5. **Start the Server**
   ```bash
   php -S localhost:8000
   ```

## ⚙️ Configuration

### Environment Variables

```env
# GitHub Configuration
GITHUB_TOKEN=your_github_token_here

# SMTP Configuration
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_specific_password
FROM_EMAIL=your_email@gmail.com
FROM_NAME=GitHub Timeline Updates
```

### Cron Setup

```bash
# Add to crontab to run every hour
0 * * * * /path/to/cron_github_updates.sh
```

## 📖 Usage

1. **Subscribe to Updates**
   - Visit the homepage
   - Enter your email address
   - Verify with OTP
   - Add your GitHub username

2. **Customize Notifications**
   - Choose event types
   - Set update frequency
   - Configure email preferences

3. **Manage Subscription**
   - View your settings
   - Update preferences
   - Unsubscribe anytime

## 🤝 Contributing

Contributions are welcome! Here's how you can help:

- 🐛 Report bugs
- 💡 Suggest features
- 🔧 Submit pull requests
- 📖 Improve documentation

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

<div align="center">

Made with ❤️ by [Your Name]

</div>
