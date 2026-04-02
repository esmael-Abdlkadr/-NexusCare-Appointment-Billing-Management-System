import { expect, Page } from '@playwright/test'

export async function loginAs(page: Page, identifier: string, password: string) {
  await page.goto('/login')
  await page.locator('input[placeholder="Enter identifier"]').fill(identifier)
  await page.locator('input[placeholder="Enter password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await page.waitForURL(url => !url.pathname.includes('/login'))
}

export async function loginAsAdmin(page: Page) {
  await loginAs(page, 'admin', 'Admin@NexusCare1')
  await expect(page.locator('.sidebar')).toBeVisible()
}

export async function loginAsStaff(page: Page) {
  await loginAs(page, 'staff1', 'Staff@NexusCare1')
}

export async function loginAsReviewer(page: Page) {
  await loginAs(page, 'reviewer1', 'Reviewer@NexusCare1')
}

export async function logout(page: Page) {
  const logoutButton = page.getByRole('button', { name: /logout/i })
  if (await logoutButton.isVisible()) {
    await logoutButton.click()
  }
}
