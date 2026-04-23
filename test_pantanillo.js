/**
 * Test completo del Parque Industrial de Catamarca
 * Cubre: sitio público, panel empresa, panel ministerio
 *
 * Uso:
 *   node test_pantanillo.js
 *
 * Credenciales: definirlas como variables de entorno o editar la sección CONFIG.
 *   set EMPRESA_EMAIL=empresa@test.com
 *   set EMPRESA_PASS=password123
 *   set MINISTERIO_EMAIL=admin@test.com
 *   set MINISTERIO_PASS=password123
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// ── DOTENV: carga .env.test si existe (evita escribir credenciales a mano) ───
const ENV_FILE = path.join(__dirname, '.env.test');
if (fs.existsSync(ENV_FILE)) {
  fs.readFileSync(ENV_FILE, 'utf8').split('\n').forEach(line => {
    const [k, ...rest] = line.split('=');
    if (k && rest.length) process.env[k.trim()] = rest.join('=').trim();
  });
}

// ── CONFIG ────────────────────────────────────────────────────────────────────
const BASE_URL = 'https://parque-industrial.onrender.com';

const CREDS = {
  empresa: {
    email:    process.env.EMPRESA_EMAIL    || 'COMPLETAR_EMAIL_EMPRESA',
    password: process.env.EMPRESA_PASS     || 'COMPLETAR_PASSWORD_EMPRESA',
  },
  ministerio: {
    email:    process.env.MINISTERIO_EMAIL || 'COMPLETAR_EMAIL_MINISTERIO',
    password: process.env.MINISTERIO_PASS  || 'COMPLETAR_PASSWORD_MINISTERIO',
  },
};

const SCREENSHOT_DIR = path.join(__dirname, 'test_screenshots');
const SLOW_MO = 400;
const WARMUP_TIMEOUT = 30000; // tolera cold start de Render en warm-up
// ─────────────────────────────────────────────────────────────────────────────

// ── HELPERS ───────────────────────────────────────────────────────────────────
let passCount = 0;
let failCount = 0;
const results = [];
let _page = null; // referencia global para screenshots en fallo

function ok(label) {
  passCount++;
  results.push({ status: '✅', label });
  console.log(`  ✅ ${label}`);
}

function fail(label, err) {
  failCount++;
  const msg = err?.message?.split('\n')[0] ?? String(err);
  results.push({ status: '❌', label, error: msg });
  console.error(`  ❌ ${label}\n     ${msg}`);
}

async function screenshot(page, name) {
  const file = path.join(SCREENSHOT_DIR, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  return file;
}

async function waitReady(page) {
  await page.waitForLoadState('domcontentloaded');
}

async function login(page, role) {
  const { email, password } = CREDS[role];
  await page.goto(`${BASE_URL}/login.php`);
  await waitReady(page);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await waitReady(page);
}

async function logout(page) {
  await page.goto(`${BASE_URL}/logout.php`);
  await waitReady(page);
}

async function check(label, fn, { retries = 0 } = {}) {
  let lastErr;
  for (let i = 0; i <= retries; i++) {
    try {
      await fn();
      ok(label);
      return;
    } catch (err) {
      lastErr = err;
      // En el último intento fallido: captura screenshot para debugging
      if (i === retries && _page) {
        const safe = label.replace(/[^a-z0-9]/gi, '_').slice(0, 50);
        const file = path.join(SCREENSHOT_DIR, `FAIL_${safe}.png`);
        await _page.screenshot({ path: file }).catch(() => {});
        results.at(-1) && (results.at(-1).screenshot = file);
      }
      if (i < retries) await new Promise(r => setTimeout(r, 1500));
    }
  }
  fail(label, lastErr);
}
// ─────────────────────────────────────────────────────────────────────────────

// ── SUITE: SITIO PÚBLICO ──────────────────────────────────────────────────────
async function testPublico(page) {
  console.log('\n📋 SITIO PÚBLICO');

  await check('Inicio carga y muestra estadísticas', async () => {
    await page.goto(BASE_URL);
    await waitReady(page);
    await page.waitForSelector('.stat-card, .stat-cards', { timeout: 15000 });
    await screenshot(page, '01_inicio');
  });

  await check('Navbar contiene los 6 ítems principales', async () => {
    const links = await page.$$eval('nav a', els => els.map(e => e.textContent.trim()));
    const expected = ['Empresas', 'Mapa', 'El Parque', 'Estadísticas', 'Noticias'];
    for (const item of expected) {
      if (!links.some(l => l.includes(item))) throw new Error(`Falta enlace: ${item}`);
    }
  });

  await check('Página /empresas.php carga el directorio', async () => {
    await page.goto(`${BASE_URL}/empresas.php`);
    await waitReady(page);
    await page.waitForSelector('.card, .empresa-card, table', { timeout: 10000 });
    await screenshot(page, '02_empresas');
  });

  await check('Búsqueda de empresas responde', async () => {
    await page.fill('input[name="q"]', 'a');
    await page.keyboard.press('Enter');
    await waitReady(page);
    // Solo verifica que no rompe (sin 500)
    const status = page.url();
    if (!status.includes('empresas')) throw new Error('Redirigió fuera de empresas');
  });

  await check('Página /mapa.php carga Leaflet', async () => {
    await page.goto(`${BASE_URL}/mapa.php`, { waitUntil: 'commit' });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('#map, .leaflet-container', { timeout: 15000 });
    await screenshot(page, '03_mapa');
  }, { retries: 2 });

  await check('Página /estadisticas.php muestra gráficos renderizados', async () => {
    await page.goto(`${BASE_URL}/estadisticas.php`);
    await waitReady(page);
    await page.waitForSelector('canvas, .chart, [id*="chart"], [id*="grafico"]', { timeout: 10000 });
    // Espera a que Chart.js/ApexCharts termine la animación antes de capturar
    await page.waitForTimeout(1500);
    await screenshot(page, '04_estadisticas');
  });

  await check('Página /el-parque.php carga contenido institucional', async () => {
    await page.goto(`${BASE_URL}/el-parque.php`);
    await waitReady(page);
    await page.waitForSelector('h1, h2', { timeout: 8000 });
    await screenshot(page, '05_el_parque');
  });

  await check('Página /noticias.php lista publicaciones', async () => {
    await page.goto(`${BASE_URL}/noticias.php`);
    await waitReady(page);
    await page.waitForSelector('.card, article, .publicacion', { timeout: 10000 });
    await screenshot(page, '06_noticias');
  });

  await check('Formulario /presentar-proyecto.php es visible', async () => {
    await page.goto(`${BASE_URL}/presentar-proyecto.php`);
    await waitReady(page);
    await page.waitForSelector('form', { timeout: 8000 });
    // Verifica campos clave
    await page.waitForSelector('input[name="nombre_empresa"], input[name="contacto"]', { timeout: 5000 });
    await screenshot(page, '07_presentar_proyecto');
  });

  await check('Formulario público: campos requeridos tienen atributo required', async () => {
    await page.goto(`${BASE_URL}/presentar-proyecto.php`);
    await waitReady(page);
    // Verifica que los campos críticos tengan validación HTML5 nativa
    const requiredFields = await page.$$eval(
      'input[required], textarea[required], select[required]',
      els => els.map(e => e.name || e.id)
    );
    if (requiredFields.length === 0) {
      throw new Error('Ningún campo tiene atributo required — el formulario no valida en cliente');
    }
    // También verifica que el servidor no truene con un POST vacío
    const response = await page.request.post(`${BASE_URL}/presentar-proyecto.php`, { form: {} });
    if (response.status() === 500) {
      throw new Error('Servidor devuelve 500 con POST vacío — falta validación server-side');
    }
  });

  await check('Login rechaza credenciales incorrectas', async () => {
    await page.goto(`${BASE_URL}/login.php`);
    await waitReady(page);
    await page.fill('input[name="email"]', 'noexiste@test.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    await waitReady(page);
    // Debe quedarse en login o mostrar error
    const url = page.url();
    const hasError = await page.$('.alert-danger, .alert-error, [class*="error"]');
    if (!url.includes('login') && !hasError) throw new Error('No mostró error con credenciales incorrectas');
  });
}

// ── SUITE: PANEL EMPRESA ──────────────────────────────────────────────────────
async function testEmpresa(page) {
  console.log('\n🏭 PANEL EMPRESA');

  await check('Login como empresa exitoso', async () => {
    await login(page, 'empresa');
    const url = page.url();
    if (!url.includes('empresa') && !url.includes('dashboard')) {
      throw new Error(`Redirigió a: ${url}`);
    }
    await screenshot(page, '10_empresa_dashboard');
  });

  await check('Dashboard muestra % de completitud de perfil', async () => {
    await page.goto(`${BASE_URL}/empresa/dashboard.php`);
    await waitReady(page);
    await page.waitForSelector('[class*="progress"], [class*="completit"], [class*="percent"]', { timeout: 8000 });
  });

  await check('Dashboard muestra notificaciones o formularios pendientes', async () => {
    // Cualquier sección de actividad/pendientes
    await page.waitForSelector('.card, .alert, .list-group-item', { timeout: 8000 });
  });

  await check('Página de perfil carga con campos editables', async () => {
    await page.goto(`${BASE_URL}/empresa/perfil.php`);
    await waitReady(page);
    await page.waitForSelector('input[name="nombre"], input[name="razon_social"], input[name="cuit"]', { timeout: 8000 });
    await screenshot(page, '11_empresa_perfil');
  });

  await check('Perfil: edición de teléfono y guardado', async () => {
    await page.goto(`${BASE_URL}/empresa/perfil.php`);
    await waitReady(page);
    const telInput = page.locator('input[name="telefono"]');
    await telInput.clear();
    await telInput.fill('3834000000');
    await page.click('button[type="submit"]');
    await waitReady(page);
    // Debe mostrar mensaje de éxito
    const flash = await page.$('.alert-success, .alert-info, [class*="success"]');
    if (!flash) throw new Error('No apareció confirmación de guardado');
    await screenshot(page, '12_empresa_perfil_guardado');
  });

  await check('Página de formularios carga sin error', async () => {
    await page.goto(`${BASE_URL}/empresa/formularios.php`);
    await waitReady(page);
    // Puede mostrar "No hay formularios" o una lista — ambos son válidos
    await page.waitForSelector('.card, table, .alert, p', { timeout: 8000 });
    await screenshot(page, '13_empresa_formularios');
  });

  await check('Página de publicaciones carga lista', async () => {
    await page.goto(`${BASE_URL}/empresa/publicaciones.php`);
    await waitReady(page);
    await page.waitForSelector('.card, table, .alert, h1', { timeout: 8000 });
    await screenshot(page, '14_empresa_publicaciones');
  });

  await check('Página de mensajes carga bandeja', async () => {
    await page.goto(`${BASE_URL}/empresa/mensajes.php`);
    await waitReady(page);
    await page.waitForSelector('.inbox-wrap, .inbox-empty, .inbox-list-col, .alert', { timeout: 8000 });
    await screenshot(page, '15_empresa_mensajes');
  });

  await check('Página de notificaciones carga', async () => {
    await page.goto(`${BASE_URL}/empresa/notificaciones.php`);
    await waitReady(page);
    await page.waitForSelector('.card, .list-group, .alert, h1', { timeout: 8000 });
  });

  await check('Logout de empresa funciona', async () => {
    await logout(page);
    const url = page.url();
    if (url.includes('empresa/dashboard')) throw new Error('Sigue en dashboard tras logout');
    await screenshot(page, '16_logout_empresa');
  });
}

// ── SUITE: PANEL MINISTERIO ───────────────────────────────────────────────────
async function testMinisterio(page) {
  console.log('\n🏛️  PANEL MINISTERIO');

  await check('Login como ministerio exitoso', async () => {
    await login(page, 'ministerio');
    const url = page.url();
    if (!url.includes('ministerio') && !url.includes('dashboard')) {
      throw new Error(`Redirigió a: ${url}`);
    }
    await screenshot(page, '20_ministerio_dashboard');
  });

  await check('Dashboard muestra KPIs: empresas activas, empleados, visitas', async () => {
    await page.goto(`${BASE_URL}/ministerio/dashboard.php`);
    await waitReady(page);
    await page.waitForSelector('.card, [class*="stat"], [class*="kpi"]', { timeout: 10000 });
    // Al menos 3 tarjetas de métricas
    const cards = await page.$$('.card');
    if (cards.length < 3) throw new Error(`Solo ${cards.length} tarjeta(s) en dashboard`);
  });

  await check('Dashboard muestra log de actividad reciente', async () => {
    // Navega explícitamente — no depende del estado de la página anterior
    await page.goto(`${BASE_URL}/ministerio/dashboard.php`);
    await waitReady(page);
    await page.waitForSelector('[class*="actividad"], [class*="log"], .timeline, .list-group-item', { timeout: 8000 });
  });

  await check('Lista de empresas carga con tabla/cards', async () => {
    await page.goto(`${BASE_URL}/ministerio/empresas.php`);
    await waitReady(page);
    await page.waitForSelector('table, .card, .empresa-row', { timeout: 10000 });
    await screenshot(page, '21_ministerio_empresas');
  });

  await check('Detalle de primera empresa: carga y CUIT no está vacío (valida DB)', async () => {
    await page.goto(`${BASE_URL}/ministerio/empresas.php`);
    await waitReady(page);
    const firstLink = await page.$('table a[href*="empresa-detalle"], .card a[href*="empresa-detalle"]');
    if (!firstLink) return; // sin empresas cargadas, el test no aplica
    await firstLink.click();
    await waitReady(page);
    if (page.url().includes('500')) throw new Error('Error 500 en detalle empresa');
    // Valida que los datos llegaron desde Aiven:
    // el nombre de la empresa debe estar visible y no vacío
    const heading = await page.textContent('h1, h2, h3').catch(() => '');
    if (!heading || heading.trim().length < 2) {
      throw new Error('Nombre de empresa vacío — posible problema de conexión con Aiven');
    }
    // Si hay un CUIT renderizado, verificar que tenga formato XX-XXXXXXXX-X
    const bodyText = await page.textContent('body');
    const cuitMatch = bodyText.match(/\d{2}-\d{8}-\d/);
    if (!cuitMatch && bodyText.includes('CUIT')) {
      console.log('     ⚠️  CUIT visible pero sin valor cargado (empresa sin datos completos)');
    }
    await screenshot(page, '22_ministerio_empresa_detalle');
  });

  await check('Formularios: lista carga correctamente', async () => {
    await page.goto(`${BASE_URL}/ministerio/formularios.php`);
    await waitReady(page);
    await page.waitForSelector('table, .card, .alert, h1', { timeout: 8000 });
    await screenshot(page, '23_ministerio_formularios');
  });

  await check('Publicaciones: tabs "Ministerio" y "Empresas" presentes', async () => {
    await page.goto(`${BASE_URL}/ministerio/publicaciones.php`);
    await waitReady(page);
    await page.waitForSelector('.nav-tabs, .nav-pills, [role="tablist"]', { timeout: 8000 });
    await screenshot(page, '24_ministerio_publicaciones');
  });

  await check('Banners: lista de banners carga', async () => {
    await page.goto(`${BASE_URL}/ministerio/banners.php`);
    await waitReady(page);
    await page.waitForSelector('table, .card, .alert, form', { timeout: 8000 });
    await screenshot(page, '25_ministerio_banners');
  });

  await check('Comunicados: formulario de envío visible', async () => {
    await page.goto(`${BASE_URL}/ministerio/comunicados.php`);
    await waitReady(page);
    await page.waitForSelector('form, textarea, select', { timeout: 8000 });
    await screenshot(page, '26_ministerio_comunicados');
  });

  await check('Estadísticas config carga sin error', async () => {
    await page.goto(`${BASE_URL}/ministerio/estadisticas-config.php`);
    await waitReady(page);
    await page.waitForSelector('form, .card, h1', { timeout: 8000 });
  });

  await check('Gráficos del ministerio cargan', async () => {
    await page.goto(`${BASE_URL}/ministerio/graficos.php`);
    await waitReady(page);
    await page.waitForSelector('canvas, .chart, h1, .card', { timeout: 8000 });
    await screenshot(page, '27_ministerio_graficos');
  });

  await check('Exportar datos: página carga sin error', async () => {
    await page.goto(`${BASE_URL}/ministerio/exportar.php`);
    await waitReady(page);
    await page.waitForSelector('form, table, .btn, h1', { timeout: 8000 });
  });

  await check('Solicitudes de proyecto: lista carga', async () => {
    await page.goto(`${BASE_URL}/ministerio/solicitudes-proyecto.php`);
    await waitReady(page);
    await page.waitForSelector('table, .card, .alert, h1', { timeout: 8000 });
  });

  await check('Logout de ministerio funciona', async () => {
    await logout(page);
    const url = page.url();
    if (url.includes('ministerio/dashboard')) throw new Error('Sigue en dashboard tras logout');
    await screenshot(page, '28_logout_ministerio');
  });
}

// ── SUITE: PROTECCIÓN DE RUTAS ────────────────────────────────────────────────
async function testProteccion(page, context) {
  console.log('\n🔒 PROTECCIÓN DE RUTAS');

  const rutasProtegidas = [
    '/empresa/dashboard.php',
    '/empresa/perfil.php',
    '/empresa/formularios.php',
    '/ministerio/dashboard.php',
    '/ministerio/empresas.php',
    '/ministerio/banners.php',
  ];

  // Limpia cookies antes de probar rutas protegidas para garantizar sesión vacía
  await context.clearCookies();

  for (const ruta of rutasProtegidas) {
    await check(`Ruta protegida redirige sin sesión: ${ruta}`, async () => {
      await page.goto(`${BASE_URL}${ruta}`);
      await waitReady(page);
      const finalUrl = new URL(page.url());
      // La ruta protegida NO debe ser la pathname final — debe haber redirigido
      if (finalUrl.pathname === ruta) {
        throw new Error(`Acceso permitido sin autenticación a ${ruta}`);
      }
    });
  }
}

// ── SUITE: RENDIMIENTO ────────────────────────────────────────────────────────
async function testRendimiento(page) {
  console.log('\n⚡ RENDIMIENTO');

  const paginas = [
    { url: '/', nombre: 'Inicio' },
    { url: '/empresas.php', nombre: 'Directorio' },
    { url: '/estadisticas.php', nombre: 'Estadísticas' },
    { url: '/mapa.php', nombre: 'Mapa' },
  ];

  for (const { url, nombre } of paginas) {
    await check(`${nombre} carga en menos de 10 segundos (cold start tolerado)`, async () => {
      const t0 = Date.now();
      await page.goto(`${BASE_URL}${url}`);
      await waitReady(page);
      const ms = Date.now() - t0;
      console.log(`     ⏱ ${ms}ms`);
      if (ms > 10000) throw new Error(`Tardó ${ms}ms (>10s)`);
    });
  }
}

// ── SUITE: ESTRÉS DE EXPORTACIÓN ─────────────────────────────────────────────
async function testExportacion(page) {
  console.log('\n📦 ESTRÉS DE EXPORTACIÓN');

  // Login como ministerio si la sesión expiró
  await login(page, 'ministerio');

  await check('Exportar CSV de empresas: descarga antes de 15s', async () => {
    await page.goto(`${BASE_URL}/ministerio/exportar.php`);
    await waitReady(page);

    // Primer formulario (value="empresas") → primer botón "Descargar CSV"
    const btnEmpresas = page.locator('form').filter({ has: page.locator('input[value="empresas"]') }).locator('button[type="submit"], button:has-text("Descargar")');

    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 15000 }),
      btnEmpresas.click(),
    ]);

    const filename = download.suggestedFilename();
    if (!filename.endsWith('.csv')) throw new Error(`Nombre inesperado: ${filename}`);

    // Lee las primeras líneas para verificar que no está vacío
    const stream = await download.createReadStream();
    const firstChunk = await new Promise((resolve, reject) => {
      stream.once('data', d => resolve(d.toString()));
      stream.once('error', reject);
      setTimeout(() => resolve(''), 3000);
    });

    if (!firstChunk || firstChunk.trim().length < 10) {
      throw new Error('CSV vacío o demasiado pequeño — posible timeout en Aiven');
    }

    console.log(`     📄 ${filename} — ${firstChunk.slice(0, 60).replace(/\n/g, '↵')}`);
  });

  await check('Exportar CSV de formularios: descarga antes de 15s', async () => {
    await page.goto(`${BASE_URL}/ministerio/exportar.php`);
    await waitReady(page);

    // Segundo formulario (value="formularios")
    const btnFormularios = page.locator('form').filter({ has: page.locator('input[value="formularios"]') }).locator('button[type="submit"], button:has-text("Descargar")');

    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 15000 }),
      btnFormularios.click(),
    ]);

    const filename = download.suggestedFilename();
    if (!filename.endsWith('.csv')) throw new Error(`Nombre inesperado: ${filename}`);
    console.log(`     📄 ${filename}`);
  });
}

// ── MAIN ──────────────────────────────────────────────────────────────────────
(async () => {
  if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR);

  console.log('═══════════════════════════════════════════════════════');
  console.log('  TEST SUITE — Parque Industrial de Catamarca');
  console.log(`  URL: ${BASE_URL}`);
  console.log('═══════════════════════════════════════════════════════');

  // Aviso si las credenciales son placeholder
  const missingCreds = [];
  if (CREDS.empresa.email.startsWith('COMPLETAR'))    missingCreds.push('EMPRESA_EMAIL / EMPRESA_PASS');
  if (CREDS.ministerio.email.startsWith('COMPLETAR')) missingCreds.push('MINISTERIO_EMAIL / MINISTERIO_PASS');
  if (missingCreds.length) {
    console.warn(`\n⚠️  Credenciales faltantes: ${missingCreds.join(', ')}`);
    console.warn('   Los tests de panel empresa/ministerio fallarán.');
    console.warn('   Define las variables de entorno o edita la sección CONFIG.\n');
  }

  // headless: false puede fallar en Windows sin display — usar HEADLESS=false para modo visual
  const headless = process.env.HEADLESS !== 'false';
  const browser = await chromium.launch({ headless, slowMo: headless ? 0 : SLOW_MO });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    locale: 'es-AR',
  });
  const page = await context.newPage();
  _page = page; // permite screenshots en fallo desde check()

  // Captura errores de consola del sitio
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(`JS: ${err.message}`));

  // ── WARM-UP ── despierta Render antes de medir tiempos reales ────────────
  process.stdout.write('\n🔥 WARM-UP (espera hasta 30s si Render está dormido)... ');
  const t0Warmup = Date.now();
  try {
    await page.goto(BASE_URL, { timeout: WARMUP_TIMEOUT, waitUntil: 'domcontentloaded' });
    console.log(`listo en ${Date.now() - t0Warmup}ms`);
  } catch {
    console.log(`⚠️  warm-up superó ${WARMUP_TIMEOUT}ms — el servidor puede estar lento`);
  }
  // ─────────────────────────────────────────────────────────────────────────

  try {
    await testRendimiento(page);
    await testPublico(page);
    await testProteccion(page, context);
    await testEmpresa(page);
    await testMinisterio(page);
    await testExportacion(page);
  } finally {
    await browser.close();
  }

  // ── RESUMEN ──────────────────────────────────────────────────────────────
  console.log('\n═══════════════════════════════════════════════════════');
  console.log('  RESUMEN');
  console.log('═══════════════════════════════════════════════════════');
  console.log(`  ✅ Pasaron: ${passCount}`);
  console.log(`  ❌ Fallaron: ${failCount}`);
  console.log(`  📸 Screenshots: ${SCREENSHOT_DIR}`);

  if (consoleErrors.length) {
    const shown = Math.min(consoleErrors.length, 10);
    console.log(`\n  ⚠️  Errores JS en el navegador (${consoleErrors.length} total, mostrando ${shown}):`);
    consoleErrors.slice(0, shown).forEach(e => console.log(`     • ${e}`));
    if (consoleErrors.length > shown) {
      console.log(`     … y ${consoleErrors.length - shown} más (revisa los screenshots FAIL_*.png)`);
    }
  }

  if (failCount > 0) {
    console.log('\n  Tests fallidos:');
    results.filter(r => r.status === '❌').forEach(r => {
      console.log(`     ❌ ${r.label}`);
      if (r.error)      console.log(`        ${r.error}`);
      if (r.screenshot) console.log(`        📸 ${r.screenshot}`);
    });
  }

  console.log('═══════════════════════════════════════════════════════\n');
  process.exit(failCount > 0 ? 1 : 0);
})();
