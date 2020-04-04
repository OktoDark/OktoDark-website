# OktoDark

##### Website: https://www.oktodark.com

-------------

### Build
[![Build Status](https://travis-ci.com/OktoDark/OktoDark-website.svg?branch=master)](https://travis-ci.com/OktoDark/OktoDark-website)

-------------
### Description
The main website builded in Symfony 5 for OktoDark Studios.

-------------
### Prepare for deploy
**Requirements**

Linux Ubuntu (latest)

Install PHP 7.3:

`apt-get update && apt-get upgrade`

`apt-get install python-software-properties`

`add-apt-repository ppa:ondrej/php`

`apt-get update`

`apt-get install php7.3`

Install Composer:

`curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer`

**Deploy**

`./bin/console deploy`
