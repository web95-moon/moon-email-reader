# Moon Email Reader

## Installation

Require this package with composer.

```bash
composer require moon/reademail
```

Copy the package config to your local config with the publish command:
```bash
php artisan vendor:publish --provider="moon\reademail\ReadEmailServiceProvider"
```

## Usage

```php
use moon\reademail\MoonEmailReader;

$email = new MoonEmailReader($request->all());
$content = $email->getEmail();
```