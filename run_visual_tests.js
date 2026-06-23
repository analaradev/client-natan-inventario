import puppeteer from 'puppeteer';
import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const SCREENSHOT_DIR = '/Users/usuario/.gemini/antigravity/brain/13c3ed09-6bc2-4201-b882-313902da01ec';
const PORT = 8000;
const BASE_URL = `http://localhost:${PORT}`;

// Asegurarse de que el directorio de capturas existe
if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function run() {
    console.log('Iniciando servidor de desarrollo de Laravel...');
    const server = spawn('php', ['artisan', 'serve', `--port=${PORT}`], {
        stdio: 'pipe',
        cwd: '/Users/usuario/Desktop/pan/pan_control_interno'
    });

    server.stdout.on('data', (data) => {
        console.log(`[Laravel Server]: ${data.toString().trim()}`);
    });

    server.stderr.on('data', (data) => {
        console.error(`[Laravel Server Error]: ${data.toString().trim()}`);
    });

    // Esperar a que el servidor se levante
    await wait(3000);

    let browser;
    try {
        console.log('Iniciando Puppeteer...');
        browser = await puppeteer.launch({
            executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            headless: true,
            defaultViewport: { width: 1280, height: 800 }
        });

        const page = await browser.newPage();

        // 1. Ir a la página de Login
        console.log(`Navegando a ${BASE_URL}/login...`);
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        
        const loginPath = path.join(SCREENSHOT_DIR, 'login_view.png');
        await page.screenshot({ path: loginPath });
        console.log(`Captura de login guardada en: ${loginPath}`);

        // 2. Rellenar formulario de Login
        console.log('Llenando formulario de login...');
        await page.type('input[name="user"]', 'test');
        await page.type('input[name="contra"]', 'test123');
        
        console.log('Enviando formulario de login...');
        await Promise.all([
            page.click('button[type="submit"]'),
            page.waitForNavigation({ waitUntil: 'networkidle2' })
        ]);

        const dashboardPath = path.join(SCREENSHOT_DIR, 'dashboard_view.png');
        await page.screenshot({ path: dashboardPath });
        console.log(`Captura de dashboard guardada en: ${dashboardPath}`);

        // 3. Ir a Subinventarios Index
        console.log(`Navegando a ${BASE_URL}/subinventarios...`);
        await page.goto(`${BASE_URL}/subinventarios`, { waitUntil: 'networkidle2' });
        
        const indexInitialPath = path.join(SCREENSHOT_DIR, 'subinventarios_index_initial.png');
        await page.screenshot({ path: indexInitialPath });
        console.log(`Captura de listado inicial guardada en: ${indexInitialPath}`);

        // 4. Ir a Crear Subinventario
        console.log(`Navegando a ${BASE_URL}/subinventarios/create...`);
        await page.goto(`${BASE_URL}/subinventarios/create`, { waitUntil: 'networkidle2' });

        // Esperar a que el selector de libros esté disponible (generado por agregarLibro() al cargar)
        await page.waitForSelector('select[name="libros[0][libro_id]"]');

        console.log('Llenando formulario de creación de subinventario...');
        // Rellenar fecha
        await page.evaluate(() => {
            document.querySelector('input[name="fecha_subinventario"]').value = '2026-06-23';
        });
        
        // Rellenar descripción y observaciones
        await page.type('input[name="descripcion"]', 'Prueba Visual Puppeteer');
        await page.type('textarea[name="observaciones"]', 'Subinventario creado automáticamente mediante script de pruebas visuales con Puppeteer.');

        // Seleccionar libro con ID 1 (21 dias 1)
        console.log('Seleccionando libro...');
        await page.select('select[name="libros[0][libro_id]"]', '1');

        // Escribir cantidad 5
        console.log('Estableciendo cantidad...');
        await page.evaluate(() => {
            const qtyInput = document.querySelector('input[name="libros[0][cantidad]"]');
            qtyInput.value = '';
        });
        await page.type('input[name="libros[0][cantidad]"]', '5');

        // Tomar captura del formulario lleno
        const createFilledPath = path.join(SCREENSHOT_DIR, 'subinventarios_create_filled.png');
        await page.screenshot({ path: createFilledPath });
        console.log(`Captura de formulario lleno guardada en: ${createFilledPath}`);

        // Guardar subinventario
        console.log('Guardando subinventario (haciendo clic en Guardar)...');
        await Promise.all([
            page.click('#subinventarioForm button[type="submit"]'),
            page.waitForNavigation({ waitUntil: 'networkidle2' })
        ]);

        console.log('Redirección completada. URL actual:', page.url());

        // Captura del detalle del subinventario creado
        const detailSavedPath = path.join(SCREENSHOT_DIR, 'subinventario_detail_saved.png');
        await page.screenshot({ path: detailSavedPath });
        console.log(`Captura de detalle guardada en: ${detailSavedPath}`);

        // 5. Ir al listado final de subinventarios
        console.log(`Navegando a ${BASE_URL}/subinventarios...`);
        await page.goto(`${BASE_URL}/subinventarios`, { waitUntil: 'networkidle2' });
        
        const indexFinalPath = path.join(SCREENSHOT_DIR, 'subinventarios_index_final.png');
        await page.screenshot({ path: indexFinalPath });
        console.log(`Captura de listado final guardada en: ${indexFinalPath}`);

        console.log('Prueba visual finalizada con éxito.');

    } catch (err) {
        console.error('Error durante la ejecución del test:', err);
    } finally {
        if (browser) {
            await browser.close();
        }
        console.log('Deteniendo servidor de desarrollo de Laravel...');
        server.kill('SIGINT');
    }
}

run();
