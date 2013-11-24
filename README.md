user_wcf
========

Owncloud user backend for the Woltlab Community Framework (WCF) used by Woltlab Burning Board (WBB3)

To use this backend you need to enable the app in OwnCloud and then add the user backend to your configuration file:

```php
$CONFIG = array (
  // [...]
  'user_backends' => array (
    array (
      'class' => 'OCA\\User_WCF\\User_WCF',
      'arguments' => array (
        // Change to your WCF path containing the DB settings (config.inc.php)
        '/var/www/forum/wcf',
        // Change this to the WCF groups that should be allowed to use OC.
        array ('Admins', 'Privilged Users', 'Some other Privileged Group'),
      ),
    ),
  ),
);
```
