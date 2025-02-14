# Magento module for SeQura payment gateway

1. [About seQura](#about-sequra)
2. [Installation guide](https://sequra.atlassian.net/wiki/spaces/DOC/pages/1377304583/MAGENTO+2)
3. [Sign-up](#sign-up)
4. [For developers](#for-developers)

## About seQura
### Description

seQura is the flexible payment platform that will help your business improve conversion and recurrence. 
The easiest, safest, and quickest way for your customers to pay on installments.

+6.000 e-commerce and +1.5 million delight shoppers already use seQura. Are you still thinking about it?

This Magento 2 plugin allows you to make payments with [seQura](https://sequra.es).

### Benefits for merchants

> Flexible payment solutions adapted to your business.

Widest flexible payment solutions in the market:

* Buy now pay later 
* Pay in 3, no interest
* Installments, up to 24 months
* Flexi, combines interest-free BNPL with long-term financing in a single purchase experience

Your customers in good hands:

* Cost transparency and clarity
* Local support teams to deliver the best shopper experience
* Secure data, we don’t share your data with anyone or use your information to sell our own or third-party products 


Obsessed with conversion and recurrence

* We adapt to your business, solutions for every sector, and buyer profile
* The highest acceptance rate in Southern Europe thanks to our own risk algorithm, created and optimized for the local market
* Instant approval. A frictionless credit-purchase experience, buy-in seconds without document uploads
* seQura marketing collateral to support your campaigns

### Benefits for customers

* Widest range of flexible payment solutions available on the market, up to 4 different solutions to pay as you want.
* Access to credit with no paperwork, just complete 5 fields to be instantly approved
* Security and privacy, we do not sell your personal data to third parties nor share with other companies

## Installation guide

Check the [installation guide](https://sequra.atlassian.net/wiki/spaces/DOC/pages/1377304583/MAGENTO+2)

## Sign-up

Si tu comercio no está dado de alta en seQura, puedes hacerlo [aquí](https://sqra.es/signupmes) para recibir credenciales de sandbox y empezar.

If you are not a seQura merchant yet, you can sign up [here](https://sqra.es/signupmen) to get sandbox credentials and get started.

## For developers

### Starting the environment

The repository includes a docker-compose file to easily test the module. You can start the environment with the following command:

```bash
./setup.sh
```
This will start a Magento 2 instance with the seQura module installed. You can access the admin panel at `http://localhost.sequrapi.com:8018/admin` with the credentials `admin`/`Admin123`.

> [!IMPORTANT]  
> Make sure you have the line `127.0.0.1	localhost.sequrapi.com` added in your hosts file.

> [!NOTE]  
> Once the setup is complete, the Magento root URL, back-office URL, and user credentials (including the password) will be displayed in your terminal.

Additionally, the setup script supports the following arguments:

| Argument | Description |
| -------- | ------------------------------------------------------------------ |
| `--ngrok` | Starts an ngrok container to expose the site to internet using HTTPS. An ngrok Auth Token must be provided either as an argument or as a variable in the `.env` file for it to work |
| `--ngrok-token=YOUR_NGROK_TOKEN` | Define the ngrok Auth Token |
| `--open-browser` | Open the browser and navigate to the Magento root URL once the installation is complete |

### Customization

When the setup script runs, it takes the configuration from the `.env` file in the root of the repository. If the file doesn't exists, it will create a new one, copying the `.env.sample` template. In order to customize your environment before the setup occurs, you might create your `.env` file. To avoid errors, is important that you make a duplicate of `.env.sample` and then rename it to `.env`

You can read the `.env.sample` file to know what are the available configuration variables and understand the purpose of each one.

### Stopping the environment

To stop the containers and perform the cleanup operations run:

```bash
./teardown.sh
```

## Utilities

This repo contains a group of utility scripts under `bin/` directory. The goal is to ease the execution of common tasks without installing additional software.

| Utility | Description |
| -------- | ------------------------------------------------------------------ |
| `bin/composer <arguments>` | This is a wrapper to run composer commands. |
| `bin/magento <arguments>` | This is a wrapper to run Magento CLI commands. |
| `bin/n98-magerun2 <arguments>` | This is a wrapper to run n98 magerun CLI commands. |
| `bin/update-sequra` | Reinstall the seQura plugin in Magento's `vendor` directory using the project files as the repository. |
| `bin/xdebug` | Toggle XDebug on/off. By default XDebug comes disabled by default. |

## Debugging with XDebug

Create a VS Code Workspace file at `.vscode/magento2-core.code-workspace` with the following content:

```json
{
	"folders": [
		{
			"path": ".."
		}
	],
	"settings": {
		"launch": {
			"version": "0.2.0",
			"configurations": [
				{
					"name": "Listen for Xdebug",
					"type": "php",
					"request": "launch",
					"port": 9003,
					"pathMappings": {
						"/var/www/html/vendor/sequra/magento2-core": "${workspaceFolder}",
						"/var/www/html/app/code/Sequra/Helper": "${workspaceFolder}/.docker/magento/HelperModule/Sequra/Helper"
					}
				}
			]
		}
	}
}
```
Then start debugging (F5) making sure you have the option `Listen for Xdebug (workspace)` selected in VS Code

> [!NOTE]  
> You need to install and activate [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) extension

## Building Docker images

The `docker-compose.yml` file uses a customized Magento 2 Docker image available at GitHub Packages Registry. Since the image is private, you need to authenticate to pull it. To do so, you need to create a GitHub Personal Access Token and store it in the `.env` file under the `GITHUB_TOKEN` variable.

Tools for building and pushing the Docker image are available in the `.docker/magento` directory. You can easily build and push the image by running the following commands:

```bash
.docker/magento/build-image.sh
```
The behavior of the script can be customized by setting the following arguments:

| Argument | Description |
| -------- | ------------------------------------------------------------------ |
| `--push` | Push the image to the GitHub Packages Registry. Authentication is required. |
| `--magento=<VERSION>` | The Magento version to use. Supported versions are 2.4.3-p3, 2.4.4-p11, 2.4.5-p10, 2.4.6-p8, 2.4.7-p3 |
| `--php=<VERSION>` | The PHP version to use. Supported versions are 7.4, 8.1, 8.2 |

If arguments are not provided, the script will build the image using the values defined in the `.env` file.

> [!NOTE]  
> For pushing the image, you need a token with the `read:packages` and `write:packages` scopes. The token must be stored in the `.env` file under the `GITHUB_TOKEN` variable or as a global environment variable.