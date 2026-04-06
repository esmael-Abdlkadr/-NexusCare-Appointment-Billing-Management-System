import { expect, test } from '@playwright/test'
import { loginAs, loginAsAdmin, loginAsStaff, logout, requireEnv } from '../helpers/auth'

test('login with valid admin credentials navigates to appointments page', async ({ page }) => {
  await loginAsAdmin(page)
  await expect(page).toHaveURL(/appointments|\/$/)
  await expect(page.locator('.sidebar')).toBeVisible()
})

test('login with wrong password shows error message', async ({ page }) => {
  await page.goto('/login')
  await page.locator('input[placeholder="Enter identifier"]').fill(requireEnv('E2E_ADMIN_USER'))
  await page.locator('input[placeholder="Enter password"]').fill('WrongPassword@1')
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page.locator('.el-alert--error')).toBeVisible()
  await expect(page.locator('.el-alert--error')).toContainText(/invalid|unable/i)
})

test('login with banned account shows banned error', async ({ page }) => {
  await page.goto('/login')
  await page.locator('input[placeholder="Enter identifier"]').fill(requireEnv('E2E_BANNED_USER'))
  await page.locator('input[placeholder="Enter password"]').fill(requireEnv('E2E_BANNED_PASS'))
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/\/login/, { timeout: 5000 })
  await expect(page.locator('.el-alert--error')).toBeVisible()
  await expect(page.locator('.el-alert--error')).toContainText(/banned|unable|invalid/i)
})

test('unauthenticated redirect to login', async ({ page }) => {
  await page.goto('/appointments')
  await expect(page).toHaveURL(/\/login/)
})

test('logout clears session and redirects to login', async ({ page }) => {
  await loginAsAdmin(page)
  await logout(page)
  await expect(page).toHaveURL(/\/login/)
  await page.goto('/appointments')
  await expect(page).toHaveURL(/\/login/)
})

test('expired token mid-session redirects to login', async ({ page }) => {
  await loginAsAdmin(page)
  await expect(page).toHaveURL(/appointments/)

  // Intercept every subsequent /auth/me to simulate an invalidated/expired token
  await page.route('**/api/auth/me', route =>
    route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ success: false, error: 'UNAUTHORIZED', data: [] })
    })
  )

  // Router guard calls /auth/me on every navigation — 401 must trigger redirect to /login
  await page.goto('/waitlist')
  await expect(page).toHaveURL(/\/login/, { timeout: 8000 })
})

test('page refresh preserves authenticated session', async ({ page }) => {
  await loginAsAdmin(page)
  await expect(page).toHaveURL(/appointments/)

  await page.reload()

  await expect(page).toHaveURL(/appointments|\//, { timeout: 10000 })
  await expect(page.locator('.sidebar')).toBeVisible({ timeout: 8000 })
})

test('muted user can log in successfully', async ({ page }) => {
  await loginAs(page, requireEnv('E2E_MUTED_USER'), requireEnv('E2E_MUTED_PASS'))
  // Muted users are not banned — login must succeed and sidebar must be visible
  await expect(page).toHaveURL(/appointments|\//, { timeout: 8000 })
  await expect(page.locator('.sidebar')).toBeVisible({ timeout: 8000 })
})

test('login clears stale localStorage auth keys', async ({ page }) => {
  await page.goto('/login')

  // Plant stale auth-related keys that a previous session might have left
  await page.evaluate(() => {
    localStorage.setItem('token', 'stale-token-value')
    localStorage.setItem('user', JSON.stringify({ id: 999, role: 'staff' }))
    localStorage.setItem('jwt', 'old-jwt-value')
    localStorage.setItem('auth', 'leftover')
  })

  await page.locator('input[placeholder="Enter identifier"]').fill(requireEnv('E2E_ADMIN_USER'))
  await page.locator('input[placeholder="Enter password"]').fill(requireEnv('E2E_ADMIN_PASS'))
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/appointments|\//, { timeout: 8000 })

  const staleRemaining = await page.evaluate(() =>
    ['token', 'user', 'jwt', 'auth'].filter(k => localStorage.getItem(k) !== null)
  )
  expect(staleRemaining).toHaveLength(0)
})
