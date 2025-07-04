if (!window.SequraFE) {
    window.SequraFE = {};
}

/**
 * Creates and shows the "Manage Deployments" modal.
 *
 * @param {{
 *   generator: typeof SequraFE.elementGenerator,
 *   components: typeof SequraFE.components
 *   notConnectedDeployments: DeploymentSettings[]
 *   changedSettings: ConnectionSettings
 *   activeSettings: ConnectionSettings
 * }} dependencies
 *
 * @returns {Promise<boolean>} Resolves true if confirmed, false if canceled
 */
window.SequraFE.showDeploymentsModal = function ({ generator, components, validator,notConnectedDeployments, changedSettings, activeSettings }) {
    return new Promise((resolve) => {
        if (!notConnectedDeployments || notConnectedDeployments.length === 0) {
            resolve({ confirmed: false, selectedDeploymentId: null });
            return;
        }

        const getSettingsForDeployment = (deploymentId) =>
            changedSettings.connectionData.find(c => c.deployment === deploymentId) || { username: '', password: '' };

        let activeDeploymentId = notConnectedDeployments[0].id;

        const headerWrapper = generator.createElement('div', 'sq-page-header');

        const menuWrapper = generator.createElement('div', 'sqp-menu-items sqm-deployments');
        const deploymentItems = notConnectedDeployments.map(deployment => {
            const item = generator.createElement(
                'span',
                `sqp-menu-item ${deployment.id === activeDeploymentId ? 'sqs--active' : ''}`,
                deployment.name
            );

            item.style.cursor = 'pointer';
            item.addEventListener('click', () => {
                if (deployment.id !== activeDeploymentId) {
                    activeDeploymentId = deployment.id;

                    // update active class
                    deploymentItems.forEach(el => el.classList.remove('sqs--active'));
                    item.classList.add('sqs--active');
                }
            });

            return item;
        });

        menuWrapper.append(...deploymentItems);
        headerWrapper.append(menuWrapper);

        const content = generator.createElement('div', 'sq-content-inner sqv--deployments', '', null, [
            generator.createElement('p', '', 'deployments.description'),
            headerWrapper
        ]);

        const usernameInput = generator.createTextField({
            name: 'username-input',
            value: getSettingsForDeployment(activeDeploymentId).username,
            className: 'sq-text-input',
            label: 'connection.username.label',
            description: 'connection.username.description',
            onChange: (value) => handleChange('username', value)
        });
        content.append(usernameInput);

        const passwordInput = generator.createPasswordField({
            name: 'password-input',
            value: getSettingsForDeployment(activeDeploymentId).password,
            className: 'sq-password-input',
            label: 'connection.password.label',
            description: 'connection.password.description',
            onChange: (value) => handleChange('password', value)

        });
        content.append(passwordInput);

        /**
         * Handles form input changes.
         *
         * @param name
         * @param value
         */
        const handleChange = (name, value) => {
            if (['username', 'password'].includes(name)) {
                validator.validateRequiredField(
                    document.querySelector(`[name="${name}-input"]`),
                    'validation.requiredField'
                );

                const current = getSettingsForDeployment(changedSettings);
                if (current) {
                    current[name] = value;
                }
            }
        };

        const modal = components.Modal.create({
            title: 'deployments.title',
            className: 'sq-modal',
            content: [content],
            footer: true,
            buttons: [
                {
                    type: 'secondary',
                    label: 'general.cancel',
                    onClick: () => {
                        modal.close();
                        resolve(false);
                    }
                },
                {
                    className: 'sqp-save',
                    type: 'primary',
                    label: 'general.saveChanges',
                    onClick: () => {
                        modal.close();
                    }
                }
            ]
        });

        modal.open();
    });
};
