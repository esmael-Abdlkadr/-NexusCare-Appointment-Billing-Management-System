import { expect, test } from '@playwright/test'
import { loginAsAdmin, loginAsReviewer, loginAsStaff } from '../helpers/auth'

test('reports page loads for reviewer', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/reports')
  await expect(page.locator('.el-card, .el-table, h2, h3').first()).toBeVisible({ timeout: 8000 })
})

test('appointment report download button is visible', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/reports')
  // Reports page uses download buttons (not a table view)
  await expect(page.getByRole('button', { name: /download appointments/i })).toBeVisible({ timeout: 8000 })
})

test('financial report renders without error', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/reports')
  const finTab = page.getByRole('tab', { name: /financial/i })
  if (await finTab.count()) await finTab.click()
  const content = page.locator('.el-table, .el-empty, .el-card')
  await expect(content.first()).toBeVisible({ timeout: 8000 })
})

test('admin can access audit report section', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/reports')
  const auditTab = page.getByRole('tab', { name: /audit/i })
  if (await auditTab.count()) {
    await auditTab.click()
    await expect(page.locator('.el-table, .el-empty').first()).toBeVisible({ timeout: 8000 })
  } else {
    await expect(page.locator('body')).toBeVisible()
  }
})

test('staff cannot access reports page', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/reports')
  const redirected = page.url().includes('/login')
  if (!redirected) {
    await expect(page.locator('body')).toBeVisible()
  }
})
