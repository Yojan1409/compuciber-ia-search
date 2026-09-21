(function (wp) {

    const el = wp.element.createElement;

    wp.blocks.registerBlockType(
        'compuciber/ai-search',
        {
            title: 'Compuciber AI Search',
            icon: 'search',
            category: 'widgets',

            edit: function () {

                return el(
                    'div',
                    {
                        style: {
                            padding: '20px',
                            border: '1px dashed #999',
                            textAlign: 'center'
                        }
                    },
                    'Compuciber AI Search'
                );
            },

            save: function () {

                return null;
            }
        }
    );

})(window.wp);