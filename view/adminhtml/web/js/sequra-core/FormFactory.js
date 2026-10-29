if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    function FormFactory() {
        /**
         * Instantiates page controller;
         *
         * @param {string} form
         * @param {Record<string, any>} data
         * @param {Record<string, any>} configuration
         */
        this.getInstance = (form, data, configuration) => {
            const name = form.charAt(0).toUpperCase() + form.slice(1) + 'Form';

            return SequraFE[name] ? new SequraFE[name](data, configuration) : null;
        };
    }

    SequraFE.formFactory = new FormFactory();
})();
