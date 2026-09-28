document.addEventListener('DOMContentLoaded', function () {

    const input = document.getElementById(
        'compuciber-search-input'
    );

    const suggestions = document.getElementById(
        'compuciber-suggestions'
    );

    if (!input || !suggestions) {
        return;
    }

    let timeout;
    let requestNumber = 0;


    /*
     * ======================================================
     * AUTOCOMPLETADO
     * ======================================================
     */

    input.addEventListener('input', function () {

        clearTimeout(timeout);

        const term = input.value.trim();

        requestNumber++;

        const currentRequest = requestNumber;

        suggestions.innerHTML = '';

        if (term.length < 2) {
            return;
        }

        timeout = setTimeout(function () {

            const url =
                CompuciberSearch.ajaxUrl +
                '?action=compuciber_sugerencias' +
                '&term=' +
                encodeURIComponent(term);

            fetch(url)
                .then(response => response.json())
                .then(data => {

                    if (
                        currentRequest !== requestNumber
                    ) {
                        return;
                    }

                    if (
                        input.value.trim() !== term
                    ) {
                        return;
                    }

                    suggestions.innerHTML = '';

                    if (
                        !data.success ||
                        !Array.isArray(data.data) ||
                        data.data.length === 0
                    ) {
                        return;
                    }

                    data.data.forEach(function (product) {

                        const item =
                            document.createElement('a');

                        item.href =
                            product.url;

                        item.className =
                            'compuciber-suggestion-item';


                        /*
                        * ==================================================
                        * IMAGEN
                        * ==================================================
                        */

                        const image =
                            document.createElement('img');

                        image.src =
                            product.image;

                        image.alt =
                            product.title;

                        image.className =
                            'compuciber-suggestion-image';


                        /*
                        * ==================================================
                        * CONTENIDO
                        * ==================================================
                        */

                        const content =
                            document.createElement('div');

                        content.className =
                            'compuciber-suggestion-content';


                        /*
                        * Nombre del producto.
                        */

                        const title =
                            document.createElement('div');

                        title.className =
                            'compuciber-suggestion-title';

                        title.textContent =
                            product.title;


                        /*
                        * Atributos.
                        */

                        const attributes =
                            document.createElement('div');

                        attributes.className =
                            'compuciber-suggestion-attributes';

                        if (
                            Array.isArray(
                                product.attributes
                            ) &&
                            product.attributes.length > 0
                        ) {

                            attributes.textContent =
                                product.attributes.join(
                                    ' | '
                                );

                        }


                        /*
                        * Precio.
                        */

                        const price =
                            document.createElement('div');

                        price.className =
                            'compuciber-suggestion-price';

                        price.innerHTML =
                            product.price || '';


                        /*
                        * Disponibilidad.
                        */

                        const availability =
                            document.createElement('div');

                        availability.className =
                            'compuciber-suggestion-stock';

                        availability.textContent =
                            product.availability;


                        /*
                        * Construcción de la tarjeta.
                        */

                        content.appendChild(
                            title
                        );

                        if (
                            attributes.textContent
                        ) {

                            content.appendChild(
                                attributes
                            );
                        }

                        content.appendChild(
                            price
                        );

                        content.appendChild(
                            availability
                        );


                        item.appendChild(
                            image
                        );

                        item.appendChild(
                            content
                        );


                        suggestions.appendChild(
                            item
                        );

                    });

                })
                .catch(error => {

                    console.error(
                        'Error en búsqueda:',
                        error
                    );

                });

        }, 300);
    });


    /*
     * ======================================================
     * BÚSQUEDA POR VOZ
     * ======================================================
     */

    const voiceButton =
        document.getElementById(
            'compuciber-voice-button'
        );

    function compuciber_icono_microfono() {

        return `
            <svg 
                class="compuciber-mic-svg"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
            <path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/>
            <path d="M19 11h-2a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21H8v2h8v-2h-3v-3.08A7 7 0 0 0 19 11z"/>
            </svg>
        `;

    }


    if (voiceButton) {
        voiceButton.innerHTML =
            compuciber_icono_microfono();

        const SpeechRecognition =
            window.SpeechRecognition ||
            window.webkitSpeechRecognition;


        if (!SpeechRecognition) {

            voiceButton.style.display = 'none';

            console.warn(
                'Este navegador no admite búsqueda por voz.'
            );

        } else {

            const recognition =
                new SpeechRecognition();

            recognition.lang = 'es-PE';

            recognition.continuous = false;

            recognition.interimResults = false;

            recognition.maxAlternatives = 1;


            let escuchando = false;
            let reconocimientoActivo = false;
            const estadoVoz =
                document.getElementById(
                    'compuciber-voice-status'
                );


            /*
             * ==================================================
             * BOTÓN DEL MICRÓFONO
             * ==================================================
             */

            voiceButton.addEventListener(
                'click',
                function () {

                    if (escuchando || reconocimientoActivo) {

                        console.log(
                            'El reconocimiento ya está iniciado.'
                        );

                        return;
                    }

                    try {

                        escuchando = true;
                        reconocimientoActivo = true;

                        voiceButton.innerHTML =
                            compuciber_icono_microfono();

                        voiceButton.classList.add(
                            'compuciber-voice-listening'
                        );

                        voiceButton.innerHTML =
                            compuciber_icono_microfono() +
                            '<span class="compuciber-voice-text">Escuchando...</span>';

                        voiceButton.setAttribute(
                            'aria-label',
                            'Escuchando...'
                        );

                        console.log(
                            'Iniciando reconocimiento de voz...'
                        );

                        recognition.start();

                    } catch (error) {

                        console.error(
                            'No se pudo iniciar el reconocimiento de voz:',
                            error
                        );

                        escuchando = false;

                        voiceButton.innerHTML =
                            compuciber_icono_microfono();
                        reconocimientoActivo = false;

                        voiceButton.classList.remove(
                            'compuciber-voice-listening'
                        );

                        voiceButton.setAttribute(
                            'aria-label',
                            'Buscar por voz'
                        );

                    }

                }
            );


            /*
             * ==================================================
             * RESULTADO DEL RECONOCIMIENTO
             * ==================================================
             */

            recognition.addEventListener(
                'result',
                function (event) {

                    const texto =
                        event.results[0][0].transcript;

                    console.log(
                        'Texto reconocido:',
                        texto
                    );
                    voiceButton.innerHTML =
                        compuciber_icono_microfono() +
                        '<span class="compuciber-voice-text">Procesando...</span>';

                    input.value = texto;

                    /*
                    * Marca que esta búsqueda proviene de voz.
                    */
                    let voiceSource =
                        document.getElementById(
                            'compuciber-search-source'
                        );

                    if (!voiceSource) {

                        voiceSource =
                            document.createElement('input');

                        voiceSource.type = 'hidden';
                        voiceSource.id =
                            'compuciber-search-source';

                        voiceSource.name =
                            'cis_source';

                        input.form.appendChild(
                            voiceSource
                        );
                    }

                    voiceSource.value = 'voz';

                    input.dispatchEvent(
                        new Event(
                            'input',
                            {
                                bubbles: true
                            }
                        )
                    );

                }
            );


            /*
             * ==================================================
             * FINALIZACIÓN
             * ==================================================
             */

            recognition.addEventListener(
                'end',
                function () {

                    console.log(
                        'Reconocimiento de voz finalizado.'
                    );

                    escuchando = false;

                    reconocimientoActivo = false;

                    voiceButton.innerHTML =
                        compuciber_icono_microfono();
                    voiceButton.classList.remove(
                        'compuciber-voice-listening'
                    );
                    voiceButton.innerHTML =
                        compuciber_icono_microfono();

                    voiceButton.setAttribute(
                        'aria-label',
                        'Buscar por voz'
                    );

                    voiceButton.disabled = false;

                }
            );


            /*
             * ==================================================
             * ERRORES
             * ==================================================
             */

            recognition.addEventListener(
                'error',
                function (event) {

                    console.error(
                        'Error de reconocimiento de voz:',
                        event.error
                    );

                    escuchando = false;
                    reconocimientoActivo = false;

                    voiceButton.innerHTML =
                        compuciber_icono_microfono();
                    voiceButton.classList.remove(
                        'compuciber-voice-listening'
                    );
                    voiceButton.innerHTML =
                        compuciber_icono_microfono();

                    voiceButton.setAttribute(
                        'aria-label',
                        'Buscar por voz'
                    );

                    voiceButton.disabled = false;

                }
            );

        }

    }

    function compuciber_icono_camara() {

        return `
        <svg 
            class="compuciber-camera-svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M9 4l2-2h2l2 2h3a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h3zm3 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
        </svg>
    `;

    }
    /*
     * ======================================================
     * BÚSQUEDA POR IMAGEN
     * ======================================================
     */

    const imageButton =
        document.getElementById(
            'compuciber-image-button'
        );
    const aiStatus =
        document.getElementById(
            'compuciber-ai-status'
        );

    const imageInput =
        document.getElementById(
            'compuciber-image-input'
        );


    if (imageButton && imageInput) {

        imageButton.addEventListener(
            'click',
            function () {

                imageInput.click();

            }
        );


        imageInput.addEventListener(
            'change',
            function () {

                if (
                    !imageInput.files ||
                    !imageInput.files.length
                ) {
                    return;
                }


                const archivo =
                    imageInput.files[0];


                /*
                 * Estado de Gemini mientras
                 * analiza la fotografía.
                 */

                imageButton.innerHTML = '⏳';
                imageButton.disabled = true;

                if (aiStatus) {
                    aiStatus.textContent =
                        'Analizando imagen con IA...';
                }

                imageButton.classList.add(
                    'compuciber-image-loading'
                );


                const formulario =
                    new FormData();

                formulario.append(
                    'action',
                    'compuciber_busqueda_imagen'
                );

                formulario.append(
                    'nonce',
                    CompuciberSearch.imageNonce
                );

                formulario.append(
                    'imagen',
                    archivo
                );


                fetch(
                    CompuciberSearch.ajaxUrl,
                    {
                        method: 'POST',
                        body: formulario
                    }
                )

                    .then(
                        response => response.json()
                    )

                    .then(
                        data => {

                            if (!data.success) {

                                console.error(
                                    'Error en búsqueda por imagen:',
                                    data.data
                                );

                                alert(
                                    data.data ||
                                    'No se pudo analizar la imagen.'
                                );

                                return;
                            }


                            const busqueda =
                                data.data.busqueda;

                            const interpretacion =
                                data.data.interpretacion;

                            if (aiStatus && interpretacion) {

                                aiStatus.textContent =
                                    'Producto detectado: ' +
                                    (
                                        interpretacion.producto ||
                                        'producto'
                                    );

                            }


                            if (!busqueda) {

                                alert(
                                    'No se pudo identificar el producto.'
                                );

                                return;
                            }


                            console.log(
                                'Búsqueda generada por imagen:',
                                busqueda
                            );

                            console.log(
                                'Interpretación de Gemini:',
                                interpretacion
                            );

                            input.value =
                                busqueda;


                            /*
                             * Guarda la interpretación completa
                             * de Gemini para que PHP pueda utilizarla
                             * sin volver a consultar la IA.
                             */

                            let campoIA =
                                document.getElementById(
                                    'compuciber-ai-interpretacion'
                                );


                            if (!campoIA) {

                                campoIA =
                                    document.createElement(
                                        'input'
                                    );

                                campoIA.type = 'hidden';

                                campoIA.id =
                                    'compuciber-ai-interpretacion';

                                campoIA.name =
                                    'cis_ai';

                                const formularioBusqueda =
                                    input.closest('form');

                                if (
                                    formularioBusqueda
                                ) {

                                    formularioBusqueda.appendChild(
                                        campoIA
                                    );

                                }

                            }


                            if (campoIA) {

                                campoIA.value =
                                    JSON.stringify(
                                        interpretacion || {}
                                    );

                            }


                            /*
                             * Envia automáticamente
                             * la búsqueda.
                             */

                            const formularioBusqueda =
                                input.closest('form');


                            if (
                                formularioBusqueda
                            ) {

                                formularioBusqueda.submit();

                            }

                        }
                    )

                    .catch(
                        error => {

                            console.error(
                                'Error de conexión:',
                                error
                            );

                            alert(
                                'Ocurrió un error al procesar la imagen.'
                            );

                        }
                    )

                    .finally(
                        function () {

                            imageButton.innerHTML =
                                compuciber_icono_camara();

                            imageButton.classList.remove(
                                'compuciber-image-loading'
                            );

                            imageButton.disabled =
                                false;

                            imageInput.value =
                                '';

                            if (aiStatus) {
                                aiStatus.textContent = '';
                            }

                        }
                    );

            }
        );

    }

});