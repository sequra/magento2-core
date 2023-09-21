# Sequra Magento 2 plugin - developer instructions

## Run unit tests
Configuration for phpunit is in the `./phpunit.xml` file.

If you haven't already done so, you can setup unit tests in PHPStorm.
To do so, first go to `File > Settings > Languages & Frameworks > PHP > Test Frameworks` and 
add new PHPUnit Local configuration. Select `Use composer autoloader` and in the field below navigate to your Magento 
installation folder and select `/vendor/autoload.php` file.

Go to `Run > Edit configuration` menu and add new PHPUnit configuration. 
For Test Runner options select `Defined in configuration file` and add specific phpunit configuration 
file path to the `./phpunit.xml` file in module's root directory.

Create new file `./Test/autoload.php` by copying the file
`./Test/autoload-sample.php`. In the newly created file change path to the magento's root folder,
for example `/var/www/html/magento/app/bootstrap.php`.

Now test configuration is set and you can run tests by activating run command from the 
top right toolbar. 

**All tests must pass.**

*Note*: Tests will fail if you already have installed the module in Magento, because Magento will load both your local 
module and the module from Magento. In such a configuration, you need to rename the module folder in Magento,
run tests, and rename it back.
