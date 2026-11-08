// Mantiene el rostro completo sin exigir alineacion exacta de ojos o postura.
const resultado = (estado, mensaje) => ({ estado, mensaje });
const limitar = valor => Math.max(0, Math.min(1, valor));

function nitidezRostro(imagen, caja) {
    if (!imagen.data || imagen.data.length !== imagen.width * imagen.height * 4) {
        return null;
    }

    // Compara el 60 % central del rostro a escala fija: excluye fondo y bordes.
    const lado = 64;
    const gris = new Float32Array(lado * lado);
    for (let y = 0; y < lado; y++) {
        for (let x = 0; x < lado; x++) {
            const px = caja.originX + caja.width * (0.2 + 0.6 * (x + 0.5) / lado);
            const py = caja.originY + caja.height * (0.2 + 0.6 * (y + 0.5) / lado);
            const x0 = Math.floor(px);
            const y0 = Math.floor(py);
            let luz = 0;

            // Interpola los cuatro pixeles vecinos para reducir saltos al cambiar de escala.
            for (let dy = 0; dy <= 1; dy++) {
                for (let dx = 0; dx <= 1; dx++) {
                    const vecinoX = Math.min(imagen.width - 1, x0 + dx);
                    const vecinoY = Math.min(imagen.height - 1, y0 + dy);
                    const indice = (vecinoY * imagen.width + vecinoX) * 4;
                    const pesoX = dx ? px - x0 : 1 - (px - x0);
                    const pesoY = dy ? py - y0 : 1 - (py - y0);
                    const luminancia = (
                        0.299 * imagen.data[indice]
                        + 0.587 * imagen.data[indice + 1]
                        + 0.114 * imagen.data[indice + 2]
                    );

                    luz += pesoX * pesoY * luminancia;
                }
            }
            gris[y * lado + x] = luz;
        }
    }
    // Suaviza el ruido del sensor para no confundirlo con detalle del rostro.
    const suave = new Float32Array(gris.length);
    const pesos = [1, 2, 1];
    for (let y = 1; y < lado - 1; y++) {
        for (let x = 1; x < lado - 1; x++) {
            let valor = 0;
            for (let dy = -1; dy <= 1; dy++) {
                for (let dx = -1; dx <= 1; dx++) {
                    const indice = (y + dy) * lado + x + dx;
                    valor += gris[indice] * pesos[dy + 1] * pesos[dx + 1];
                }
            }
            suave[y * lado + x] = valor / 16;
        }
    }
    // Mide cuanto varian los bordes: una imagen lisa o borrosa tiene menos detalle.
    let suma = 0;
    let cuadrados = 0;
    let muestras = 0;
    for (let y = 2; y < lado - 2; y++) {
        for (let x = 2; x < lado - 2; x++) {
            const i = y * lado + x;
            const borde = (
                suave[i - 1] + suave[i + 1]
                + suave[i - lado] + suave[i + lado]
                - 4 * suave[i]
            );

            suma += borde;
            cuadrados += borde * borde;
            muestras++;
        }
    }
    const varianza = Math.max(0, cuadrados / muestras - (suma / muestras) ** 2);
    // Normaliza entre 0 y 1; 300 es una referencia de puntuacion, no un umbral de rechazo.
    return limitar(Math.log1p(varianza) / Math.log1p(300));
}

export function evaluarCaptura(detecciones, imagen) {
    if (!detecciones.length) return resultado('buscando', 'Coloca tu rostro dentro de la imagen');
    if (detecciones.length > 1) return resultado('varias', 'Debe aparecer una sola persona');
    const caja = detecciones[0].boundingBox;
    if (!caja || ![caja.originX, caja.originY, caja.width, caja.height].every(Number.isFinite)
        || caja.width <= 0 || caja.height <= 0 || !(imagen.width > 0 && imagen.height > 0)) {
        return resultado('buscando', 'No vemos tu rostro con claridad');
    }
    const alto = caja.height / imagen.height;
    const ancho = caja.width / imagen.width;
    const margen = Math.min(caja.originX / imagen.width, caja.originY / imagen.height,
        (imagen.width - caja.originX - caja.width) / imagen.width,
        (imagen.height - caja.originY - caja.height) / imagen.height);
    if (alto > 0.80 || ancho > 0.70) {
        return resultado('cerca', 'Aléjate un poco para que se vea tu cara completa');
    }
    if (margen < 0.04) {
        return resultado('descentrado', 'Mueve tu rostro hacia el centro para que no quede cortado');
    }
    if (alto < 0.35 || ancho < 0.25) {
        return resultado('lejos', 'Acércate un poco para ver bien tu rostro');
    }
    const confianza = detecciones[0].categories?.[0]?.score;
    const deteccion = Number.isFinite(confianza) ? limitar(confianza) : 0.5;
    const nitidez = nitidezRostro(imagen, caja);

    // Solo elige la mejor toma, no bloquea la captura ni exige alineacion.
    // Sin pixeles disponibles conserva el criterio anterior: confianza de deteccion.
    const puntuacion = nitidez === null
        ? deteccion
        : 0.85 * nitidez + 0.15 * deteccion;

    return {
        ...resultado('listo', 'Rostro detectado. Mantente un momento para tomar la foto…'),
        puntuacion: 100 * puntuacion,
        posicion: {
            x: (caja.originX + caja.width / 2) / imagen.width,
            y: (caja.originY + caja.height / 2) / imagen.height,
            escala: caja.width / imagen.width,
            inclinacion: 0,
            giro: 0,
        },
    };
}

