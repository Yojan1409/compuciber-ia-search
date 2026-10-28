(function (wp) {
    'use strict';

    const el = wp.element.createElement;

    wp.blocks.registerBlockType('compuciber/ai-search', {

        title: 'Compuciber AI Search',
        icon: 'search',
        category: 'widgets',

        attributes: {

            // Perfil opcional del buscador.
            // Vacío = buscador predeterminado.
            perfil: {
                type: 'string',
                default: ''
            },

            // Modo de visualización.
            // false = buscador completo.
            // true = solo barra.
            solo_barra: {
                type: 'boolean',
                default: false
            },

            solo_resultados: {
                type: 'boolean',
                default: false
            }



        },

        edit: function (props) {

            /*
             * Obtener los perfiles activos enviados
             * desde la configuración de WordPress.
             */
            const opciones = (
                window.CompuciberGutenbergPerfiles &&
                Array.isArray(
                    window.CompuciberGutenbergPerfiles.opciones
                )
            )
                ? window.CompuciberGutenbergPerfiles.opciones.slice()
                : [
                    {
                        label: 'Predeterminado (todos los productos)',
                        value: ''
                    }
                ];

            const perfilActual = props.attributes.perfil || '';

            /*
             * Conservar visible un perfil seleccionado
             * aunque posteriormente se desactive
             * o se elimine.
             */
            const perfilDisponible = opciones.some(
                function (opcion) {
                    return opcion.value === perfilActual;
                }
            );

            if (!perfilDisponible && perfilActual !== '') {

                opciones.push({
                    label: 'Perfil no disponible: ' + perfilActual,
                    value: perfilActual
                });

            }

            return el(
                'div',
                {
                    style: {
                        padding: '20px',
                        border: '1px dashed #999',
                        borderRadius: '6px',
                        textAlign: 'left'
                    }
                },

                // Título del bloque.
                el(
                    'strong',
                    null,
                    'Compuciber AI Search'
                ),

                /*
                 * Selector de perfiles.
                 */
                el(
                    'label',
                    {
                        htmlFor: 'cis-perfil-' + props.clientId,
                        style: {
                            display: 'block',
                            marginTop: '12px',
                            marginBottom: '6px'
                        }
                    },
                    'Perfil de búsqueda'
                ),

                el(
                    'select',
                    {
                        id: 'cis-perfil-' + props.clientId,
                        value: perfilActual,

                        onChange: function (evento) {

                            props.setAttributes({
                                perfil: evento.target.value
                            });

                        },

                        style: {
                            width: '100%',
                            maxWidth: '380px'
                        }
                    },

                    opciones.map(function (opcion) {

                        return el(
                            'option',
                            {
                                key: opcion.value,
                                value: opcion.value
                            },
                            opcion.label
                        );

                    })
                ),

                /*
                 * Selector de modo de visualización.
                 */
                el(
                    'label',
                    {
                        htmlFor: 'cis-modo-' + props.clientId,
                        style: {
                            display: 'block',
                            marginTop: '16px',
                            marginBottom: '6px'
                        }
                    },
                    'Modo de visualización'
                ),

                el(
                    'select',
                    {
                        id: 'cis-modo-' + props.clientId,

                        value: props.attributes.solo_resultados
                            ? 'resultados'
                            : (props.attributes.solo_barra ? 'barra' : 'completo'),

                        onChange: function (evento) {

                            const modo = evento.target.value;

                            props.setAttributes({
                                solo_barra: modo === 'barra',
                                solo_resultados: modo === 'resultados'
                            });

                        },

                        style: {
                            width: '100%',
                            maxWidth: '380px'
                        }
                    },

                    el(
                        'option',
                        { value: 'completo' },
                        'Buscador completo'
                    ),

                    el(
                        'option',
                        { value: 'barra' },
                        'Solo barra (ideal para cabeceras)'
                    ),

                    el(
                        'option',
                        { value: 'resultados' },
                        'Solo resultados (páginas de destino)'
                    )
                ),

                /*
                 * Información para el administrador.
                 */
                el(
                    'p',
                    {
                        style: {
                            fontSize: '12px',
                            color: '#666',
                            marginTop: '14px',
                            marginBottom: '0'
                        }
                    },
                    'Sin perfil, el buscador muestra todos los productos.'
                )

            );

        },

        /*
         * Bloque dinámico.
         * El contenido público lo genera PHP.
         */
        save: function () {
            return null;
        }

    });

})(window.wp);