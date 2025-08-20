# Introduction

This plugin provides tools for internationalization in CakePHP 5:

I18nRoute – generate and match routes with language prefixes (/{lang}/{controller}/{action}).

I18nMiddleware – automatically sets locale via I18n::setLocale() based on URL prefix and optionally redirects root / to default or browser-detected language.

DbMessagesLoader – load translation messages from the database instead of .po/.mo files.

Validation translation – auto-translate validation messages.

TimezoneWidget – generate a <select> box of timezones grouped by region.

## Installation
composer require zvezda/cakephp-i18n

## Usage
Load Plugin

In CakePHP 5, use Application.php to load plugins:

// src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();
    $this->addPlugin(\ADmad\I18n\Plugin::class);
}

## I18nRoute

Define routes with language prefix:

use ADmad\I18n\Routing\I18nRoute;

$routes->scope('/', function ($routes) {
    $routes->connect(
        '/{controller}',
        ['action' => 'index'],
        ['routeClass' => I18nRoute::class]
    );
    $routes->connect(
        '/{controller}/{action}/*',
        [],
        ['routeClass' => I18nRoute::class]
    );
});


Set supported languages in config/app.php:

return [
    'I18n' => [
        'languages' => ['en', 'fr', 'de']
    ]
];

## I18nMiddleware

Configure middleware in Application::middleware():

use ADmad\I18n\Middleware\I18nMiddleware;

$middlewareQueue->add(new I18nMiddleware([
    'detectLanguage' => true,
    'defaultLanguage' => 'en',
    'languages' => [
        'en' => ['locale' => 'en_US'],
        'fr' => ['locale' => 'fr_FR'],
    ],
]));


## Notes:

Add after RoutingMiddleware.

Configure a root route / to prevent missing route errors:

$routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

## DbMessagesLoader

Store translation messages in the database instead of .po files.

Create i18n_messages table using the SQL file in config/.

Configure loader in config/bootstrap.php:

use ADmad\I18n\I18n\DbMessagesLoader;
use Cake\I18n\I18n;

I18n::config('default', function ($domain, $locale) {
    $loader = new DbMessagesLoader($domain, $locale);
    return $loader();
});


## Extract messages from code:

bin/cake admad/i18n extract --languages en,fr,de

## TimezoneWidget

Register in AppView:

$this->loadHelper('Form', [
    'widgets' => [
        'timezone' => ['ADmad/I18n.Timezone'],
    ],
]);


Use in forms:

// Full timezone list
$this->Form->control('field', ['type' => 'timezone']);

// Specific regions
$this->Form->control('field', [
    'type' => 'timezone',
    'options' => [
        'Asia' => DateTimeZone::ASIA,
        'Europe' => DateTimeZone::EUROPE,
    ],
]);