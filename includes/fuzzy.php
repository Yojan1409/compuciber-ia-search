<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function compuciber_fuzzy_distancia( $a, $b ) {

    $a = compuciber_normalizar_texto( $a );
    $b = compuciber_normalizar_texto( $b );

    if ( $a === $b ) {
        return 0;
    }

    if ( empty( $a ) || empty( $b ) ) {
        return max(
            strlen( $a ),
            strlen( $b )
        );
    }

    return levenshtein( $a, $b );
}

function compuciber_fuzzy_similitud( $a, $b ) {

    $a = compuciber_normalizar_texto( $a );
    $b = compuciber_normalizar_texto( $b );

    if ( empty( $a ) || empty( $b ) ) {
        return 0;
    }

    if ( $a === $b ) {
        return 100;
    }

    similar_text(
        $a,
        $b,
        $porcentaje
    );

    return $porcentaje;
}

function compuciber_fuzzy_coincide( $busqueda, $texto ) {

    $palabras_busqueda =
        compuciber_obtener_palabras(
            $busqueda
        );

    $palabras_texto =
        compuciber_obtener_palabras(
            $texto
        );

    if (
        empty( $palabras_busqueda )
        || empty( $palabras_texto )
    ) {
        return false;
    }

    foreach (
        $palabras_busqueda
        as $palabra_busqueda
    ) {

        foreach (
            $palabras_texto
            as $palabra_texto
        ) {

            $distancia =
                compuciber_fuzzy_distancia(
                    $palabra_busqueda,
                    $palabra_texto
                );

            $longitud =
                max(
                    strlen( $palabra_busqueda ),
                    strlen( $palabra_texto )
                );

            if ( $longitud <= 3 ) {
                $max_distancia = 1;
            } elseif ( $longitud <= 6 ) {
                $max_distancia = 2;
            } else {
                $max_distancia = 3;
            }

            if (
                $distancia <= $max_distancia
            ) {
                return true;
            }

            $similitud =
                compuciber_fuzzy_similitud(
                    $palabra_busqueda,
                    $palabra_texto
                );

            if ( $similitud >= 75 ) {
                return true;
            }
        }
    }

    return false;
}

function compuciber_fuzzy_puntuacion(
    $busqueda,
    $texto
) {

    $palabras_busqueda =
        compuciber_obtener_palabras(
            $busqueda
        );

    $palabras_texto =
        compuciber_obtener_palabras(
            $texto
        );

    if (
        empty( $palabras_busqueda )
        || empty( $palabras_texto )
    ) {
        return 0;
    }

    $puntuacion = 0;

    foreach (
        $palabras_busqueda
        as $palabra_busqueda
    ) {

        $mejor_similitud = 0;

        foreach (
            $palabras_texto
            as $palabra_texto
        ) {

            $similitud =
                compuciber_fuzzy_similitud(
                    $palabra_busqueda,
                    $palabra_texto
                );

            if (
                $similitud >
                $mejor_similitud
            ) {

                $mejor_similitud = $similitud;

                error_log(
                    'FUZZY COMPARACION: '
                    . $palabra_busqueda
                    . ' VS '
                    . $palabra_texto
                    . ' = '
                    . $similitud
                );
            }
        }

        if ( $mejor_similitud >= 90 ) {

            $puntuacion += 4;

        } elseif ( $mejor_similitud >= 80 ) {

            $puntuacion += 3;

        } elseif ( $mejor_similitud >= 75 ) {

            $puntuacion += 2;
        }
    }

    return $puntuacion;
}