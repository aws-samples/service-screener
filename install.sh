#! /bin/bash
if(sudo yum list installed  | grep php-cli > /dev/null) then echo 'PHP installed ,skipped'; else sudo amazon-linux-extras install -y php8.0; fi
## aws-sdk requires mbstring and xml
if(sudo yum list installed | grep php-mbstring > /dev/null) then echo 'php-mbstring installed, skipped'; else sudo yum install php-mbstring -y; fi
if(sudo yum list installed | grep php-xml > /dev/null) then echo 'php-xml installed, skipped'; else sudo yum install php-xml -y; fi
if(sudo yum list installed | grep php-opcache > /dev/null) then echo 'php-opcache installed, skipped'; else sudo yum install php-opcache -y; fi
if(sudo yum list installed | grep php-gd > /dev/null) then echo 'php-gd installed, skipped'; else sudo yum install php-gd -y; fi

## Install Composer & PHP SDK
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
php -d memory_limit=-1 composer.phar require aws/aws-sdk-php phpoffice/phpspreadsheet

## Setup Screener Alias
alias screener='php -dopcache.enable_cli=1 -dopcache.jit_buffer_size=128M $(pwd)/screen.php'