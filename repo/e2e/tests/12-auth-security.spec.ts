import { expect, test } from '@playwright/test'
import { loginAsAdmin, loginAsReviewer, loginAsStaff } from '../helpers/auth'

test('locked account shows locked message on login', async ({ page }) => {
  // Use staff2 — dedicated for lockout test so staff1 remains unlocked for other tests
  await page.goto('/login')

  for (let i = 0; i < 5; i++) {
    await page.locator('input[placeholder="Enter identifier"]').fill('staff2')
    await page.locator('input[placeholder="Enter password"]').fill('WrongPass@999')
    await page.getByRole('button', { name: /sign in|login/i }).click()
    await page.waitForTimeout(400)
  }

  await page.locator('input[placeholder="Enter identifier"]').fill('staff2')
  await page.locator('input[placeholder="Enter password"]').fill('Staff2@NexusCare1')
  await page.getByRole('button', { name: /sign in|login/i }).click()

  await expect(page.locator('.el-alert--error')).toBeVisible({ timeout: 5000 })
  await expect(page.locator('.el-alert--error')).toContainText(/locked|lock/i)
})

test('audit log payload does not display raw sensitive fields', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/audit-logs')
  await expect(page.locator('.el-table')).toBeVisible({ timeout: 8000 })

  const content = await page.content()
  expect(content).not.toMatch(/"password_hash"\s*:\s*"\$2y\$/)
  expect(content).not.toMatch(/"government_id"\s*:\s*"[^*]/)
})

test('user-switch cache isolation — staff sees no admin UI after admin logout', async ({ page }) => {
  await loginAsAdmin(page)
  await expect(page.locator('.sidebar')).toContainText(/User Management/i)

  await page.getByRole('button', { name: /logout/i }).click()
  await expect(page).toHaveURL(/\/login/)

  await loginAsStaff(page)
  await expect(page.locator('.sidebar')).not.toContainText(/User Management/i)

  await page.goto('/admin/users')
  await expect(page).toHaveURL(/\/forbidden|\/login/)
})
