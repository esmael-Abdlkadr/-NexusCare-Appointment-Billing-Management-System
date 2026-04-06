import { expect, Page } from '@playwright/test'

export function requireEnv(name: string): string {
  const val = process.env[name]
  if (!val) {
    throw new Error(`Missing required env var ${name}. Set it before running E2E tests.`)
  }
  return val
}

export async function loginAs(page: Page, identifier: string, password: string) {
  await page.goto('/login')
  await page.locator('input[placeholder="Enter identifier"]').fill(identifier)
  await page.locator('input[placeholder="Enter password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await page.waitForURL(url => !url.pathname.includes('/login'))
}

export async function loginAsAdmin(page: Page) {
  await loginAs(page, requireEnv('E2E_ADMIN_USER'), requireEnv('E2E_ADMIN_PASS'))
  await expect(page.locator('.sidebar')).toBeVisible()
}

export async function loginAsStaff(page: Page) {
  await loginAs(page, requireEnv('E2E_STAFF_USER'), requireEnv('E2E_STAFF_PASS'))
}

export async function loginAsReviewer(page: Page) {
  await loginAs(page, requireEnv('E2E_REVIEWER_USER'), requireEnv('E2E_REVIEWER_PASS'))
}

export async function logout(page: Page) {
  const logoutButton = page.getByRole('button', { name: /logout/i })
  if (await logoutButton.isVisible()) {
    await logoutButton.click()
  }
}
