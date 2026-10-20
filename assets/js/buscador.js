document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.compuciber-ai-search').forEach(function (root) {

        const input = root.querySelector('.compuciber-search-input');

        const suggestions = root.querySelector('.compuciber-suggestions');

        if (!input || !suggestions) {
            return;
        }

        let timeout;
        let requestNumber = 0;

        let aiTimer = null;
        let ultimaTecla = 0;

        /*
         * Consultar IA solamente cuando
         * no hay sugerencias locales.
         */
        function consultarSugerenciaIA(
            term,
            currentRequest
        ) {

            if (
                !CompuciberSearch.suggestionAiNonce
            ) {
                return;
            }

            const parametros =
                new URLSearchParams({
                    action: 'compuciber_sugerencia_ia',
                    nonce:
                        CompuciberSearch.suggestionAiNonce,
                    term: term
                });

            fetch(
                CompuciberSearch.ajaxUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8'
                    },

                    body: parametros.toString()
                }
            )
                .then(
                    response => response.json()
                )
                .then(function (data) {

                    if (
                        currentRequest !== requestNumber
                        || input.value.trim() !== term
                        || !data.success
                        || !data.data
                        || typeof data.data.suggested_term
                        !== 'string'
                    ) {
                        return;
                    }

                    const propuesta =
                        data.data.suggested_term.trim();

                    if (
                        !propuesta
                        || propuesta.toLowerCase()
                        === term.toLowerCase()
                    ) {
                        return;
                    }

                    if (
                        suggestions.children.length > 0
                    ) {
                        return;
                    }

                    /*
                     * MOSTRAR CORRECCIÓN PROPUESTA
                     */
                    const boton =
                        document.createElement(
                            'button'
                        );

                    boton.type = 'button';

                    boton.className =
                        'cis-did-you-mean';

                    boton.appendChild(
                        document.createTextNode(
                            'Quizás quisiste decir: '
                        )
                    );

                    const palabra =
                        document.createElement(
                            'strong'
                        );

                    palabra.textContent =
                        propuesta;

                    boton.appendChild(
                        palabra
                    );

                    /*
                     * PERMITIR SELECCIONAR
                     * LA CORRECCIÓN.
                     */
                    boton.addEventListener(
                        'click',
                        function () {

                            input.value =
                                propuesta;

                            input.dispatchEvent(
                                new Event(
                                    'input',
                                    {
                                        bubbles: true
                                    }
                                )
                            );

                            input.focus();
                        }
                    );

                    suggestions.appendChild(
                        boton
                    );

                    /*
                     * MOSTRAR PRODUCTOS DE LA CORRECCIÓN IA
                     *
                     * Consulta el autocompletado local con
                     * la palabra corregida.
                     * No vuelve a llamar a Gemini ni OpenAI.
                     */

                    const urlProductos = new URL(
                        CompuciberSearch.ajaxUrl,
                        window.location.href
                    );

                    urlProductos.searchParams.set(
                        'action',
                        'compuciber_sugerencias'
                    );

                    urlProductos.searchParams.set(
                        'term',
                        propuesta
                    );

                    fetch(
                        urlProductos.toString(),
                        {
                            credentials: 'same-origin'
                        }
                    )
                        .then(function (respuesta) {

                            if (!respuesta.ok) {
                                throw new Error(
                                    'No se pudieron cargar sugerencias'
                                );
                            }

                            return respuesta.json();
                        })
                        .then(function (resultado) {

                            // Evitar resultados antiguos si
                            // el cliente cambió su búsqueda.
                            if (
                                currentRequest !== requestNumber ||
                                input.value.trim() !== term ||
                                !resultado.success ||
                                !Array.isArray(resultado.data)
                            ) {
                                return;
                            }

                            resultado.data.slice(0, 5).forEach(
                                function (producto) {

                                    if (
                                        !producto ||
                                        !producto.url
                                    ) {
                                        return;
                                    }

                                    const tarjeta =
                                        document.createElement('a');

                                    tarjeta.href = producto.url;

                                    tarjeta.className =
                                        'compuciber-suggestion-item compuciber-product-result';

                                    tarjeta.dataset.productId =
                                        producto.id || '';

                                    tarjeta.dataset.search = term;

                                    /*
                                     * IMAGEN
                                     */
                                    const imagen =
                                        document.createElement('img');

                                    imagen.className =
                                        'compuciber-suggestion-image';

                                    imagen.src =
                                        producto.image || '';

                                    imagen.alt =
                                        producto.title || '';

                                    /*
                                     * CONTENIDO
                                     */
                                    const contenido =
                                        document.createElement('div');

                                    contenido.className =
                                        'compuciber-suggestion-content';

                                    /*
                                     * NOMBRE
                                     */
                                    const titulo =
                                        document.createElement('div');

                                    titulo.className =
                                        'compuciber-suggestion-title';

                                    titulo.textContent =
                                        producto.title || '';

                                    contenido.appendChild(titulo);

                                    /*
                                     * ATRIBUTOS
                                     */
                                    if (
                                        Array.isArray(producto.attributes) &&
                                        producto.attributes.length > 0
                                    ) {

                                        const atributos =
                                            document.createElement('div');

                                        atributos.className =
                                            'compuciber-suggestion-attributes';

                                        atributos.textContent =
                                            producto.attributes.join(' | ');

                                        contenido.appendChild(atributos);
                                    }

                                    /*
                                     * PRECIO
                                     */
                                    const precio =
                                        document.createElement('div');

                                    precio.className =
                                        'compuciber-suggestion-price';

                                    precio.innerHTML =
                                        producto.price || '';

                                    contenido.appendChild(precio);

                                    /*
                                     * DISPONIBILIDAD
                                     */
                                    const disponibilidad =
                                        document.createElement('div');

                                    disponibilidad.className =
                                        'compuciber-suggestion-stock';

                                    disponibilidad.textContent =
                                        producto.availability || '';

                                    contenido.appendChild(disponibilidad);

                                    /*
                                     * AGREGAR TARJETA
                                     */
                                    tarjeta.appendChild(imagen);

                                    tarjeta.appendChild(contenido);

                                    suggestions.appendChild(tarjeta);
                                }
                            );
                        })
                        .catch(function (error) {

                            console.warn(
                                'No se cargaron las tarjetas de la corrección:',
                                error
                            );
                        });

                })
                .catch(function () {

                    // Si falla la IA, el buscador
                    // local continúa funcionando.

                });
        }

        /*
         * ======================================================
         * AUTOCOMPLETADO
         * ======================================================
         */

        input.addEventListener('input', function () {

            const origenVoz = input.form
                ? input.form.querySelector(
                    'input[name="cis_source"]'
                )
                : null;

            if (origenVoz) {
                origenVoz.value = 'texto';
            }

            clearTimeout(timeout);
            clearTimeout(aiTimer);

            ultimaTecla = Date.now();

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

                            /*
                             * Si el autocompletado local
                             * no encontró resultados,
                             * intentar IA de respaldo.
                             */
                            if (
                                data.success
                                && Array.isArray(data.data)
                                && data.data.length === 0
                                && CompuciberSearch.suggestionAiEnabled
                            ) {

                                /*
                                 * Esperar hasta que el
                                 * usuario deje de escribir.
                                 */
                                const espera = Math.max(
                                    0,
                                    1200 - (
                                        Date.now()
                                        - ultimaTecla
                                    )
                                );

                                aiTimer = setTimeout(
                                    function () {

                                        if (
                                            currentRequest === requestNumber
                                            && input.value.trim() === term
                                        ) {

                                            consultarSugerenciaIA(
                                                term,
                                                currentRequest
                                            );
                                        }

                                    },
                                    espera
                                );
                            }

                            return;
                        }

                        /*
                         * CORRECCIÓN SUGERIDA
                         * Sin llamadas adicionales a la IA.
                         */
                        const terminoSugerido =
                            typeof data.data[0].suggested_term === 'string'
                                ? data.data[0].suggested_term.trim()
                                : '';

                        if (
                            terminoSugerido &&
                            terminoSugerido.toLocaleLowerCase() !== term.toLocaleLowerCase()
                        ) {

                            const botonCorreccion =
                                document.createElement('button');

                            botonCorreccion.type = 'button';

                            botonCorreccion.className =
                                'cis-did-you-mean';

                            const textoCorreccion =
                                document.createElement('span');

                            textoCorreccion.textContent =
                                'Quizás quisiste decir: ';

                            const palabraCorreccion =
                                document.createElement('strong');

                            palabraCorreccion.textContent =
                                terminoSugerido;

                            botonCorreccion.appendChild(
                                textoCorreccion
                            );

                            botonCorreccion.appendChild(
                                palabraCorreccion
                            );

                            botonCorreccion.addEventListener(
                                'click',
                                function () {

                                    input.value =
                                        terminoSugerido;

                                    input.dispatchEvent(
                                        new Event('input', {
                                            bubbles: true
                                        })
                                    );

                                    input.focus();
                                }
                            );

                            suggestions.appendChild(
                                botonCorreccion
                            );
                        }

                        data.data.forEach(function (product) {

                            const item =
                                document.createElement('a');

                            item.href =
                                product.url;

                            item.className =
                                'compuciber-suggestion-item';

                            item.classList.add(
                                'compuciber-product-result'
                            );

                            item.dataset.productId =
                                product.id;

                            item.dataset.search =
                                term;

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

        const voiceButton = root.querySelector('.compuciber-voice-button');

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

                recognition.lang =
                    document.documentElement.lang ||
                    navigator.language;

                recognition.continuous = false;

                recognition.interimResults = true;

                recognition.maxAlternatives = 1;


                let escuchando = false;
                let reconocimientoActivo = false;

                let inicioVoz = 0;
                let finHabla = 0;


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

                            inicioVoz = performance.now();
                            finHabla = 0;

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
                    'speechend',
                    function () {
                        finHabla = performance.now();
                    }
                );

                recognition.addEventListener(
                    'result',
                    function (event) {
                        let texto = '';
                        let resultadoFinal = true;

                        for (
                            let i = 0;
                            i < event.results.length;
                            i++
                        ) {

                            const segmento =
                                event.results[i];

                            if (
                                !segmento ||
                                !segmento[0]
                            ) {
                                continue;
                            }

                            texto +=
                                (texto ? ' ' : '') +
                                segmento[0].transcript;

                            if (!segmento.isFinal) {
                                resultadoFinal = false;
                            }
                        }

                        texto = texto.trim();

                        if (!texto) {
                            return;
                        }

                        // Mostrar el texto provisional
                        // sin lanzar todavía la búsqueda.
                        if (!resultadoFinal) {
                            input.value = texto;
                            return;
                        }

                        const tiempoTotal = Math.round(
                            performance.now() - inicioVoz
                        );

                        console.info(
                            '[Compuciber voz] Botón a texto final:',
                            tiempoTotal + ' ms'
                        );

                        if (finHabla > 0) {
                            console.info(
                                '[Compuciber voz] Fin del habla a texto final:',
                                Math.round(
                                    performance.now() - finHabla
                                ) + ' ms'
                            );
                        }

                        voiceButton.innerHTML =
                            compuciber_icono_microfono() +
                            '<span class="compuciber-voice-text">Procesando...</span>';

                        input.value = texto;

                        /*
                        * Marca que esta búsqueda proviene de voz.
                        */
                        let voiceSource = input.form.querySelector('input[name="cis_source"]');

                        if (!voiceSource) {

                            voiceSource =
                                document.createElement('input');

                            voiceSource.type = 'hidden';

                            voiceSource.name =
                                'cis_source';

                            input.form.appendChild(
                                voiceSource
                            );
                        }

                        voiceSource.value = 'voz';

                        /*
                        * Ejecuta automáticamente la búsqueda
                        * después de reconocer la voz.
                        */
                        const formularioBusqueda =
                            input.closest('form');

                        if (formularioBusqueda) {

                            formularioBusqueda.submit();

                        }

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

                        const mensajesVoz = {
                            'not-allowed':
                                'Activa el permiso del micrófono en el navegador e inténtalo de nuevo.',

                            'service-not-allowed':
                                'Este navegador no permite el reconocimiento de voz. Puedes escribir tu búsqueda.',

                            'audio-capture':
                                'No se pudo acceder al micrófono. Revisa su conexión y permisos.',

                            'network':
                                'No se pudo conectar al servicio de voz. Revisa tu conexión o busca escribiendo.',

                            'no-speech':
                                'No se detectó voz. Intenta hablar nuevamente.',

                            'language-not-supported':
                                'Este navegador no admite el idioma configurado para la búsqueda por voz.'
                        };

                        const estadoVoz = root.querySelector(
                            '.compuciber-ai-status'
                        );

                        if (estadoVoz) {
                            estadoVoz.textContent =
                                mensajesVoz[event.error] ||
                                'La búsqueda por voz no está disponible. Puedes escribir tu búsqueda.';
                        }

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

        const imageButton = root.querySelector('.compuciber-image-button');

        const aiStatus = root.querySelector('.compuciber-ai-status');

        const imageInput = root.querySelector('.compuciber-image-input');


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

                                const tokenImagen = data.data.token;

                                if (!tokenImagen) {
                                    alert(
                                        'No se pudo validar la búsqueda por imagen.'
                                    );
                                    return;
                                }

                                let campoToken = input.form.querySelector(
                                    'input[name="cis_img_token"]'
                                );

                                if (!campoToken) {

                                    campoToken = document.createElement(
                                        'input'
                                    );

                                    campoToken.type = 'hidden';
                                    campoToken.name = 'cis_img_token';

                                    input.form.appendChild(
                                        campoToken
                                    );
                                }

                                campoToken.value = tokenImagen;


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

    }); // Fin de la inicialización de cada buscador.        
    /*
    * ======================================================
    * ESTADÍSTICAS - PRODUCTO SELECCIONADO
    * ======================================================
    */

    document.addEventListener(
        'click',
        function (event) {
            const enlace = event.target.closest(
                '.compuciber-product-result, .cis-woo-results a.woocommerce-LoopProduct-link'
            );

            if (!enlace) {
                return;
            }

            const contenedorWoo =
                enlace.closest('.cis-woo-results');

            let productoId =
                enlace.dataset.productId || '';

            if (!productoId && contenedorWoo) {

                const tarjeta =
                    enlace.closest('li.product');

                const clase =
                    tarjeta ? tarjeta.className : '';

                const idWoo = clase.match(
                    /(?:^|\s)post-(\d+)(?=\s|$)/
                );

                productoId = idWoo
                    ? idWoo[1]
                    : '';
            }

            const busqueda =
                enlace.dataset.search || (
                    contenedorWoo
                        ? contenedorWoo.dataset.search || ''
                        : ''
                );

            const origen = enlace.classList.contains(
                'compuciber-suggestion-item'
            ) ? 'autocompletado' : 'resultados';

            if (
                !productoId ||
                !CompuciberSearch.selectionNonce
            ) {
                return;
            }

            const datos =
                new FormData();

            datos.append(
                'action',
                'compuciber_registrar_seleccion'
            );

            datos.append(
                'nonce',
                CompuciberSearch.selectionNonce
            );

            datos.append(
                'producto_id',
                productoId
            );

            datos.append(
                'busqueda',
                busqueda
            );

            datos.append(
                'origen',
                origen
            );

            /*
            * Registra la selección sin bloquear
            * la navegación hacia el producto.
            */
            fetch(
                CompuciberSearch.ajaxUrl,
                {
                    method: 'POST',
                    body: datos,
                    keepalive: true
                }
            ).catch(
                function (error) {

                    console.error(
                        'No se pudo registrar la selección:',
                        error
                    );

                }
            );

        }
    );

});

