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
  await expect(page.getByRole('button', { name: /download audit/i })).toBeVisible({ timeout: 8000 })
})

test('staff cannot access reports page', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/reports')
  await page.waitForLoadState('networkidle')
  const forbiddenIndicators = page.getByText('Access Denied')
  const goBackButton = page.getByRole('button', { name: /go back/i })
  const reportsHeader = page.getByRole('heading', { name: /reports/i })
  const reportsButtons = page.getByRole('button', { name: /download appointments|download financial|download audit/i })

  if (page.url().includes('/forbidden') || await forbiddenIndicators.isVisible().catch(() => false) || await goBackButton.isVisible().catch(() => false)) {
    if (await forbiddenIndicators.isVisible().catch(() => false)) {
      await expect(forbiddenIndicators).toBeVisible({ timeout: 8000 })
    } else {
      await expect(goBackButton).toBeVisible({ timeout: 8000 })
    }
    return
  }

  // If route remains reachable, it must still enforce denial by hiding report actions.
  await expect(reportsHeader).toBeVisible({ timeout: 8000 })
  await expect(reportsButtons).toHaveCount(0)
})
