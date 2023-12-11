#!/usr/bin/env bash

# Change this to the root of your WHMCS install (containing configuration.php, addons, modules, etc.)
WHMCS_ROOT_DIR="/var/www/html"

# Create symlinks to the module files contained in our module folder
ln -s $WHMCS_ROOT_DIR/modules/gateways/tebexcheckout/callback/tebexcheckout.php /var/www/html/modules/gateways/callback/tebexcheckout.php
ln -s $WHMCS_ROOT_DIR/modules/gateways/tebexcheckout/gateway/tebexcheckout.php /var/www/html/modules/gateways/tebexcheckout.php
