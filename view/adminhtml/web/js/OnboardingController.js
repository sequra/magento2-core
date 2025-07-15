if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * Handles onboarding page logic.
     *
     * @param {{
     * validateConnectionDataUrl: string,
     * getConnectionDataUrl: string,
     * connectUrl: string,
     * getSellingCountriesUrl: string,
     * getCountrySettingsUrl: string,
     * saveCountrySettingsUrl: string,
     * getWidgetSettingsUrl: string,
     * saveWidgetSettingsUrl: string,
     * getPaymentMethodsUrl: string,
     * getAllAvailablePaymentMethodsUrl: string,
     * configurableSelectorsForMiniWidgets: string
     * page: string}} configuration
     * @constructor
     */
    function OnboardingController(configuration) {
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        const {
            templateService,
            elementGenerator: generator,
            utilities,
            formFactory,
        } = SequraFE;

        /** @type string */
        let currentStoreId = '';
        /** @type Version */
        let version;
        /** @type Store[] */
        let stores;
        /** @type CountrySettings[] **/
        let countrySettings;
        /** @type ConnectionSettings **/
        let connectionSettings;
        /** @type WidgetSettings **/
        let widgetSettings;
        /** @type DeploymentSettings[] **/
        let deploymentsSettings;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({storeId}) => {
            utilities.showLoader();
            currentStoreId = storeId;
            templateService.clearMainPage();
            stores = SequraFE.state.getData('stores');
            version = SequraFE.state.getData('version');
            connectionSettings = SequraFE.state.getData('connectionSettings');
            countrySettings = SequraFE.state.getData('countrySettings');
            widgetSettings = SequraFE.state.getData('widgetSettings');
            deploymentsSettings = SequraFE.state.getData('deploymentsSettings');

            initializePage();
            renderPage();
        };

        /**
         * Handles rendering of a form based on state.
         */
        const renderPage = () => {
            utilities.showLoader();
            let page = SequraFE.state.getPage();
            let renderer;
            let promises;

            switch (page) {
                case SequraFE.appPages.ONBOARDING.COUNTRIES:
                    renderer = renderCountrySettingsForm;
                    promises = Promise.all([
                        SequraFE.state.getData('sellingCountries') ?? api.get(configuration.getSellingCountriesUrl)
                    ])
                    break;
                case SequraFE.appPages.ONBOARDING.WIDGETS:
                    renderer = renderWidgetSettingsForm;
                    promises = Promise.all([
                        SequraFE.state.getData('paymentMethods') ?? api.get(configuration.getPaymentMethodsUrl.replace(
                            encodeURIComponent('{merchantId}'),
                            countrySettings[0].merchantId
                        )),
                        SequraFE.state.getData('allAvailablePaymentMethods') ?? api.get(configuration.getAllAvailablePaymentMethodsUrl),
                    ])
                    break;

                case SequraFE.appPages.ONBOARDING.DEPLOYMENTS:
                    renderer = renderDeploymentsSettingForm;
                    promises = Promise.all([
                        SequraFE.state.getData('deploymentsSettings') ?? api.get(configuration.getDeploymentSettingsUrl)
                    ]);
                    break;

                default:
                    renderer = renderConnectionSettingsForm;
                    promises = Promise.all([])
            }

            promises
                .then((array) => renderer(...array))
                .catch((error) => {
                    console.error('Error occurred while rendering the page: ', error);
                })
                .finally(() => utilities.hideLoader());
        };

        /**
         * Renders the country settings form.
         *
         * @param sellingCountries
         */
        const renderCountrySettingsForm = (sellingCountries) => {
            if (!SequraFE.state.getData('sellingCountries')) {
                SequraFE.state.setData('sellingCountries', sellingCountries)
            }

            const form = formFactory.getInstance(
                'generalSettings',
                {countrySettings, sellingCountries, connectionSettings},
                {...configuration, appState: SequraFE.appStates.ONBOARDING}
            );

            form?.render();
        }

        const renderDeploymentsSettingForm = (deploymentsSettings) => {
            if (!SequraFE.state.getData('deploymentsSettings')) {
                SequraFE.state.setData('deploymentsSettings', deploymentsSettings);
            }

            const form = formFactory.getInstance(
                'deploymentsSettings',
                {deploymentsSettings},
                {...configuration, appState: SequraFE.appStates.ONBOARDING}
            );

            form?.render();
        };

        /**
         * Renders the widgets settings form.
         *
         * @param paymentMethods
         * @param allAvailablePaymentMethods
         */
        const renderWidgetSettingsForm = (paymentMethods, allAvailablePaymentMethods) => {
            if (!SequraFE.state.getData('paymentMethods')) {
                SequraFE.state.setData('paymentMethods', paymentMethods)
            }

            if (!SequraFE.state.getData('allAvailablePaymentMethods')) {
                SequraFE.state.setData('allAvailablePaymentMethods', allAvailablePaymentMethods)
            }

            const form = formFactory.getInstance(
                'widgetSettings',
                {widgetSettings, connectionSettings, countrySettings, paymentMethods, allAvailablePaymentMethods},
                {...configuration, appState: SequraFE.appStates.ONBOARDING}
            );

            form?.render();
        }

        /**
         * Renders the connection settings form.
         */
        const renderConnectionSettingsForm = () => {
            const form = formFactory.getInstance(
                'connectionSettings',
                {connectionSettings},
                {...configuration, appState: SequraFE.appStates.ONBOARDING}
            );

            form?.render();
        }

        /**
         * Returns sidebar steps.
         *
         * @returns {unknown[]}
         */
        const getStepConfiguration = () => {
            const firstStep = {
                label: 'sidebar.stepOneLabel',
                description: 'sidebar.stepOneDescription',
                href: '#',
                isCompleted: true
            };

            const lastStep = {
                label: 'sidebar.stepFiveLabel',
                href: '#',
            }

            const pageSteps = SequraFE.pages.onboarding.map((page) => {
                const activePage = SequraFE.state.getPage() ?? SequraFE.pages.settings[0];

                switch (page) {
                    case SequraFE.appPages.ONBOARDING.DEPLOYMENTS:
                        return {
                            label: 'sidebar.stepDeployments',
                            href: '#onboarding-deployments',
                            isCompleted: SequraFE.pages.onboarding.indexOf(SequraFE.state.getPage()) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.DEPLOYMENTS),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.DEPLOYMENTS
                        }

                    case SequraFE.appPages.ONBOARDING.CONNECT:
                        return {
                            label: 'sidebar.stepTwoLabel',
                            href: '#onboarding-connect',
                            isCompleted: SequraFE.pages.onboarding.indexOf(SequraFE.state.getPage()) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.CONNECT),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.CONNECT
                        }
                    case SequraFE.appPages.ONBOARDING.COUNTRIES:
                        return {
                            label: 'sidebar.stepThreeLabel',
                            href: '#onboarding-countries',
                            isCompleted: SequraFE.pages.onboarding.indexOf(SequraFE.state.getPage()) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.COUNTRIES),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.COUNTRIES
                        }
                    case SequraFE.appPages.ONBOARDING.WIDGETS:
                        return {
                            label: 'sidebar.stepFourLabel',
                            href: '#onboarding-widgets',
                            isCompleted: SequraFE.pages.onboarding.indexOf(SequraFE.state.getPage()) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.WIDGETS),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.WIDGETS
                        }
                }
            });

            return [firstStep, ...pageSteps, lastStep]
        }

        /**
         * Initializes general onboarding state content.
         */
        const initializePage = () => {
            const pageWrapper = document.getElementById('sq-page-wrapper')

            pageWrapper.append(
                generator.createElement('div', 'sq-page-content-wrapper sqv--onboarding', '', null, [
                    generator.createElement('div', 'sq-page-content', '', null, [
                        version?.current ? generator.createElement('div', 'sq-version-header', '', null, [
                            generator.createVersionBadge(version.current)
                        ]) : [],
                        getSetupWizardRow()
                    ]),
                ])
            )
        }

        const getSetupWizardRow = () => {
            return generator.createElement(
                'div',
                `sq-content-row ${version?.current ? '' : 'sqs--no-version'}`,
                '',
                null,
                [
                    generator.createWizardSidebar({
                        steps: getStepConfiguration()
                    }),
                    generator.createElement('main', 'sq-content', '', null, [
                        generator.createElement('div', 'sqp-content-header', '', null, [
                            generator.createElementFromHTML(SequraFE.imagesProvider.logo || ''),
                            stores.length <= 1 ? [] : generator.createStoreSwitcher({
                                label: 'general.selectStore',
                                value: currentStoreId,
                                options: stores.map((store) => ({
                                    label: store.storeName,
                                    value: store.storeId
                                })),
                                onChange: (storeId) => {
                                    if (storeId !== SequraFE.state.getStoreId()) {
                                        SequraFE.state.setStoreId(storeId);
                                        window.location.hash = '';
                                        SequraFE.state.display();
                                    }
                                }
                            })
                        ])
                    ])
                ]
            )
        }
    }

    SequraFE.OnboardingController = OnboardingController;
})();
