// Recortes y normalizacion compatibles con FrameFind (MIT).
// Procedencia y licencia: public/face-accessories/README.md.
export const LADO_ACCESORIOS = 112;
const OJOS = [33, 133, 159, 145, 158, 153, 144, 163, 7, 246,
    362, 263, 386, 374, 385, 380, 373, 390, 249, 466,
    70, 63, 105, 66, 107, 336, 296, 334, 293, 300, 168, 6, 197, 195];
const MEDIA = [0.485, 0.456, 0.406];
const DESVIACION = [0.229, 0.224, 0.225];

export function recortesAccesorios(puntos, ancho, alto) {
    if (!Array.isArray(puntos) || puntos.length < 468 || !(ancho > 0 && alto > 0)
        || puntos.some(p => !Number.isFinite(p.x) || !Number.isFinite(p.y))) return null;
    const limites = lista => {
        const xs = lista.map(p => p.x * ancho), ys = lista.map(p => p.y * alto);
        return [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)];
    };
    const acotar = (x0, y0, x1, y1) => {
        const x = Math.max(0, x0), y = Math.max(0, y0);
        const w = Math.min(ancho, x1) - x, h = Math.min(alto, y1) - y;
        return w > 0 && h > 0 ? { x, y, w, h } : null;
    };
    const [ox0, oy0, ox1, oy1] = limites(OJOS.map(i => puntos[i]));
    const lado = Math.max(ox1 - ox0, oy1 - oy0) * 1.25;
    const cx = (ox0 + ox1) / 2, cy = (oy0 + oy1) / 2;
    const [x0, y0, x1, y1] = limites(puntos);
    const w = x1 - x0, h = y1 - y0;
    const ojos = acotar(cx - lado / 2, cy - lado / 2, cx + lado / 2, cy + lado / 2);
    const rostro = acotar(x0 - w * .2, y0 - h * .2 * .65, x1 + w * .2, y1 + h * .2 * 1.7);
    return ojos && rostro ? { ojos, rostro } : null;
}

export function normalizarAccesorios(pixeles) {
    const n = LADO_ACCESORIOS ** 2;
    if (pixeles.length !== n * 4) throw new Error('Recorte de accesorios invalido');
    const tensor = new Float32Array(3 * n);
    for (let canal = 0; canal < 3; canal++) {
        for (let i = 0; i < n; i++) tensor[canal * n + i] = (pixeles[i * 4 + canal] / 255 - MEDIA[canal]) / DESVIACION[canal];
    }
    return tensor;
}

export function probabilidadesAccesorios(gafas, mascarilla) {
    if (!gafas || gafas.length !== 1 || !mascarilla || mascarilla.length !== 3
        || [...gafas, ...mascarilla].some(v => !Number.isFinite(v))) throw new Error('Salida de accesorios invalida');
    const maximo = Math.max(...mascarilla);
    const exp = Array.from(mascarilla, v => Math.exp(v - maximo));
    const suma = exp.reduce((a, b) => a + b, 0);
    return { gafas: 1 / (1 + Math.exp(-gafas[0])), mascarilla: (exp[0] + exp[2]) / suma, sinMascarilla: exp[1] / suma };
}

export function crearFiltroAccesorios() {
    let historial = [];
    return {
        reiniciar() { historial = []; },
        actualizar(p) {
            if (![p.gafas, p.mascarilla, p.sinMascarilla].every(v => Number.isFinite(v) && v >= 0 && v <= 1)) {
                this.reiniciar();
                throw new Error('Probabilidad de accesorios invalida');
            }
            historial.push(p);
            historial = historial.slice(-3);
            // Consenso para no emitir avisos por un solo cuadro. Cualquier duda impide capturar.
            const gafas = p.gafas >= .65 && historial.filter(v => v.gafas >= .65).length >= 2;
            const mascarilla = p.mascarilla >= .65 && historial.filter(v => v.mascarilla >= .65).length >= 2;
            if (gafas || mascarilla) return {
                estado: 'accesorios',
                mensaje: gafas && mascarilla ? 'Retira tus lentes y la mascarilla para tomar la foto'
                    : gafas ? 'Retira tus lentes para tomar la foto' : 'Retira la mascarilla y deja tu rostro descubierto',
            };
            if (historial.length === 3 && historial.every(v => v.gafas <= .35 && v.sinMascarilla >= .7)) {
                return { estado: 'listo', mensaje: 'Rostro sin accesorios detectados' };
            }
            return { estado: 'accesorios', mensaje: 'Mira de frente mientras comprobamos que tu rostro esté descubierto' };
        },
    };
}
