<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Widget nativo de Elementor para Compuciber AI Search.
 *
 * Elementor controla únicamente la presentación.
 * La lógica de búsqueda continúa centralizada
 * en Compuciber AI Search.
 */
class Compuciber_AI_Search_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'compuciber_ai_search';
    }

    public function get_title() {
        return 'Compuciber AI Search';
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array(
            'compuciber',
            'search',
            'buscador',
            'woocommerce',
            'productos',
            'ia',
        );
    }


    /**
     * ======================================================
     * CONTROLES
     * ======================================================
     */
    protected function register_controls() {

        /*
         * ==================================================
         * CONTENIDO
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_content_section',
            array(
                'label' => 'Buscador',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'titulo_widget',
            array(
                'label'       => 'Título',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => 'Buscador de productos',
            )
        );

        $this->end_controls_section();


        /*
         * ==================================================
         * CONTENEDOR
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_container_style',
            array(
                'label' => 'Contenedor',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'search_width',
            array(
                'label'      => 'Ancho',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', '%' ),

                'range' => array(
                    'px' => array(
                        'min' => 200,
                        'max' => 1600,
                    ),

                    '%' => array(
                        'min' => 10,
                        'max' => 100,
                    ),
                ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-ai-search' =>
                        'width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_margin',
            array(
                'label'      => 'Margen',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%', 'em' ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-ai-search' =>
                        'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();


        /*
         * ==================================================
         * CAMPO DE BÚSQUEDA
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_input_style',
            array(
                'label' => 'Campo de búsqueda',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );


        /*
         * Tipografía
         */

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'input_typography',
                'selector' =>
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]',
            )
        );


        /*
         * Color del texto
         */

        $this->add_control(
            'input_text_color',
            array(
                'label' => 'Color del texto',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]' =>
                        'color: {{VALUE}};',
                ),
            )
        );


        /*
         * Placeholder
         */

        $this->add_control(
            'placeholder_color',
            array(
                'label' => 'Color del placeholder',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]::placeholder' =>
                        'color: {{VALUE}};',
                ),
            )
        );


        /*
         * Fondo
         */

        $this->add_control(
            'input_background',
            array(
                'label' => 'Color de fondo',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]' =>
                        'background-color: {{VALUE}};',
                ),
            )
        );


        /*
         * Altura
         */

        $this->add_responsive_control(
            'input_height',
            array(
                'label'      => 'Altura',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),

                'range' => array(
                    'px' => array(
                        'min' => 30,
                        'max' => 120,
                    ),
                ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]' =>
                        'height: {{SIZE}}{{UNIT}};',
                ),
            )
        );


        /*
         * Padding
         */

        $this->add_responsive_control(
            'input_padding',
            array(
                'label'      => 'Espaciado interno',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );


        /*
         * Borde
         */

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'input_border',
                'selector' =>
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]',
            )
        );


        /*
         * Radio
         */

        $this->add_responsive_control(
            'input_radius',
            array(
                'label'      => 'Radio del borde',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box input[type="search"]' =>
                        'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();


        /*
         * ==================================================
         * BOTÓN BUSCAR
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_search_button_style',
            array(
                'label' => 'Botón Buscar',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );


        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'search_button_typography',
                'selector' =>
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]',
            )
        );


        /*
         * NORMAL / HOVER
         */

        $this->start_controls_tabs(
            'search_button_tabs'
        );


        /*
         * NORMAL
         */

        $this->start_controls_tab(
            'search_button_normal',
            array(
                'label' => 'Normal',
            )
        );

        $this->add_control(
            'search_button_background',
            array(
                'label' => 'Fondo',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]' =>
                        'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_button_color',
            array(
                'label' => 'Texto',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]' =>
                        'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();


        /*
         * HOVER
         */

        $this->start_controls_tab(
            'search_button_hover',
            array(
                'label' => 'Hover',
            )
        );

        $this->add_control(
            'search_button_background_hover',
            array(
                'label' => 'Fondo',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]:hover' =>
                        'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_button_color_hover',
            array(
                'label' => 'Texto',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]:hover' =>
                        'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();


        /*
         * Borde
         */

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'search_button_border',
                'selector' =>
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]',
            )
        );


        /*
         * Radio
         */

        $this->add_responsive_control(
            'search_button_radius',
            array(
                'label'      => 'Radio del borde',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]' =>
                        'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );


        /*
         * Padding
         */

        $this->add_responsive_control(
            'search_button_padding',
            array(
                'label'      => 'Espaciado interno',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em' ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box button[type="submit"]' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();


        /*
         * ==================================================
         * VOZ E IMAGEN
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_action_buttons_style',
            array(
                'label' => 'Voz e imagen',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );


        $this->add_control(
            'action_background',
            array(
                'label' => 'Color de fondo',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button, {{WRAPPER}} #compuciber-image-button' =>
                        'background-color: {{VALUE}};',
                ),
            )
        );


        $this->add_control(
            'action_icon_color',
            array(
                'label' => 'Color del icono',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button svg, {{WRAPPER}} #compuciber-image-button svg' =>
                        'fill: {{VALUE}};',
                ),
            )
        );


        $this->add_control(
            'action_background_hover',
            array(
                'label' => 'Fondo al pasar el mouse',
                'type'  => \Elementor\Controls_Manager::COLOR,

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button:hover, {{WRAPPER}} #compuciber-image-button:hover' =>
                        'background-color: {{VALUE}};',
                ),
            )
        );


        $this->add_responsive_control(
            'action_button_size',
            array(
                'label'      => 'Tamaño del botón',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),

                'range' => array(
                    'px' => array(
                        'min' => 25,
                        'max' => 100,
                    ),
                ),

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button, {{WRAPPER}} #compuciber-image-button' =>
                        'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );


        $this->add_responsive_control(
            'action_icon_size',
            array(
                'label'      => 'Tamaño del icono',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),

                'range' => array(
                    'px' => array(
                        'min' => 10,
                        'max' => 60,
                    ),
                ),

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button svg, {{WRAPPER}} #compuciber-image-button svg' =>
                        'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );


        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'action_border',
                'selector' =>
                    '{{WRAPPER}} #compuciber-voice-button, {{WRAPPER}} #compuciber-image-button',
            )
        );
        


        $this->add_responsive_control(
            'action_radius',
            array(
                'label'      => 'Radio del borde',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),

                'selectors' => array(
                    '{{WRAPPER}} #compuciber-voice-button, {{WRAPPER}} #compuciber-image-button' =>
                        'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();


        /*
         * ==================================================
         * ESPACIADO ENTRE ELEMENTOS
         * ==================================================
         */

        $this->start_controls_section(
            'compuciber_spacing_style',
            array(
                'label' => 'Distribución',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );


        $this->add_responsive_control(
            'elements_gap',
            array(
                'label'      => 'Separación',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),

                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),

                'selectors' => array(
                    '{{WRAPPER}} .compuciber-search-box' =>
                        'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }


    /**
     * ======================================================
     * RENDER
     * ======================================================
     */
    protected function render() {

        $settings =
            $this->get_settings_for_display();

        if (
            ! empty(
                $settings['titulo_widget']
            )
        ) {

            echo '<div class="compuciber-elementor-title">';

            echo esc_html(
                $settings['titulo_widget']
            );

            echo '</div>';
        }


        /*
         * Reutilizamos el buscador original.
         * Elementor no duplica su lógica.
         */
        echo do_shortcode(
            '[compuciber_ai_search]'
        );
    }
}