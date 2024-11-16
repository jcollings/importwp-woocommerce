## Install PHPUnit

```
wp-env run cli --env-cwd=wp-content/plugins/importwp-woocommerce composer install
```

# Run Tests

```
wp-env run tests-cli --env-cwd=wp-content/plugins/importwp-woocommerce vendor/bin/phpunit
```
