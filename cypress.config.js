// =====================================================================
// PRUEBAS DE PUNTA A PUNTA (Cypress)
//   npm run e2e         → corre todas las pruebas sin ventana
//   npm run e2e:abrir   → abre Cypress para verlas paso a paso
// Necesita MySQL de XAMPP encendido. Cypress levanta solo el servidor de
// PHP en el puerto 8081 (tools/servidor_e2e.php), que usa la base
// nextleague_e2e_test; antes de cada archivo de pruebas esa base se
// vuelve a crear con datos conocidos (tests/e2e/preparar_base.php).
// Los datos de desarrollo (nextleague_v2) no se tocan.
// Los pasos que anota cada prueba (cy.paso) quedan, con su resultado, en
// tests/e2e/registro/pasos.json.
// =====================================================================

const { defineConfig } = require('cypress');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');

const PHP = process.env.NL_PHP || 'C:\\xampp\\php\\php.exe';
const PUERTO = 8081;
const CLAVE_PRUEBA = 'Prueba#2026'; // contraseña de todas las cuentas de prueba (solo existen en nextleague_e2e_test)
const REGISTRO = path.join(__dirname, 'tests', 'e2e', 'registro', 'pasos.json');

function leerRegistro() {
    try {
        return JSON.parse(fs.readFileSync(REGISTRO, 'utf8'));
    } catch {
        return [];
    }
}

function puertoEnUso(puerto) {
    return new Promise((resolver) => {
        const conexion = net.connect(puerto, '127.0.0.1');
        conexion.once('connect', () => { conexion.end(); resolver(true); });
        conexion.once('error', () => resolver(false));
    });
}

// Levanta el servidor de PHP si no está corriendo y espera a que responda.
async function levantarServidor(on) {
    if (await puertoEnUso(PUERTO)) return;
    const servidor = spawn(PHP, ['-S', `127.0.0.1:${PUERTO}`, '-t', '.', 'tools/servidor_e2e.php'],
        { cwd: __dirname, stdio: 'ignore', windowsHide: true });
    const apagar = () => { if (!servidor.killed) servidor.kill(); };
    on('after:run', apagar);
    process.on('exit', apagar);
    for (let i = 0; i < 50 && !(await puertoEnUso(PUERTO)); i++) {
        await new Promise((r) => setTimeout(r, 100));
    }
}

module.exports = defineConfig({
    e2e: {
        baseUrl: `http://127.0.0.1:${PUERTO}/public/`,
        specPattern: 'tests/e2e/pruebas/**/*.cy.js',
        supportFile: 'tests/e2e/soporte/e2e.js',
        fixturesFolder: 'tests/e2e/archivos',
        screenshotsFolder: 'tests/e2e/screenshots',
        videosFolder: 'tests/e2e/videos',
        video: false,
        viewportWidth: 1280,
        viewportHeight: 800,
        // El servidor de PHP atiende de a una petición: se le da más margen
        defaultCommandTimeout: 10000,
        taskTimeout: 120000,
        expose: { clave: CLAVE_PRUEBA },
        async setupNodeEvents(on, config) {
            await levantarServidor(on);
            // Cada corrida empieza un registro nuevo
            on('before:run', () => {
                fs.mkdirSync(path.dirname(REGISTRO), { recursive: true });
                fs.writeFileSync(REGISTRO, '[]');
            });
            on('task', {
                // Vuelve a crear la base de pruebas y devuelve los ids de los datos conocidos
                prepararBase() {
                    const salida = execFileSync(PHP, ['tests/e2e/preparar_base.php'], {
                        cwd: __dirname, encoding: 'utf8', env: { ...process.env, NL_E2E_CLAVE: CLAVE_PRUEBA },
                    });
                    return JSON.parse(salida);
                },
                // Simula que pasó el tiempo atrasando fechas guardadas (tests/e2e/simular_tiempo.php)
                simularTiempo(pedido) {
                    const salida = execFileSync(PHP, ['tests/e2e/simular_tiempo.php'], {
                        cwd: __dirname, encoding: 'utf8', env: { ...process.env, NL_SIMULAR: JSON.stringify(pedido) },
                    });
                    return JSON.parse(salida);
                },
                // Agrega al registro los pasos de una prueba que terminó
                guardarPasos(prueba) {
                    const registro = leerRegistro();
                    registro.push(prueba);
                    fs.mkdirSync(path.dirname(REGISTRO), { recursive: true });
                    fs.writeFileSync(REGISTRO, JSON.stringify(registro, null, 2));
                    return null;
                },
            });
            return config;
        },
    },
});
