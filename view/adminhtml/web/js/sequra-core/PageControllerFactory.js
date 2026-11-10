if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    function PageControllerFactory() {
        /**
         * Instantiates page controller;
         *
         * @param {string} controller
         * @param {Record<string, any>} configuration
         */
        this.getInstance = (controller, configuration) => {
            let parts = controller.split('-');
            let name = '';
            for (let part of parts) {
                part = part.charAt(0).toUpperCase() + part.slice(1);
                name += part;
            }

            name += 'Controller';

            return SequraFE[name] ? new SequraFE[name](configuration) : null;
        };
    }

    SequraFE.pageControllerFactory = new PageControllerFactory();
})();
