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

This WooCommerce plugin allows you to make payments with [seQura](https://sequra.es).

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
| `--build` | Force building Docker images |
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
