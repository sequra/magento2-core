if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * The ResponseService constructor.
     *
     * @constructor
     */
    function ResponseService() {
        /**
         * Handles an error response from the submit action.
         *
         * @param {{errorMessage?: string, errorCode?: string}} response
         * @returns {Promise<void>}
         */
        this.errorHandler = (response) => {
            const { utilities, templateService, elementGenerator } = SequraFE;
            let container = document.querySelector('.sqp-flash-message-wrapper');
            if (!container) {
                container = elementGenerator.createElement('div', 'sqp-flash-message-wrapper');
                templateService.getMainPage().prepend(container);
            }

            templateService.clearComponent(container);

            if (response.errorMessage) {
                container.prepend(utilities.createFlashMessage(response.errorMessage, 'error'));
            } else if (response.errorCode) {
                container.prepend(utilities.createFlashMessage('general.errors.' + response.errorCode, 'error'));
            } else {
                container.prepend(utilities.createFlashMessage('general.errors.unknown', 'error'));
            }

            return Promise.reject(response);
        };

        /**
         * Handles 401 response.
         *
         * @param {{errorMessage?: string, errorCode?: string}} response
         * @returns {Promise<void>}
         */
        this.unauthorizedHandler = (response) => {
            SequraFE.state.goToState(SequraFE.appStates.ONBOARDING);
            SequraFE.utilities.hideLoader();

            return this.errorHandler(response);
        };
    }

    SequraFE.responseService = new ResponseService();
})();
