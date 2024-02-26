#!/usr/bin/env bash

# Packages the Tebex Checkout payment gateway into a distributable zip.
#
# Structure for archive
#   - tebexcheckout/                  - Library code, logo, etc.
#   - callback/tebexcheckout.php      - Module callback placed in `modules/gateways/callback`
#   - tebexcheckout.php               - Module definition placed in `modules/gateways`
#
# This structure allows a user to unzip the archive directly into their `gateways` folder in order to install Tebex.

VERSION="1.0.1"

# Make temporary build dir, clearing it out if it existed
rm -r .build;mkdir .build
mkdir .build/tebexcheckout

# Copy main module files
cp -r lib .build/tebexcheckout/
cp ./logo.png .build/tebexcheckout/logo.png
cp ./README.md .build/tebexcheckout/README.md
cp ./whmcs.json .build/tebexcheckout/whmcs.json

# Copy callback module
mkdir .build/callback
cp callback/tebexcheckout.php .build/callback/tebexcheckout.php

# Copy gateway module
cp gateway/tebexcheckout.php .build/tebexcheckout.php

# Set version in display name
sed -i '' "s/\%VERSION%/$VERSION/g" .build/tebexcheckout.php

# Create the zip
cd .build
zip -r TebexCheckout-WHMCS-$VERSION.zip *
cd ..
