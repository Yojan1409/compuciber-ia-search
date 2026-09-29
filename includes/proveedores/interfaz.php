<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contrato base para proveedores de inteligencia artificial.
 *
 * Cualquier proveedor futuro deberá implementar
 * estos métodos básicos.
 */
interface Compuciber_AIProviderInterface {

    /**
     * Consulta de texto.
     *
     * @param string $busqueda Texto introducido por el usuario.
     * @return array|false
     */
    public function consultar_texto( $busqueda );

    /**
     * Consulta mediante imagen.
     *
     * @param string $imagen_base64 Imagen codificada en base64.
     * @param string $mime_type Tipo MIME de la imagen.
     * @return array|false
     */
    public function consultar_imagen(
        $imagen_base64,
        $mime_type
    );

    /**
     * Genera un embedding a partir de un texto.
     *
     * @param string $texto Texto que se desea representar.
     * @param string $tipo  Tipo de contenido: producto o consulta.
     * @return array|false
     */
    public function generar_embedding(
        $texto,
        $tipo = 'producto'
    );

}