<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene los sinónimos predeterminados del plugin.
 * El administrador puede agregar sus propios términos
 * desde el panel de WordPress.
 */
function compuciber_obtener_sinonimos() {

    $sinonimos_predeterminados = array(

        'laptop' => array(
            'portatil',
            'portátil',
            'notebook',
            'computadora portatil',
            'computadora portátil',
        ),

        'mouse' => array(
            'raton',
            'ratón',
            'mause',
            'maus',
        ),

        'teclado' => array(
            'keyboard',
        ),

        'audifonos' => array(
            'audífonos',
            'auriculares',
            'headset',
            'headphones',
        ),

        'monitor' => array(
            'pantalla',
            'display',
        ),

        'impresora' => array(
            'printer',
        ),

        'celular' => array(
            'telefono',
            'teléfono',
            'smartphone',
            'movil',
            'móvil',
        ),

        'computadora' => array(
            'pc',
            'ordenador',
            'computador',
        ),

        'disco duro' => array(
            'hard disk',
            'hard drive',
            'hdd',
        ),

        'disco ssd' => array(
            'ssd',
            'disco solido',
            'disco sólido',           
            'unidad de estado solido',
            'unidad de estado sólido',
        ),

        'memoria ram' => array(
            'ram',
            'memoria',
        ),

        'tarjeta grafica' => array(
            'tarjeta gráfica',
            'gpu',
            'graphics card',
        ),

        'fuente de poder' => array(
            'fuente',
            'fuente de alimentacion',
            'fuente de alimentación',
            'power supply',
            'psu',
        ),

        'webcam' => array(
            'camara web',
            'cámara web',
            'camara',
            'cámara',
        ),

        'ticketera' => array(
            'impresora termica',
            'impresora térmica',
            'impresora de tickets',
            'ticketera termica',
            'ticketera térmica',
        ),

        'dnie' => array(
            'dni electronico',
            'dni electrónico',
            'lector dnie',
            'lector dni electronico',
            'lector dni electrónico',
        ),

        'lector pistola' => array(
            'lector codigo de barras',
            'lector código de barras',
            'lector de codigo de barras',
            'lector de código de barras',
            'pistola lectora',
        ),

    );

    /*
     * Sinónimos personalizados guardados desde WordPress.
     */
    $sinonimos_personalizados = get_option(
        'compuciber_sinonimos_personalizados',
        array()
    );

    if (
        ! is_array( $sinonimos_personalizados )
    ) {
        $sinonimos_personalizados = array();
    }

    foreach (
        $sinonimos_personalizados
        as $principal => $variantes
    ) {

        if ( empty( $principal ) ) {
            continue;
        }

        if ( ! isset( $sinonimos_predeterminados[ $principal ] ) ) {

            $sinonimos_predeterminados[ $principal ] = array();

        }

        if ( is_array( $variantes ) ) {

            $sinonimos_predeterminados[ $principal ] =
                array_merge(
                    $sinonimos_predeterminados[ $principal ],
                    $variantes
                );
        }
    }

    foreach (
        $sinonimos_predeterminados
        as $principal => $variantes
    ) {

        $sinonimos_predeterminados[ $principal ] =
            array_values(
                array_unique(
                    $variantes
                )
            );
    }

    return $sinonimos_predeterminados;
}


/**
 * Expande una búsqueda utilizando los sinónimos.
 */
function compuciber_expandir_sinonimos( $texto ) {

    $texto_normalizado = compuciber_normalizar_texto(
        $texto
    );

    if ( empty( $texto_normalizado ) ) {
        return '';
    }

    $sinonimos = compuciber_obtener_sinonimos();

    $terminos = array(
        $texto
    );

    foreach (
        $sinonimos
        as $principal => $variantes
    ) {

        $principal_normalizado =
            compuciber_normalizar_texto(
                $principal
            );

        if (
            strpos(
                $texto_normalizado,
                $principal_normalizado
            ) !== false
        ) {

            $terminos[] = $principal;

            foreach (
                $variantes
                as $variante
            ) {

                $terminos[] = $variante;
            }

            continue;
        }

        foreach (
            $variantes
            as $variante
        ) {

            $variante_normalizado =
                compuciber_normalizar_texto(
                    $variante
                );

            if (
                strpos(
                    $texto_normalizado,
                    $variante_normalizado
                ) !== false
            ) {

                $terminos[] = $principal;

                foreach (
                    $variantes
                    as $otra_variante
                ) {

                    $terminos[] = $otra_variante;
                }

                break;
            }
        }
    }

    return implode(
        ' ',
        array_unique(
            $terminos
        )
    );
}


/**
 * Agrega un nuevo grupo de sinónimos.
 */
function compuciber_guardar_sinonimo_personalizado(
    $principal,
    $variantes
) {

    $principal = sanitize_text_field(
        $principal
    );

    $principal = trim(
        $principal
    );

    if ( empty( $principal ) ) {
        return false;
    }

    if ( ! is_array( $variantes ) ) {
        return false;
    }

    $variantes_limpias = array();

    foreach (
        $variantes
        as $variante
    ) {

        $variante = sanitize_text_field(
            $variante
        );

        $variante = trim(
            $variante
        );

        if ( ! empty( $variante ) ) {

            $variantes_limpias[] =
                $variante;
        }
    }

    if ( empty( $variantes_limpias ) ) {
        return false;
    }

    $sinonimos_personalizados = get_option(
        'compuciber_sinonimos_personalizados',
        array()
    );

    if (
        ! is_array( $sinonimos_personalizados )
    ) {

        $sinonimos_personalizados = array();
    }

    if (
        ! isset(
            $sinonimos_personalizados[ $principal ]
        )
    ) {

        $sinonimos_personalizados[ $principal ] =
            array();
    }

    $sinonimos_personalizados[ $principal ] =
        array_values(
            array_unique(
                array_merge(
                    $sinonimos_personalizados[ $principal ],
                    $variantes_limpias
                )
            )
        );

    update_option(
        'compuciber_sinonimos_personalizados',
        $sinonimos_personalizados
    );

    return true;
}


