# Atomic Deployment Package for Laravel Framework
![run-tests](https://github.com/J-T-McC/laravel-atomic-deployments/workflows/run-tests/badge.svg)

The purpose of this package is to introduce local zero-downtime deployments into a laravel application.

## Requirements
 
* Laravel 8
* PHP ^7.4 | ^8.0

## Installation

```shell script

composer require jtmcc/atomic-deployments

php artisan migrate

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
    'build-limit' => 10
];
```

By default, this package will restrict your project to 10 deployment builds. Once you hit the limit defined in the config, 
older deployments will be automatically deleted. Be aware of the size of your project and adjust to meet your needs.

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

Deploy current under using a custom directory name 
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

## License

MIT
