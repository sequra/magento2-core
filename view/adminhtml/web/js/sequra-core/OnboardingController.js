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
     * getDeploymentsUrl: string,
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
        /** @type ConnectionSettings **/
        let connectionSettings;
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
                case SequraFE.appPages.ONBOARDING.DEPLOYMENTS:
                    renderer = renderDeploymentsSettingForm;
                    promises = Promise.all([
                        SequraFE.state.getData('deploymentsSettings') ?? api.get(configuration.getDeploymentsUrl, null, SequraFE.customHeader)
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
         * Renders the connection settings form.
         */
        const renderConnectionSettingsForm = () => {
            const form = formFactory.getInstance(
                'connectionSettings',
                {connectionSettings, activeDeploymentsIds: getActiveDeploymentsIds()},
                {...configuration, appState: SequraFE.appStates.ONBOARDING}
            );

            form?.render();
        }

        /**
         * Returns the deployments the connection form asks credentials for.
         *
         * @returns {string[]}
         */
        const getActiveDeploymentsIds = () => {
            const connectedIds = (connectionSettings?.connectionData || [])
                .map(connection => connection.deployment)
                .filter(Boolean);
            if (connectedIds.length > 0) {
                // A connected store sent back here to reconnect: the `active` selection only
                // lives for the page session that made it, so it is gone after a reload.
                return connectedIds;
            }

            const deployments = SequraFE.state.getData('deploymentsSettings') || [];
            const selectedIds = deployments.filter(deployment => deployment.active).map(deployment => deployment.id);

            // Without a deployments page in the onboarding there is no selection to honour.
            return selectedIds.length > 0 ? selectedIds : deployments.map(deployment => deployment.id);
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

            const onboardingPages = SequraFE.state.getOnboardingPages();
            const pageSteps = onboardingPages.map((page) => {
                const activePage = SequraFE.state.getPage() ?? SequraFE.pages.settings[0];

                switch (page) {
                    case SequraFE.appPages.ONBOARDING.DEPLOYMENTS:
                        return {
                            label: 'sidebar.stepDeployments',
                            href: '#onboarding-deployments',
                            isCompleted: onboardingPages.indexOf(SequraFE.state.getPage()) >
                                onboardingPages.indexOf(SequraFE.appPages.ONBOARDING.DEPLOYMENTS),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.DEPLOYMENTS
                        }

                    case SequraFE.appPages.ONBOARDING.CONNECT:
                        return {
                            label: 'sidebar.stepTwoLabel',
                            href: '#onboarding-connect',
                            isCompleted: onboardingPages.indexOf(SequraFE.state.getPage()) >
                                onboardingPages.indexOf(SequraFE.appPages.ONBOARDING.CONNECT),
                            isActive: activePage === SequraFE.appPages.ONBOARDING.CONNECT
                        }
                }
            }).filter(Boolean);

            return [firstStep, ...pageSteps]
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
                    generator.createSupportLink()
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
                            (!SequraFE.flags.isStoreSwitcherVisible || stores.length <= 1) ? [] : generator.createStoreSwitcher({
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
