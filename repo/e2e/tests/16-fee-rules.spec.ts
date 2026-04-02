import { test, expect } from '@playwright/test'
import { loginAsAdmin, loginAsStaff } from '../helpers/auth'

test.describe('Fee Rules Management', () => {

  test('admin sees Fee Rules in sidebar nav', async ({ page }) => {
    await loginAsAdmin(page)
    const nav = page.locator('.sidebar, .el-menu, nav')
    await expect(nav.filter({ hasText: 'Fee Rules' }).first()).toBeVisible({ timeout: 8000 })
  })

  test('admin can navigate to /fee-rules and sees the table', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto('/fee-rules')
    await expect(page.getByRole('heading', { name: /fee rules/i })).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.el-table')).toBeVisible({ timeout: 8000 })
  })

  test('fee rules table shows seeded rules with fee type labels', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto('/fee-rules')
    await page.locator('.el-table').waitFor({ timeout: 10000 })
    await expect(page.locator('.el-table__row').first()).toBeVisible({ timeout: 8000 })
    const tableText = await page.locator('.el-table__body').innerText()
    expect(/no show|overdue|lost/i.test(tableText)).toBe(true)
  })

  test('admin can open Add/Update Rule dialog', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto('/fee-rules')
    await page.locator('.el-table').waitFor({ timeout: 10000 })
    await page.getByRole('button', { name: /add|update.*rule/i }).click()
    await expect(page.locator('.el-dialog')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('.el-dialog .el-select')).toBeVisible({ timeout: 3000 })
  })

  test('admin can save a fee rule and sees success message', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto('/fee-rules')
    await page.locator('.el-table').waitFor({ timeout: 10000 })

    // Open dialog
    await page.getByRole('button', { name: /add|update.*rule/i }).click()
    await expect(page.locator('.el-dialog')).toBeVisible({ timeout: 5000 })

    // Select fee type
    await page.locator('.el-dialog .el-select').click()
    await page.getByRole('option', { name: /no show/i }).click()

    // Fill amount
    await page.locator('.el-dialog input[type="number"]').first().fill('30')

    // Submit
    await page.locator('.el-dialog').getByRole('button', { name: /save/i }).click()
    await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 8000 })
  })

  test('staff visiting /fee-rules is denied access', async ({ page }) => {
    await loginAsStaff(page)
    await page.goto('/fee-rules')
    await page.waitForTimeout(2000)
    const url = page.url()
    const hasError = await page.locator('.el-message--error').isVisible().catch(() => false)
    const redirected = !url.includes('/fee-rules')
    expect(redirected || hasError).toBe(true)
  })

})
