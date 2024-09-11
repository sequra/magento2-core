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

### How to try the module
The repository includes a docker-compose file to easily test the module. You can start the environment with the following command:

```bash
./setup.sh
```

This will start a Magento 2 instance with the seQura module installed. You can access the admin panel at `http://localhost:8018/admin` with the credentials `admin`/`password123`.


### Customizing the environment
You could create you own .env file to customize the environment. You can copy the .env.example file and modify the values as needed.

#### Some examples
* You can customize the Magento version by setting the `MAGENTO_VERSION` environment variable.
* You can customize the sequra/magento module version by setting the `SQ_M2_CORE_VERSION` environment variable. Leave it as local to use the local version of the module.
* You can customize the host and ports by setting the `MAGENTO_HOST` and `MAGENTO_HTTP_PORT` environment variable.

### Loading sample data
You can load sample data with the following command:

```bash
./bin/install-sampledata
```

or setting the `MAGENTO_SAMPLEDATA` environment variable to `yes` when before running the ./setup.sh script.


> After installing sample data you may get 404 errors for http://${MAGENTO_HOST}/%7B%7BMEDIA_URL%7D%7Dstyles.css.
> To fix this issue go to Content -> Design -> Configuration -> Edit your theme -> HTML Head -> Scripts and Style Sheets and change the line with `{{MEDIA_URL}}styles.css` to `media/styles.css`

### Other helper scripts
You can run commands in the Magento container with the following command:

```bash
./bin/magento <command>
```
To run magento commands in the container.

```bash
./bin/composer <command>
```
To run composer commands in the container.

```bash
./bin/mysql
```
To open mysql terminal in the container.

```bash
./bin/shell
```
To open a bash shell commands in the container.