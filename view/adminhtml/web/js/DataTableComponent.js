if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    const { elementGenerator: generator, translationService } = SequraFE;
    /**
     * @typedef TableCell
     * @property {string?} label
     * @property {string?} className
     * @property {(cell: HTMLTableCellElement) => void?} renderer
     */

    /**
     * Renders table cell.
     *
     * @param {'td' | 'th'} type
     * @param {TableCell} cellData
     * @returns {HTMLElement}
     */
    const renderCell = (type, cellData) => {
        const cell = generator.createElement(type, cellData.className, cellData.label);
        cellData.renderer && cellData.renderer(cell);

        return cell;
    };

    const getTableElement = () => {
        return generator.createElementFromHTML('<table><thead><tr></tr></thead><tbody></tbody></table>');
    };

    /**
     * Renders table rows.
     *
     * @param tableWrapper
     * @param {TableCell[][]} items
     */
    const createTableRows = (tableWrapper, items) => {
        items.length &&
            tableWrapper.querySelector('tbody').append(
                ...items.map((item) => {
                    const row = generator.createElement('tr');
                    row.append(...item.map((cellData) => renderCell('td', cellData)));

                    return row;
                })
            );
    };

    /**
     * Data table component.
     *
     * @param {TableCell[]} headers
     * @param {TableCell[][]} items
     * @param {string?} className
     */
    const createDataTable = (headers, items, className = 'sq-table-container') => {
        const tableElement = getTableElement();

        headers.forEach((header) => {
            header.label = translationService.translate(header.label);
        });

        const heading = tableElement.querySelector('thead tr');
        heading.append(...headers.map((cellData) => renderCell('th', cellData)));

        createTableRows(tableElement, items);

        return generator.createElement('div', className, '', null, [tableElement]);
    };

    SequraFE.components.DataTable = {
        create: createDataTable,
        createTableRows
    };
})();
