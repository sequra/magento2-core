if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef PaymentMethod
     * @property {string} product
     * @property {string} title
     * @property {string | null} description
     * @property {number | null} minAmount
     * @property {number | null} maxAmount
     * @property {string | null} startsAt
     * @property {string | null} endsAt
     */

    /**
     * Handles payment methods page logic.
     *
     * @param {{
     * getPaymentMethodsUrl: string,
     * getSellingCountriesUrl: string,
     * getCountrySettingsUrl: string,
     * getConnectionDataUrl: string
     * }} configuration
     * @constructor
     */
    function PaymentController(configuration) {
        const {templateService, elementGenerator: generator, components, utilities} = SequraFE;
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        /** @type string */
        let currentStoreId = '';
        /** @type Version */
        let version;
        /** @type string */
        let merchantId = '';
        /** @type string */
        let env = '';
        /** @type Store[] */
        let stores;
        /** @type SellingCountry[] */
        let sellingCountries;
        /** @type PaymentMethod[] */
        let paymentMethods;
        /** @type CountrySettings[] */
        let countryConfiguration;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({storeId}) => {
            utilities.showLoader();
            currentStoreId = storeId;
            templateService.clearMainPage();

            Promise.all([
                SequraFE.state.getStores(),
                SequraFE.state.getVersion(),
                api.get(configuration.getConnectionDataUrl),
                api.get(configuration.getSellingCountriesUrl),
                api.get(configuration.getCountrySettingsUrl)
            ])
                .then(([
                           storesData,
                           versionData,
                           connectionData,
                           sellingCountriesData,
                           countryConfigurationData,
                       ]) => {
                    stores = storesData;
                    version = versionData;
                    merchantId = connectionData.merchantId;
                    env = connectionData.environment;
                    sellingCountries = sellingCountriesData;
                    countryConfiguration = countryConfigurationData;

                    return api.get(configuration.getPaymentMethodsUrl.replace(encodeURIComponent('{merchantId}'), countryConfiguration[0].merchantId));
                })
                .then((methods) => paymentMethods = methods)
                .finally(() => {
                    initializePage();
                    utilities.hideLoader();
                });
        };

        /**
         * Renders the page contents.
         */
        const initializePage = () => {
            const pageWrapper = document.getElementById('sq-page-wrapper');

            pageWrapper.append(
                generator.createElement('div', 'sq-page-content-wrapper sqv--payments', '', null, [
                    SequraFE.components.PageHeader.create(
                        {
                            currentVersion: version.current,
                            newVersion: {
                                versionLabel: version.new,
                                versionUrl: version.downloadNewVersionUrl
                            },
                            mode: env === 'live' ? env : 'test',
                            merchantName: merchantId,
                            activeStore: currentStoreId,
                            stores: stores.map((store) => ({label: store.storeName, value: store.storeId})),
                            onChange: (storeId) => {
                                if (storeId !== SequraFE.state.getStoreId()) {
                                    SequraFE.state.setStoreId(storeId);
                                    window.location.hash = '';
                                    SequraFE.state.display();
                                }
                            },
                            menuItems: [
                                {
                                    label: 'general.paymentMethods',
                                    href: window.location.href.split('#')[0] + '#payment',
                                    isActive: true,
                                },
                                {
                                    label: 'general.settings',
                                    href: window.location.href.split('#')[0] + '#settings'
                                }
                            ]
                        }
                    ),
                    generator.createElement('div', 'sq-page-content', '', null, [
                        generator.createElement('div', 'sq-content-row', '', null, [
                            generator.createElement('main', 'sq-content', '', null, [
                                generator.createElement('div', 'sq-content-inner', '', null, [
                                    generator.createElement('div', 'sq-table-heading', '', null, [
                                        generator.createPageHeading({
                                            title: 'payments.title',
                                            text: 'payments.description'
                                        }),
                                        sellingCountries.length > 0 ? generator.createDropdownField({
                                            className: 'sqm--table-dropdown',
                                            label: 'payments.countries.label',
                                            description: 'payments.countries.description',
                                            value: countryConfiguration[0].merchantId,
                                            options: countryConfiguration.map((countrySetting) => {
                                                return {
                                                    label: sellingCountries.find((country) =>
                                                        countrySetting.countryCode === country.code
                                                    ).name, value: countrySetting.merchantId
                                                }
                                            }),
                                            onChange: handleCountryChange
                                        }) : [],
                                    ]),
                                    sellingCountries.length > 0 ? components.DataTable.create(getTableHeaders(), getTableRows()) : []
                                ]),
                            ])
                        ])
                    ]),
                ]))
        }

        /**
         * Returns table headers.
         *
         * @returns {TableCell[]}
         */
        const getTableHeaders = () => {
            return [
                {label: 'payments.paymentMethods', className: 'sqp-payment-method-header-cell sqm--text-left'},
                {label: 'payments.minimumAmount'},
                {label: 'payments.maximumAmount'},
                {label: 'payments.availableFrom'},
                {label: 'payments.availableTo'},
            ];
        }

        /**
         * Returns table rows.
         *
         * @returns {TableCell[][]}
         */
        const getTableRows = () => {
            return paymentMethods.map((method) => {
                return [
                    {
                        className: 'sqp-payment-method-cell sqm--text-left',
                        renderer: (cell) => {
                            cell.prepend(
                                generator.createElementFromHTML(SequraFE.imagesProvider.icons.payment || ''),
                                generator.createElement('div', '', '', null, [
                                    generator.createElement('h3', 'sqp-payment-method-title', method.title),
                                    generator.createElement(
                                        'p',
                                        'sqp-payment-method-description',
                                        method?.description ?? ''
                                    )
                                ])
                            )
                        }
                    },
                    {label: method?.minAmount ? formatAmount(method.minAmount) : '/'},
                    {label: method?.maxAmount ? formatAmount(method.maxAmount) : '/'},
                    {label: method?.startsAt ? formatDate(method.startsAt) : '/'},
                    {label: method?.endsAt ? formatDate(method.endsAt) : '/'}
                ];
            });
        }

        /**
         * Handles the customer country change.
         *
         * @param value
         */
        const handleCountryChange = (value) => {
            utilities.showLoader();
            api.get(configuration.getPaymentMethodsUrl.replace(encodeURIComponent('{merchantId}'), value))
                .then((methods) => {
                    paymentMethods = [...methods];
                    document.querySelector('.sq-table-container').remove();
                    document.querySelector('.sq-content-inner').append(
                        components.DataTable.create(getTableHeaders(), getTableRows())
                    )
                })
                .finally(utilities.hideLoader);
        }

        /**
         * Formats the given date to the required table view.
         *
         * @param date
         *
         * @returns {`${string}/${string}/${number}`}
         */
        const formatDate = (date) => {
            const dateTime = new Date(date.replace(/ /g, "T"));
            const day = String(dateTime.getDate()).padStart(2, '0');
            const month = String(dateTime.getMonth() + 1).padStart(2, '0');
            const year = dateTime.getFullYear();

            return `${day}/${month}/${year}`;
        }

        /**
         * Formats the given amount to the required table view.
         *
         * @param amount
         *
         * @returns {string}
         */
        const formatAmount = (amount) => {
            return (amount / 100).toLocaleString('es', {minimumFractionDigits: 2}) + ' EUR';
        }
    }

    SequraFE.PaymentController = PaymentController;
})();
