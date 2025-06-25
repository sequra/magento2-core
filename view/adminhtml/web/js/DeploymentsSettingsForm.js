(function () {
    /**
     * @typedef Deployment
     * @property {string} id
     * @property {string} name
     * @property {boolean} [active]
     */

    /**
     * @param {{
     *   deploymentsSettings: Deployment[]
     * }} data
     * @param {{
     *   saveDeploymentsUrl: string,
     *   page: string,
     *   appState: string
     * }} configuration
     * @constructor
     */
    function DeploymentsSettingsForm(data, configuration) {
        const api = SequraFE.ajaxService;
        const { elementGenerator: generator, validationService: validator, utilities } = SequraFE;

        let allDeployments = (data.deploymentsSettings || []).map(dep => ({
            ...utilities.cloneObject(dep),
            active: dep.active !== false,
        }));

        let deploymentsChanged = false;

        this.render = () => {
            const pageContent = document.querySelector('.sq-content');
            pageContent.innerHTML = '';

            const selectedIds = allDeployments
                .filter(dep => dep.active)
                .map(dep => dep.id);

            const container = generator.createElement('div', 'sq-content-inner sqv--deployments', '', null, [
                generator.createElement('div', 'sqp-flash-message-wrapper'),
                generator.createPageHeading({
                    title: 'deployments.title',
                    text: 'deployments.description'
                }),
                generator.createMultiItemSelectorField({
                    name: 'deployments-selector',
                    label: 'deployments.selector.label',
                    description: 'deployments.selector.description',
                    value: selectedIds.join(','),
                    options: allDeployments.map(dep => ({
                        label: dep.name,
                        value: dep.id
                    })),
                    onChange: handleDeploymentChange
                }),
                generator.createButtonField({
                    className: 'sq-continue sqm--block',
                    buttonType: 'primary',
                    buttonLabel: 'general.continue',
                    onClick: handleSave
                })
            ]);

            pageContent?.append(container);
            utilities.hideLoader();
        };

        const handleDeploymentChange = (selectedIds) => {
            allDeployments.forEach(dep => {
                dep.active = selectedIds.includes(dep.id);
            });
            deploymentsChanged = true;
        };

        const isFormValid = () => {
            const hasActive = allDeployments.some(dep => dep.active);
            if (!hasActive) {
                validator.validateRequiredField(
                    document.querySelector(`[name="deployments-selector"]`),
                    'validation.requiredField'
                );
                return false;
            }
            return true;
        };

        const handleSave = () => {
            if (!isFormValid()) {
                return;
            }

            utilities.showLoader();
            saveChangedData();
        };

        const saveChangedData = () => {
            deploymentsChanged = false;

            SequraFE.state.setData('deploymentsSettings', allDeployments);

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.DEPLOYMENTS);
                const nextPage = SequraFE.pages.onboarding[index + 1];

                window.location.hash = nextPage
                    ? `${configuration.appState}-${nextPage}`
                    : SequraFE.appStates.SETTINGS;
            }

            if (configuration.appState !== SequraFE.appStates.ONBOARDING) {
                utilities.hideLoader();
            }
        };
    }

    SequraFE.DeploymentsSettingsForm = DeploymentsSettingsForm;
})();
