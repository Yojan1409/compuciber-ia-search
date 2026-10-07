<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * ==========================================================
 * PÁGINA DE CORRECCIONES
 * ==========================================================
 */

function compuciber_pagina_correcciones() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $mensaje = '';
    $tipo_mensaje = 'success';


    /*
     * ======================================================
     * GUARDAR CORRECCIONES
     * ======================================================
     */

    if (
        isset( $_POST['compuciber_guardar_correccion'] )
        && isset( $_POST['compuciber_correcciones_nonce'] )
        && wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['compuciber_correcciones_nonce']
                )
            ),
            'compuciber_guardar_correccion'
        )
    ) {

        $incorrectas = isset(
            $_POST['compuciber_palabras_incorrectas']
        )
            ? sanitize_textarea_field(
                wp_unslash(
                    $_POST['compuciber_palabras_incorrectas']
                )
            )
            : '';

        $correcta = isset(
            $_POST['compuciber_palabra_correcta']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['compuciber_palabra_correcta']
                )
            )
            : '';


        /*
         * Permite separar palabras mediante:
         *
         * coma
         * punto y coma
         * salto de línea
         */
        $lista_incorrectas = preg_split(
            '/[,;\r\n]+/u',
            $incorrectas
        );

        $guardadas = 0;

        if (
            is_array( $lista_incorrectas )
            && trim( $correcta ) !== ''
        ) {

            foreach ( $lista_incorrectas as $incorrecta ) {

                $incorrecta = trim( $incorrecta );

                if ( $incorrecta === '' ) {
                    continue;
                }

                if (
                    compuciber_guardar_correccion_personalizada(
                        $incorrecta,
                        $correcta
                    )
                ) {
                    $guardadas++;
                }
            }
        }


        if ( $guardadas > 0 ) {

            $mensaje = sprintf(
                _n(
                    '%d corrección guardada correctamente.',
                    '%d correcciones guardadas correctamente.',
                    $guardadas,
                    'compuciber-ai-search'
                ),
                $guardadas
            );

        } else {

            $mensaje =
                'No se pudo guardar la corrección. '
                . 'Verifica los datos ingresados.';

            $tipo_mensaje = 'error';
        }
    }


    /*
     * ======================================================
     * ELIMINAR CORRECCIÓN
     * ======================================================
     */

    if (
        isset( $_POST['compuciber_eliminar_correccion'] )
        && isset( $_POST['compuciber_eliminar_correccion_nonce'] )
    ) {

        $incorrecta = isset(
            $_POST['compuciber_incorrecta']
        )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['compuciber_incorrecta']
                )
            )
            : '';


        if (
            wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            'compuciber_eliminar_correccion_nonce'
                        ]
                    )
                ),
                'compuciber_eliminar_correccion_'
                    . $incorrecta
            )
        ) {

            if (
                compuciber_eliminar_correccion_personalizada(
                    $incorrecta
                )
            ) {

                $mensaje =
                    'La corrección se eliminó correctamente.';

            } else {

                $mensaje =
                    'No se pudo eliminar la corrección.';

                $tipo_mensaje = 'error';
            }
        }
    }


    /*
     * ======================================================
     * OBTENER CORRECCIONES PERSONALIZADAS
     * ======================================================
     */

    $correcciones = get_option(
        'compuciber_correcciones_personalizadas',
        array()
    );

    if ( ! is_array( $correcciones ) ) {
        $correcciones = array();
    }


    /*
     * Agrupamos por palabra correcta.
     *
     * Ejemplo:
     *
     * inpresora  => impresora
     * impresorra => impresora
     *
     * Se mostrará:
     *
     * impresora | inpresora, impresorra
     */
    $correcciones_agrupadas = array();

    foreach ( $correcciones as $incorrecta => $correcta ) {

        $correcta = trim( $correcta );
        $incorrecta = trim( $incorrecta );

        if (
            $correcta === ''
            || $incorrecta === ''
        ) {
            continue;
        }

        if (
            ! isset(
                $correcciones_agrupadas[ $correcta ]
            )
        ) {
            $correcciones_agrupadas[ $correcta ] =
                array();
        }

        $correcciones_agrupadas[
            $correcta
        ][] = $incorrecta;
    }


    ksort(
        $correcciones_agrupadas,
        SORT_NATURAL | SORT_FLAG_CASE
    );

    ?>

    <div class="wrap">

        <h1>Correcciones de búsqueda</h1>

        <p>
            Añade errores de escritura frecuentes y la palabra
            correcta que debe utilizar el buscador.
        </p>


        <?php if ( $mensaje !== '' ) : ?>

            <div
                class="notice notice-<?php
                echo esc_attr( $tipo_mensaje );
                ?> is-dismissible"
            >
                <p>
                    <?php echo esc_html( $mensaje ); ?>
                </p>
            </div>

        <?php endif; ?>


        <hr>


        <h2>Añadir corrección</h2>

        <form method="post">

            <?php
            wp_nonce_field(
                'compuciber_guardar_correccion',
                'compuciber_correcciones_nonce'
            );
            ?>

            <table class="form-table">

                <tr>

                    <th scope="row">

                        <label
                            for="compuciber_palabras_incorrectas"
                        >
                            Palabras incorrectas
                        </label>

                    </th>

                    <td>

                        <textarea
                            id="compuciber_palabras_incorrectas"
                            name="compuciber_palabras_incorrectas"
                            rows="4"
                            class="large-text"
                            placeholder="inpresora, impresorra, imprezora"
                            required
                        ></textarea>

                        <p class="description">
                            Puedes introducir varias palabras
                            separadas por comas, punto y coma
                            o saltos de línea.
                        </p>

                    </td>

                </tr>


                <tr>

                    <th scope="row">

                        <label
                            for="compuciber_palabra_correcta"
                        >
                            Corrección
                        </label>

                    </th>

                    <td>

                        <input
                            type="text"
                            id="compuciber_palabra_correcta"
                            name="compuciber_palabra_correcta"
                            class="regular-text"
                            placeholder="impresora"
                            required
                        >

                        <p class="description">
                            Todas las palabras anteriores serán
                            reemplazadas por esta palabra durante
                            la búsqueda.
                        </p>

                    </td>

                </tr>

            </table>


            <?php

            submit_button(
                'Añadir corrección',
                'primary',
                'compuciber_guardar_correccion'
            );

            ?>

        </form>


        <hr>


        <h2>Correcciones personalizadas</h2>


        <?php if ( empty( $correcciones_agrupadas ) ) : ?>

            <p>
                Todavía no hay correcciones personalizadas.
            </p>

        <?php else : ?>

            <table
                class="widefat striped"
                style="max-width:1000px;"
            >

                <thead>

                    <tr>

                        <th>
                            Corrección
                        </th>

                        <th>
                            Palabras incorrectas
                        </th>

                        <th style="width:220px;">
                            Acción
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $correcciones_agrupadas
                        as $correcta => $incorrectas
                    ) : ?>

                        <tr>

                            <td>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $correcta
                                    );
                                    ?>
                                </strong>

                            </td>


                            <td>

                                <?php
                                echo esc_html(
                                    implode(
                                        ', ',
                                        $incorrectas
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <?php foreach (
                                    $incorrectas
                                    as $incorrecta
                                ) : ?>

                                    <form
                                        method="post"
                                        action="<?php
                                        echo esc_url(
                                            admin_url(
                                                'admin.php?page='
                                                . 'compuciber-ai-search-correcciones'
                                            )
                                        );
                                        ?>"
                                        style="
                                            display:inline-block;
                                            margin:2px;
                                        "
                                    >

                                        <?php
                                        wp_nonce_field(
                                            'compuciber_eliminar_correccion_'
                                                . $incorrecta,
                                            'compuciber_eliminar_correccion_nonce'
                                        );
                                        ?>

                                        <input
                                            type="hidden"
                                            name="compuciber_incorrecta"
                                            value="<?php
                                            echo esc_attr(
                                                $incorrecta
                                            );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="compuciber_eliminar_correccion"
                                            value="1"
                                            class="button button-small"
                                            onclick="return confirm(
                                                '¿Eliminar esta corrección?'
                                            );"
                                        >
                                            Eliminar
                                            <?php
                                            echo esc_html(
                                                $incorrecta
                                            );
                                            ?>
                                        </button>

                                    </form>

                                <?php endforeach; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <?php
}