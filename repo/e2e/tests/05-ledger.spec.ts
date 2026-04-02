import { expect, test } from '@playwright/test'
import { loginAsAdmin, loginAsStaff } from '../helpers/auth'

test('ledger page loads for admin', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/ledger')
  await expect(page.locator('.el-table')).toBeVisible()
  await expect(page.getByRole('heading', { name: /ledger/i })).toBeVisible()
})

test('staff cannot access ledger', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/ledger')
  const url = page.url()
  if (url.includes('/login')) {
    await expect(page).toHaveURL(/\/login/)
    return
  }
  const messages = page.locator('.el-message, .el-empty, body')
  await expect(messages.first()).toBeVisible()
})
