#! /bin/bash

if(sudo yum list installed  | grep php-cli > /dev/null) then echo 'PHP installed ,skipped'; else sudo amazon-linux-extras install -y php7.2; fi
## aws-sdk requires mbstring and xml
if(sudo yum list installed | grep php-mbstring > /dev/null) then echo 'php-mbstring installed, skipped'; else sudo yum install php-mbstring -y; fi
if(sudo yum list installed | grep php-xml > /dev/null) then echo 'php-xml installed, skipped'; else sudo yum install php-xml -y; fi