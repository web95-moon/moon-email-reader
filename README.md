# Moon Email Reader

Install via Composer:

```bash
composer require moon/reademail

php artisan vendor:publish --provider="moon\reademail\ReadEmailServiceProvider"

use moon\reademail\MoonEmailReader;

$email = new MoonEmailReader($request->all());
$content = $email->getEmail();