<?php

return [
    'names' => [
        'Lucía Mendoza', 'Carlos Quispe', 'Ana Torres', 'Miguel Salazar', 'Rosa Paredes',
        'Jorge Huamán', 'Valeria Rojas', 'Luis Castro', 'Elena Vargas', 'Pedro Flores',
        'Camila Sánchez', 'Diego Ramos', 'Patricia León', 'Renato Silva', 'María Chávez',
        'Andrés Medina', 'Gabriela Soto', 'Fernando Cárdenas', 'Claudia Peña', 'Oscar Herrera',
    ],
    'posts' => [
        ['kind' => 'need_help', 'body' => '¿Alguien sabe cómo cambiar una llanta? Mi carro está estacionado y tengo la de repuesto, pero nunca he usado la gata. ¿Algún vecino me orienta? #mecanica #autos #coche #carro', 'categories' => ['Mecánico', 'Autos', 'Mecánica', 'Coche', 'Carro'], 'comments' => [
            [1, 'Sí, vecina, trabajo en un taller. Primero podemos revisar la llanta ahí mismo; si necesita reparación puedes traerla al taller, te hago precio de vecino.'],
            [3, 'Justo estoy practicando en el taller de mi tío. Si el carro está seguro y estacionado puedo acercarme con él y ayudarte sin costo.'],
        ]],
        ['kind' => 'need_help', 'body' => 'Mi lavadero gotea aunque cierro bien la llave. ¿Conocen a alguien del barrio que haga gasfitería? Quisiera arreglarlo y dejar de desperdiciar agua. #gasfiteria #reparaciones', 'categories' => ['Gasfitería', 'Hogar'], 'comments' => [
            [5, 'Yo hago esos trabajos. Podemos coordinar una revisión y te explico qué repuesto hace falta antes de cotizar.'],
            [8, 'A mí me pasó algo parecido. Tengo el contacto de un vecino que lo solucionó; te lo paso por privado si quieres.'],
        ]],
        ['kind' => 'offer_help', 'body' => 'Soy profe de matemáticas y este sábado puedo apoyar gratis a chicos de secundaria con fracciones y ecuaciones. Podemos juntarnos en el local comunal. #clases #matematicas #apoyoescolar', 'categories' => ['Clases', 'Educación'], 'comments' => [
            [4, '¡Qué buena iniciativa! Mi hija está repasando fracciones. ¿Podría sumarse con su cuaderno?'],
            [10, 'Yo puedo ayudar a preparar ejercicios y llevar hojas para practicar.'],
        ]],
        ['kind' => 'collaboration', 'body' => 'Estamos reuniendo cuadernos y útiles en buen estado para familias del barrio. Si les sobró material del año pasado podemos organizar una entrega entre vecinos. #solidaridad #educacion #colaboracion', 'categories' => ['Solidaridad', 'Educación'], 'comments' => [
            [6, 'Tengo varios cuadernos nuevos y colores completos. Me sumo a la colecta.'],
            [12, 'Puedo ayudar a clasificar las donaciones el sábado por la tarde.'],
        ]],
        ['kind' => 'need_help', 'body' => 'Mi bicicleta se quedó sin freno trasero. ¿Hay algún vecino que sepa ajustarlo o recomiende un taller cercano? Por ahora la tengo guardada. #bicicletas #reparaciones', 'categories' => ['Bicicletas', 'Taller'], 'comments' => [
            [7, 'Arreglo bicicletas. Puedes traerla a revisar el cable y las zapatas; te doy presupuesto antes de cambiar algo.'],
            [15, 'Tengo herramientas para bici y puedo acompañarte al taller caminando.'],
        ]],
        ['kind' => 'initiative', 'body' => '¿Qué les parece recuperar las jardineras del parque? Podríamos empezar con una sola este fin de semana y organizarnos para regarla. #jardineria #parque #voluntariado', 'categories' => ['Jardinería', 'Voluntariado'], 'comments' => [
            [14, 'Tengo esquejes y un poco de compost para compartir. ¡Cuenten conmigo!'],
            [17, 'Puedo prestar una pala y llevar bolsas para recoger lo que encontremos.'],
        ]],
        ['kind' => 'offer_help', 'body' => 'Si algún adulto mayor necesita aprender a hacer videollamadas o configurar el tamaño de letra del celular, puedo explicarle con paciencia en el local comunal. #tecnologia #apoyovecinal', 'categories' => ['Tecnología', 'Adultos mayores'], 'comments' => [
            [9, 'A mi papá le vendría muy bien. ¿Podemos coordinar una tarde?'],
            [18, 'También puedo ayudar con Android. Hagamos una pequeña jornada entre varios.'],
        ]],
        ['kind' => 'need_help', 'body' => '¿Alguien me presta un taladro por una hora para colocar una repisa? Tengo las brocas y devolvería todo limpio. Si no sé hacerlo prefiero que me acompañen. #herramientas #prestamo #hogar', 'categories' => ['Herramientas', 'Hogar'], 'comments' => [
            [11, 'Yo tengo uno. Podemos coordinar para ayudarte a revisar dónde perforar antes.'],
            [19, 'También puedo prestarte un nivel para que la repisa quede derechita.'],
        ]],
        ['kind' => 'activity', 'title' => 'Intercambio de libros del barrio', 'location' => 'Local comunal · punto por confirmar entre participantes', 'body' => 'Trae un libro que ya leíste y llévate una nueva historia. También podemos preparar un rincón de lectura para niñas y niños. #libros #intercambio #comunidad', 'categories' => ['Lectura', 'Intercambios'], 'comments' => [
            [2, 'Tengo cuentos infantiles en buen estado. Los llevaré para el rincón de lectura.'],
            [13, 'Me apunto. Puedo ayudar con la mesa y la lista de libros.'],
        ]],
        ['kind' => 'collaboration', 'body' => '¿Armamos turnos para acompañar a los vecinos mayores que necesitan hacer compras cerca de casa? La idea es coordinar primero con cada familia y respetar sus horarios. #voluntariado #solidaridad', 'categories' => ['Voluntariado', 'Apoyo vecinal'], 'comments' => [
            [16, 'Puedo apoyar los sábados en la mañana. Coordinemos con las familias antes de ir.'],
            [4, 'Buena idea. Yo puedo ayudar a organizar los turnos.'],
        ]],
        ['kind' => 'need_help', 'body' => 'Mi máquina de coser salta puntadas. Ya cambié la aguja y sigue igual. ¿Hay alguien que sepa revisarla o recomiende una técnica del barrio? #costura #reparaciones', 'categories' => ['Costura', 'Reparaciones'], 'comments' => [
            [8, 'Trabajo en costura. Si me cuentas qué tela e hilo estás usando podemos empezar por revisar la tensión.'],
            [14, 'Yo conozco una técnica cercana; la semana pasada dejó mi máquina funcionando muy bien.'],
        ]],
        ['kind' => 'activity', 'title' => 'Aprendamos a hacer compost', 'location' => 'Jardineras del parque · reunión vecinal', 'body' => 'Compartamos cómo aprovechar los restos vegetales de la cocina. Lleven sus preguntas; empezaremos con un recipiente pequeño y ejemplos sencillos. #compost #huerto #medioambiente', 'categories' => ['Jardinería', 'Talleres'], 'comments' => [
            [0, 'Siempre quise aprender. Puedo llevar un balde limpio para empezar.'],
            [6, 'Tengo hojas secas del jardín. Las llevaré para la demostración.'],
        ]],
    ],
];
