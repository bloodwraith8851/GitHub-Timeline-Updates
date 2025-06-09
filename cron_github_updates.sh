#!/bin/bash

# Set the GitHub token from environment variable
export GITHUB_TOKEN="your_github_token_here"

# Change to the script directory
cd "$(dirname "$0")"

# Run the GitHub timeline update script
php github_timeline.php

# Log the execution
echo "[$(date)] GitHub Timeline Updates executed" >> cron.log 

# Set permissions for .env file
chmod 600 .env

# Set permissions for config.php
chmod 644 config.php

# Set permissions for cron_github_updates.sh
chmod +x cron_github_updates.sh 