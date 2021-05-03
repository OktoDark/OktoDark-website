# OktoDark

##### Website: https://www.oktodark.com

-------------

### Build
[![CI](https://github.com/OktoDark/OktoDark-website/actions/workflows/ci.yaml/badge.svg)](https://github.com/OktoDark/OktoDark-website/actions/workflows/ci.yaml)
[![Lint](https://github.com/OktoDark/OktoDark-website/actions/workflows/lint.yaml/badge.svg)](https://github.com/OktoDark/OktoDark-website/actions/workflows/lint.yaml)

-------------
### Description
The main website builded in Symfony 5 for OktoDark Studios.

-------------
### Prepare for deploy
**Requirements**

Linux Ubuntu (latest)

Install PHP 8.0:

`apt-get update && apt-get upgrade`

`apt-get install python-software-properties`

`add-apt-repository ppa:ondrej/php`

`apt-get update`

`apt-get install php8.0`

Install Composer:

`curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer`

**Deploy**

`./bin/console deploy`
