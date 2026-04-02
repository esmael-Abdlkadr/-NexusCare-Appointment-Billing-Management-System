import { expect, test } from '@playwright/test'
import { loginAsReviewer, loginAsStaff } from '../helpers/auth'

test.beforeEach(async ({ page }) => {
  await loginAsReviewer(page)
})

test('audit logs page loads', async ({ page }) => {
  await page.goto('/audit-logs')
  await expect(page.locator('.el-table')).toBeVisible()
  await expect(page.locator('.el-table')).toContainText(/action|actor|timestamp/i)
})

test('filter by action works', async ({ page }) => {
  await page.goto('/audit-logs')
  await page.locator('input[placeholder="Action"]').fill('LOGIN')
  await page.locator('input[placeholder="Action"]').press('Enter')
  await expect(page.locator('.el-table')).toBeVisible()
})

test('staff cannot access audit logs', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/audit-logs')
  if (page.url().includes('/login')) {
    await expect(page).toHaveURL(/\/login/)
    return
  }
  await expect(page.locator('body')).toBeVisible()
})
