import { test, expect } from '@playwright/test';

const email = process.env.CT_UI_EMAIL;
const password = process.env.CT_UI_PASSWORD;

test.skip(!email || !password, 'Define CT_UI_EMAIL y CT_UI_PASSWORD del usuario activo de ClickTap para ejecutar la prueba en navegador.');

test('usa el mismo panel en el resumen y las vistas de contenido', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.goto('/admin/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page.getByRole('heading', { name: 'Resumen' })).toBeVisible();
    await expect(page.locator('.fi-sidebar')).toBeVisible();
    await expect(page.locator('.ct-shell')).toHaveCount(0);

    await page.evaluate(() => { window.clicktapNavigationMarker = true; });
    await page.locator('.fi-sidebar').getByRole('link', { name: 'Páginas', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/paginas$/);
    await expect(page.getByRole('heading', { name: 'Páginas', exact: true })).toBeVisible();
    expect(await page.evaluate(() => window.clicktapNavigationMarker)).toBe(true);
    await expect(page.locator('.fi-sidebar')).toBeVisible();
    await expect(page.locator('.ct-shell')).toHaveCount(0);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin');
    await expect(page.getByRole('heading', { name: 'Resumen' })).toBeVisible();
    await expect(page.locator('.ct-shell')).toHaveCount(0);
    await page.locator('.fi-topbar-open-sidebar-btn').click();
    await expect(page.locator('.fi-sidebar')).toBeVisible();

    expect(errors).toEqual([]);
});
