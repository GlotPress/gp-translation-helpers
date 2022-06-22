# gp-translation-helpers

## Notifications

This plugin sends notifications (emails) to notify about some changes. 

All the users that writes in the thread will receive an email, except the one 
that writes the current comment.

We have two different situations, related with the users that will be notified:
- Typo or context request
- Question in one language.

### Typo or context request

The GlotPress admins will receive an email. In the replies, all the users that 
writes in the thread will receive an email. If none of these users is an administrator, 
the other GlotPress admins will receive an email.

### Question in one language

The validators for this project and locale will receive an email. In the replies, 
if none of these users is a validator, all the validators for this project and locale 
will receive an email.

### Disabling notifications

If you want to avoid sending notifications, you can add 

```
add_filter( 'gp_notification_before_send_emails', '__return_empty_array', 10, 1 );
```

## Code standards

### PHP

Before checking the PHP code standards, please, install the dependencies using:
```
composer install
```

To check the PHP code standards, use:
```
composer lint
```

To automatically try to resolve the PHP code standards errors, use:
```
composer format
```

If you want to see all the PHP errors and warnings, use:
```
php ./vendor/bin/phpcs
```

To see only the PHP errors and not the PHP warnings, use:
```
php -n ./vendor/bin/phpcs
```

### JavaScript

Before checking the JavaScript code standards, please, install the dependencies using:
```
npm install
```

To check the JavaScript code standards, use:
```
npm run lint:js
```

To automatically try to resolve the JavaScript code standards errors, use:
```
npm run lint:js-fix
```
## Unit Testing
Ensure you are in the gp-translation-helpers plugin directory.

Install all dependencies 
```
composer install
```

Set up test database
```
tests/phpunit/bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
```

To run the unit tests run 
```
composer test
```
## Changelog

### [0.0.2] Not released

### [0.0.1] 2017-03-29

Forked version from https://github.com/Automattic/gp-translation-helpers