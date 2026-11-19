if (!window.SequraFE) {
    window.SequraFE = {};
}

(() => {
    function StateUUIDService() {
        let currentState = '';

        this.setStateUUID = () => {
            currentState = Math.random().toString(36);
        };

        this.getStateUUID = () => {
            return currentState;
        };
    }

    SequraFE.StateUUIDService = new StateUUIDService();
})();
