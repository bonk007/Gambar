# Gambar

##Instalation
### Via Composer
Open your composer.json file.
```
"require-dev":{
...
"bonk007/gambar":"dev-master"
...
}
```
Open your terminal or command line and do composer update
```
composer update
```

##How to use
###with PSR-x autoload
```
<?php
use Gambar/Gambar;
```
###Native
Open src/Gambar.php and src/GambarException.php and set the following lines as the comments
```
<?php
// namespace Gambar;
// use Gambar\GambarException;
...
```
then add include, include_once, require, or require_once into your sourcecode
```
<?php
require_once '<some directory>/src/Gambar.php';
require_once '<some directory>/src/GambarException.php';
```
