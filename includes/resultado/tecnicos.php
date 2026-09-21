<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function compuciber_coincide_caracteristica_tecnica(
    $texto,
    $caracteristica
) {
    $texto =
        compuciber_normalizar_texto(
            $texto
        );

    $caracteristica =
        compuciber_normalizar_texto(
            $caracteristica
        );

    if (
        empty( $texto )
        || empty( $caracteristica )
    ) {
        return false;
    }

    /*
     * CARACTERÍSTICAS TÉCNICAS
     *
     * Acepta:
     *
     * 8 GB
     * 8GB
     * 16 GB
     * 16GB
     * 512 GB
     * 512GB
     * 512 GB SSD
     * 512GB SSD
     * 512 GB HDD
     * 512GB HDD
     * 1 TB SSD
     * 1TB SSD
     */

    if (
        preg_match(
            '/(?<![a-z0-9])(\d+(?:\.\d+)?)\s*(gb|tb|mb)(?:\s*(ssd|hdd))?(?![a-z0-9])/i',
            $caracteristica,
            $coincidencia
        )
    ) {

        error_log(
            'TECNICO FUNCION: '
            . 'TEXTO=' . $texto
            . ' | CARACTERISTICA=' . $caracteristica
            . ' | CANTIDAD=' . $coincidencia[1]
            . ' | UNIDAD=' . $coincidencia[2]
            . ' | TIPO='
            . (
                ! empty( $coincidencia[3] )
                    ? $coincidencia[3]
                    : ''
            )
        );    
        $cantidad =
            preg_quote(
                $coincidencia[1],
                '/'
            );

        $unidad =
            preg_quote(
                $coincidencia[2],
                '/'
            );

        $coincide_cantidad =
            preg_match(
                '/(?<![a-z0-9])'
                . $cantidad
                . '\s*'
                . $unidad
                . '(?![a-z0-9])/i',
                $texto
            ) === 1;

        if ( ! $coincide_cantidad ) {
            return false;
        }

        if (
            ! empty(
                $coincidencia[3]
            )
        ) {
            $tipo =
                compuciber_normalizar_texto(
                    $coincidencia[3]
                );

            if (
                ! preg_match(
                    '/(?<![a-z0-9])'
                    . preg_quote(
                        $tipo,
                        '/'
                    )
                    . '(?![a-z0-9])/i',
                    $texto
                )
            ) {
                return false;
            }
        }

        return true;
    }

    return false;
}