// Acumula tiempo de cuadros aptos; las correcciones breves pausan el avance.
// El llamador solo entrega cuadros nuevos. Al recuperar el encuadre se exige
// un candidato nuevo, sin reutilizar una toma anterior a la interrupcion.
// Recibe inicio y fin del analisis para separar latencia de una pausa real.
export function crearEstabilidad(duracion = 2000) {
    const recuperables = new Set(['descentrado', 'cerca', 'lejos']);
    let acumulado = 0, anterior = null, anteriorFin = null, ultimoApto = null, ultimoAptoFin = null, posicionAnterior = null;
    let lecturas = 0, consecutivas = 0, tramoDesde = null, pausada = false, mejor = -Infinity;
    const salida = opciones => ({ lista: false, cuenta: ultimoApto === null ? 0 : Math.max(1, Math.ceil((duracion - acumulado) / 1000)),
        progreso: Math.min(limitar(acumulado / duracion), limitar(lecturas / 10)),
        reiniciada: false, descartarCandidato: false, mejora: false, pausada: false, ...opciones });
    return {
        reiniciar() {
            acumulado = 0; anterior = null; anteriorFin = null; ultimoApto = null; ultimoAptoFin = null; posicionAnterior = null;
            lecturas = 0; consecutivas = 0; tramoDesde = null; pausada = false; mejor = -Infinity;
        },
        actualizar(lectura, ahora, finalizado = ahora) {
            const p = lectura.posicion;
            if (!Number.isFinite(ahora) || !Number.isFinite(finalizado) || finalizado < ahora
                || (anterior !== null && ahora <= anterior) || (anteriorFin !== null && ahora < anteriorFin)) {
                this.reiniciar();
                return salida({ reiniciada: true, descartarCandidato: true });
            }
            // La inferencia puede tardar mas en Android. Solo el tiempo desde que
            // termino cuenta como espera de otro cuadro, no el procesamiento.
            if (ultimoAptoFin !== null && ahora - ultimoAptoFin > 1500) this.reiniciar();
            if (lectura.estado !== 'listo' || !p || ![p.x, p.y, p.escala, p.inclinacion, p.giro, lectura.puntuacion, ahora].every(Number.isFinite)
                || p.escala <= 0) {
                if (recuperables.has(lectura.estado) && ultimoApto !== null) {
                    anterior = ahora;
                    anteriorFin = finalizado;
                    pausada = true;
                    consecutivas = 0;
                    mejor = -Infinity;
                    return salida({ pausada: true, descartarCandidato: true });
                }
                this.reiniciar();
                return salida({ reiniciada: true, descartarCandidato: true });
            }
            const cambioBrusco = posicionAnterior && (Math.hypot(p.x - posicionAnterior.x, p.y - posicionAnterior.y) > 0.05
                || Math.abs(p.escala / posicionAnterior.escala - 1) > 0.18
                || Math.abs(p.inclinacion - posicionAnterior.inclinacion) > 6 || Math.abs(p.giro - posicionAnterior.giro) > 0.10);
            const reiniciada = ultimoApto === null || (!pausada && ahora - anteriorFin > 450);
            if (reiniciada) this.reiniciar();
            const descartarCandidato = reiniciada || pausada || Boolean(cambioBrusco);
            if (descartarCandidato) { mejor = -Infinity; consecutivas = 0; tramoDesde = ahora; }
            // Una inferencia larga no sustituye los diez cuadros nuevos exigidos.
            if (anterior !== null && !pausada && !cambioBrusco) acumulado += Math.min(450, ahora - anterior);
            anterior = ahora;
            anteriorFin = finalizado;
            ultimoApto = ahora;
            ultimoAptoFin = finalizado;
            posicionAnterior = p;
            pausada = false;
            lecturas++;
            consecutivas++;
            const mejora = lectura.puntuacion > mejor;
            if (mejora) mejor = lectura.puntuacion;
            return salida({ lista: acumulado >= duracion && lecturas >= 10 && consecutivas >= 3 && ahora - tramoDesde >= 360,
                reiniciada, descartarCandidato, mejora });
        },
    };
}
