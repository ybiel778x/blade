# Blade Template Engine
This component is a standalone version of the Blade Template Engine that can be ported and used in different projects. It allows you to use the blade model outside of Laravel.

## Instal

How to install execute:
```shell
composer require ybiel778x/blade
```

## How to use

```php

use CodeAlpha\Blade\BladeCompiler;

require __DIR__ . '/vendor/autoload.php';

//Template folder
$template = __DIR__ . '/views';

//Compiled template folder
$compiled = __DIR__ . '/compiled';

//start instance of blade

$blade = new BladeCompiler($template, $compiled);
$blade->share([
    'example' => 'example shared vars'
]);

//renders the template located in the defined folder
//the template file must contain the final extension like '.blade.php'
//Ex: welcome.blade.php
echo $blade->run("welcome");

```