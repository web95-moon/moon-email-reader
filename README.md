# Moon Email Reader

Install via Composer:

```bash
composer require moon/reademail
Publish the config file:

bash
Copy code
php artisan vendor:publish --provider="moon\reademail\ReadEmailServiceProvider"
Usage:

php
Copy code
use moon\reademail\MoonEmailReader;

$email = new MoonEmailReader($request->all());
$content = $email->getEmail();