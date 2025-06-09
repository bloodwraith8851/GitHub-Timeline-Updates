@echo off
cd /d "D:\XKCD-Comic-Email-Subscription-System"
php github_timeline.php
echo [%date% %time%] GitHub Timeline Updates executed >> cron.log 

chmod 644 .htaccess
chmod 644 *.php
chmod 755 logs/
chmod 755 codes/
chmod 600 .env 