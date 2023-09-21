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
     * saveConnectionDataUrl: string,
     * getSellingCountriesUrl: string,
     * getCountrySettingsUrl: string,
     * saveCountrySettingsUrl: string,
     * getWidgetSettingsUrl: string,
     * saveWidgetSettingsUrl: string,
     * getWidgetConfiguratorUrl: string,
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

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({ storeId }) => {
            utilities.showLoader();
            currentStoreId = storeId;
            templateService.clearMainPage();

            Promise.all([SequraFE.state.getStores(), SequraFE.state.getVersion()])
                .then(([res1, res2]) => {
                    stores = res1;
                    version = res2;
                }).finally(() => {
                initializePage();
                renderPage();
                utilities.hideLoader();
            });
        };

        /**
         * Handles rendering of a form based on state.
         */
        const renderPage = () => {
            utilities.showLoader();
            let page = configuration.page;
            let renderer;
            let promises;

            if (!SequraFE.pages.onboarding.includes(page)) {
                page = SequraFE.pages.onboarding[0];
            }

            switch (page) {
                case SequraFE.appPages.ONBOARDING.COUNTRIES:
                    renderer = renderCountrySettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getCountrySettingsUrl),
                        api.get(configuration.getSellingCountriesUrl),
                        api.get(configuration.getConnectionDataUrl)
                    ])
                    break;
                case SequraFE.appPages.ONBOARDING.WIDGETS:
                    renderer = renderWidgetSettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getWidgetSettingsUrl),
                        api.get(configuration.getConnectionDataUrl),
                        api.get(configuration.getCountrySettingsUrl)
                    ])
                    break;
                default:
                    renderer = renderConnectionSettingsForm;
                    promises = Promise.all([api.get(configuration.getConnectionDataUrl)])
            }

            promises
                .then((array) => renderer(...array))
                .catch(renderer)
                .finally(() => utilities.hideLoader());
        };

        /**
         * Renders the country settings form.
         *
         * @param countrySettings
         * @param sellingCountries
         * @param connectionSettings
         */
        const renderCountrySettingsForm = (countrySettings, sellingCountries, connectionSettings) => {
            const form = formFactory.getInstance(
                'generalSettings',
                { countrySettings, sellingCountries, connectionSettings },
                { ...configuration, appState: SequraFE.appStates.ONBOARDING }
            );

            form && form.render();
        }

        /**
         * Renders the widgets settings form.
         *
         * @param widgetSettings
         * @param connectionSettings
         * @param countrySettings
         */
        const renderWidgetSettingsForm = (widgetSettings, connectionSettings, countrySettings) => {
            const form = formFactory.getInstance(
                'widgetSettings',
                { widgetSettings, connectionSettings, countrySettings },
                { ...configuration, appState: SequraFE.appStates.ONBOARDING }
            );

            form && form.render();
        }

        /**
         * Renders the connection settings form.
         *
         * @param connectionSettings
         */
        const renderConnectionSettingsForm = (connectionSettings) => {
            const form = formFactory.getInstance(
                'connectionSettings',
                { connectionSettings },
                { ...configuration, appState: SequraFE.appStates.ONBOARDING }
            );

            form && form.render();
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
                const activePage = configuration.page ?? SequraFE.pages.settings[0];

                switch (page) {
                    case SequraFE.appPages.ONBOARDING.CONNECT:
                        return {
                            label: 'sidebar.stepTwoLabel',
                            href: '#onboarding-connect',
                            isCompleted: SequraFE.pages.onboarding.indexOf(configuration.page) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.CONNECT),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.CONNECT
                        }
                    case SequraFE.appPages.ONBOARDING.COUNTRIES:
                        return {
                            label: 'sidebar.stepThreeLabel',
                            href: '#onboarding-countries',
                            isCompleted: SequraFE.pages.onboarding.indexOf(configuration.page) >
                                SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.COUNTRIES),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.COUNTRIES
                        }
                    case SequraFE.appPages.ONBOARDING.WIDGETS:
                        return {
                            label: 'sidebar.stepFourLabel',
                            href: '#onboarding-widgets',
                            isCompleted: SequraFE.pages.onboarding.indexOf(configuration.page) >
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
                        generator.createElement(
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
                    ]),
                ])
            )
        }

        /**
         * Sets a number in local storage that indicates which step has been successfully completed.
         *
         * @param step
         */
        this.setDoneStep = (step) => {
            localStorage.setItem('sq-done-step', step);
        }
    }

    SequraFE.OnboardingController = OnboardingController;
})();
