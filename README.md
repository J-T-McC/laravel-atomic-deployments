# Atomic Deployment Package for Laravel Framework
![run-tests](https://github.com/J-T-McC/laravel-atomic-deployments/workflows/run-tests/badge.svg)

The purpose of this package is to introduce local zero-downtime deployments into a laravel application.

## Requirements
 
* Laravel 7 | 8
* PHP ^7.4 | ^8.0

## Installation

```shell script

composer require jtmcc/atomic-deployments

php artisan migrate

php artisan vendor:publish --tag=atm-config

```

## Configuration 

#### .env

There are three required environment variables:

1.  **ATM_DEPLOYMENT_LINK**: The symbolic link you will use with your web server, artisan schedules, ...etc
1.  **ATM_BUILD**: The project build folder where you will run composer, any build related logic, update your env
1.  **ATM_DEPLOYMENTS**: The root folder that builds will be deployed to and linked

```dotenv
ATM_DEPLOYMENT_LINK="/var/www/production-site"
ATM_BUILD="/var/www/build-project"
ATM_DEPLOYMENTS="/var/www/deployments"
```

#### config( ...)

```php
return [

    /**
     * Symbolic link to the current deployed build
     * This path should be used for schedules and setting your web root
     */
    'deployment-link' => env('ATM_DEPLOYMENT_LINK'),

    /**
     * The primary build folder
     * This folder is where all deployments ran and ultimately copied to a deployment directory
     */
    'build-path' => env('ATM_BUILD'),

    /**
     * Production build directory
     * Builds are copied here and linked for deployment
     * Ensure this directory has the required permissions to allow php and your webserver to run your application here
     */
    'deployments-path' => env('ATM_DEPLOYMENTS'),

    /**
     * Max number of build directories allowed
     * Once limit is hit, old deployments will be removed automatically after a successful build
     */
    'build-limit' => 10,

    /**
     * Migrate files|folders from the outgoing production build to your new release using a relative path and pattern
     * @see https://www.php.net/manual/en/function.glob.php
     */
    'migrate' => [
//        'storage/framework/sessions/*',
    ]

];
```

By default, this package will restrict your project to 10 deployment builds. Once you hit the limit defined in the config, 
older deployments will be automatically deleted. Be aware of the size of your project and adjust to meet your needs.

You might find yourself in a situation where you need to migrate files that don't exist in your build project from your 
current deployment folder to your new deployment folder. These files/folders can be specified in the migrate config array, 
and they will be copied from the outgoing deployment into the new deployment when you run the deploy command.

Once you have configured your env and have deployed a build, you can update your webserver to start routing traffic 
to your 'deployment-link' location.

```shell script
# nginx example
root /var/www/production-site/public;

#crontab logic example
php /var/www/production-site/artisan schedule:run
```

## Commands

### atomic-deployments:deploy

#### *options*

- --hash= : Specify a previous deployments commit hash/deploy-dir to deploy
- --directory= : Define your deploy folder name. Defaults to current HEAD hash
- --dry-run : Test and log deployment steps
    
#### *examples*

Do a dry run to get some feedback on the steps that will be taken 
```shell script
php artisan atomic-deployments:deploy --dry-run
```

Deploy current build using the current branch git hash for deployment folder 
```shell script
php artisan atomic-deployments:deploy
```

Deploy current build using a custom directory name 
```shell script
php artisan atomic-deployments:deploy --directory=deployment_folder
```

Revert linked project back to a previous build 
```shell script
php artisan atomic-deployments:deploy ---hash=abc1234
```

### atomic-deployments:list

Prints a table to the console of the currently available builds

#### *examples*

```shell script
php artisan atomic-deployments:list
```

## Events

- DeploymentSuccessful
- DeploymentFailed

## Laravel Forge Example

Here is a basic configuration for use with Forge

#### *Deploy Script*

```shell script
cd /home/forge/your-application.com
git pull origin main
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan optimize:clear
    $FORGE_PHP artisan atomic-deployments:deploy
fi
```

#### *.env*

Build project .env

```dotenv
ATM_DEPLOYMENT_LINK="/home/forge/your-application.com-link"
ATM_BUILD="/home/forge/your-application.com"
ATM_DEPLOYMENTS="/home/forge/deployments/your-application.com"
```

#### *nginx config*

```shell script
root /home/forge/your-application.com-link/public;
```

#### *schedule command*

```shell script
php8.0 /home/forge/your-application.com-link/artisan schedule:run
```

If your application is isolated, you must ensure that your deployments folder has the appropriate permissions to serve
your application for that user.


## License

MIT
