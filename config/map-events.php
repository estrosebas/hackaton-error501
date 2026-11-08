<?php

return [
    'categories' => [
        'community' => ['label' => 'Eventos comunitarios', 'emoji' => '🤝', 'color' => '#a992ee', 'points' => 2, 'examples' => 'Pollada pro salud, voluntariado', 'types' => ['Pollada pro salud', 'Voluntariado', 'Otro evento comunitario']],
        'emergency' => ['label' => 'Emergencias', 'emoji' => '🚨', 'color' => '#ff617e', 'points' => 3, 'examples' => 'Inundación, incendio, accidente', 'types' => ['Inundación', 'Incendio', 'Accidente', 'Otra emergencia']],
        'trend' => ['label' => 'Tendencias', 'emoji' => '🎉', 'color' => '#f5bb62', 'points' => 1, 'examples' => 'Chocolatada, cumpleaños, ajedrez infantil', 'types' => ['Chocolatada para niños', 'Fiesta de cumpleaños', 'Concurso de ajedrez infantil', 'Otra tendencia']],
        'support' => ['label' => 'Apoyo', 'emoji' => '💚', 'color' => '#65cfb0', 'points' => 1, 'examples' => 'Mudanza, aprender a conducir, reparar una computadora', 'types' => ['Ayuda para mudanza', 'Aprender a conducir', 'Reparar una computadora', 'Otro apoyo']],
    ],
    'frames' => [
        'neighbor' => ['label' => 'Buen vecino', 'points' => 10, 'color' => '#a992ee', 'style' => 'solid'],
        'friend' => ['label' => 'Manos amigas', 'points' => 20, 'color' => '#65cfb0', 'style' => 'double'],
        'spark' => ['label' => 'Chispa del barrio', 'points' => 30, 'color' => '#f5bb62', 'style' => 'dashed'],
        'heart' => ['label' => 'Corazón solidario', 'points' => 40, 'color' => '#ff618e', 'style' => 'double'],
        'star' => ['label' => 'Estrella de la comunidad', 'points' => 50, 'color' => '#e9dc9c', 'style' => 'solid'],
    ],
];