/**
 * Página administrativa de sinónimos.
 */
function compuciber_pagina_sinonimos() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if (
        isset(
            $_POST['compuciber_guardar_sinonimo']
        )
    ) {

        check_admin_referer(
            'compuciber_guardar_sinonimo'
        );

        $principal =
            isset( $_POST['principal'] )
                ? sanitize_text_field(
                    wp_unslash(
                        $_POST['principal']
                    )
                )
                : '';

        $variantes_texto =
            isset( $_POST['variantes'] )
                ? sanitize_textarea_field(
                    wp_unslash(
                        $_POST['variantes']
                    )
                )
                : '';

        $variantes =
            preg_split(
                '/[,;\n]+/',
                $variantes_texto
            );

        if (
            compuciber_guardar_sinonimo_personalizado(
                $principal,
                $variantes
            )
        ) {

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Sinónimo guardado correctamente.</p>';
            echo '</div>';

        } else {

            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>Debes ingresar un término principal y al menos un sinónimo.</p>';
            echo '</div>';
        }
    }

    if (
        isset(
            $_GET['compuciber_eliminar_sinonimo']
        )
    ) {

        $principal =
            sanitize_text_field(
                wp_unslash(
                    $_GET['compuciber_eliminar_sinonimo']
                )
            );

        check_admin_referer(
            'compuciber_eliminar_sinonimo_' . $principal
        );

        $sinonimos_personalizados =
            get_option(
                'compuciber_sinonimos_personalizados',
                array()
            );

        if (
            isset(
                $sinonimos_personalizados[ $principal ]
            )
        ) {

            unset(
                $sinonimos_personalizados[ $principal ]
            );

            update_option(
                'compuciber_sinonimos_personalizados',
                $sinonimos_personalizados
            );

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Sinónimo eliminado correctamente.</p>';
            echo '</div>';
        }
    }

    $sinonimos =
        compuciber_obtener_sinonimos();

    $sinonimos_personalizados =
        get_option(
            'compuciber_sinonimos_personalizados',
            array()
        );

    if (
        ! is_array( $sinonimos_personalizados )
    ) {

        $sinonimos_personalizados = array();
    }
    ?>

    <div class="wrap">

        <h1>Sinónimos</h1>

        <p>
            Administra los términos relacionados que utilizará
            el buscador inteligente para ampliar las búsquedas
            de productos.
        </p>

        <hr>

        <h2>Agregar nuevo término</h2>

        <form method="post">

            <?php
            wp_nonce_field(
                'compuciber_guardar_sinonimo'
            );
            ?>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        <label for="compuciber-principal">
                            Término principal
                        </label>

                    </th>

                    <td>

                        <input
                            type="text"
                            id="compuciber-principal"
                            name="principal"
                            class="regular-text"
                            placeholder="Ejemplo: ticketera"
                            required
                        >

                        <p class="description">
                            Término que representa el producto o concepto.
                        </p>

                    </td>

                </tr>

                <tr>

                    <th scope="row">

                        <label for="compuciber-variantes">
                            Sinónimos
                        </label>

                    </th>

                    <td>

                        <textarea
                            id="compuciber-variantes"
                            name="variantes"
                            rows="5"
                            class="large-text"
                            placeholder="Ejemplo: impresora térmica, impresora de tickets"
                            required
                        ></textarea>

                        <p class="description">
                            Separa los sinónimos mediante comas,
                            punto y coma o saltos de línea.
                        </p>

                    </td>

                </tr>

            </table>

            <p>

                <button
                    type="submit"
                    name="compuciber_guardar_sinonimo"
                    class="button button-primary"
                >
                    Agregar sinónimo
                </button>

            </p>

        </form>

        <hr>

        <h2>Sinónimos configurados</h2>

        <table class="widefat striped">

            <thead>

                <tr>

                    <th>
                        Término principal
                    </th>

                    <th>
                        Sinónimos
                    </th>

                    <th>
                        Tipo
                    </th>

                    <th>
                        Acción
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach (
                    $sinonimos
                    as $principal => $variantes
                ) : ?>

                    <tr>

                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    $principal
                                );
                                ?>
                            </strong>
                        </td>

                        <td>

                            <?php
                            echo esc_html(
                                implode(
                                    ', ',
                                    $variantes
                                )
                            );
                            ?>

                        </td>

                        <td>

                            <?php
                            if (
                                isset(
                                    $sinonimos_personalizados[
                                        $principal
                                    ]
                                )
                            ) {
                                echo 'Personalizado';
                            } else {
                                echo 'Predeterminado';
                            }
                            ?>

                        </td>

                        <td>

                            <?php
                            if (
                                isset(
                                    $sinonimos_personalizados[
                                        $principal
                                    ]
                                )
                            ) {

                                $url = wp_nonce_url(
                                    admin_url(
                                        'options-general.php?page=compuciber-ai-search-sinonimos'
                                    )
                                    . '&compuciber_eliminar_sinonimo='
                                    . rawurlencode(
                                        $principal
                                    ),
                                    'compuciber_eliminar_sinonimo_' .
                                    $principal
                                );
                                ?>

                                <a
                                    href="<?php echo esc_url( $url ); ?>"
                                    class="button button-small"
                                    onclick="return confirm('¿Eliminar este grupo de sinónimos personalizados?');"
                                >
                                    Eliminar
                                </a>

                                <?php
                            } else {
                                echo '—';
                            }
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <?php
}