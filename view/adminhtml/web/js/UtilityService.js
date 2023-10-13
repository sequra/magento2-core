if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    function UtilityService() {
        /**
         * Shows the HTML node.
         *
         * @param {HTMLElement} element
         */
        this.showElement = (element) => {
            element?.classList.remove('sqs--hidden');
        };

        /**
         * Hides the HTML node.
         *
         * @param {HTMLElement} element
         */
        this.hideElement = (element) => {
            element?.classList.add('sqs--hidden');
        };

        /**
         * Enables loading spinner.
         */
        this.showLoader = () => {
            this.showElement(document.getElementById('sq-spinner'));
        };

        /**
         * Hides loading spinner.
         */
        this.hideLoader = () => {
            this.hideElement(document.getElementById('sq-spinner'));
        };

        /**
         * Shows flash message.
         *
         * @note Only one flash message will be shown at the same time.
         *
         * @param {string} message
         * @param {'error' | 'warning' | 'success'} status
         * @param {number?} clearAfter Time in ms to remove alert message.
         */
        this.createFlashMessage = (message, status, clearAfter) => {
            return SequraFE.elementGenerator.createFlashMessage(message, status, clearAfter);
        };

        /**
         * Updates a form's footer state based on the number of changes.
         *
         * @param {boolean} disable
         */
        this.disableFooter = (disable) => {
            const saveButton = document.querySelector('.sq-page-footer .sqp-actions .sqp-save');
            const cancelButton = document.querySelector('.sq-page-footer .sqp-actions .sqp-cancel');

            if(saveButton && cancelButton){
                saveButton.disabled = disable;
                cancelButton.disabled = disable;
            }
        };
        /**
         * Creates deep clone of an object with object's properties.
         * Removes object's methods.
         *
         * @note Object cannot have values that cannot be converted to json (undefined, infinity etc).
         *
         * @param {object} obj
         * @return {object}
         */
        this.cloneObject = (obj) => JSON.parse(JSON.stringify(obj || {}));

        /**
         * Gets the first ancestor element with the corresponding class name.
         *
         * @param {HTMLElement} element
         * @param {string} className
         * @return {HTMLElement}
         */
        this.getAncestor = (element, className) => {
            let parent = element?.parentElement;

            while (parent) {
                if (parent.classList.contains(className)) {
                    break;
                }

                parent = parent.parentElement;
            }

            return parent;
        };
    }

    SequraFE.utilities = new UtilityService();
})();
