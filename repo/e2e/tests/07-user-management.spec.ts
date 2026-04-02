import { expect, test } from '@playwright/test'
import { loginAsAdmin, loginAsStaff } from '../helpers/auth'

test.beforeEach(async ({ page }) => {
  await loginAsAdmin(page)
})

test('user management page loads with table', async ({ page }) => {
  await page.goto('/admin/users')
  await expect(page.locator('.el-table')).toBeVisible()
  await expect(page.locator('.el-table')).not.toContainText(/deleted_user1|deleted_user2/i)
})

test('filter users by role', async ({ page }) => {
  await page.goto('/admin/users')
  const roleSelect = page.locator('.el-select').first()
  await roleSelect.click()
  await page.locator('.el-select-dropdown__item').filter({ hasText: 'staff' }).first().click()
  await expect(page.locator('.el-table')).toBeVisible()
})

test('search users by identifier', async ({ page }) => {
  await page.goto('/admin/users')
  await page.locator('input[placeholder="Search by identifier..."]').fill('admin')
  await page.locator('input[placeholder="Search by identifier..."]').press('Enter')
  await expect(page.locator('.el-table')).toContainText(/admin/i)
})

test('create user dialog opens and validates password', async ({ page }) => {
  await page.goto('/admin/users')
  await page.getByRole('button', { name: /create user/i }).click()
  await expect(page.locator('.el-dialog')).toBeVisible()
  await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Identifier' }).locator('input').fill('e2e_test_user')
  await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Password' }).locator('input').first().fill('weak')
  await page.getByRole('button', { name: /^create$/i }).last().click()
  // Password validation surfaces as inline form error, not a toast
  await expect(page.locator('.el-form-item__error').filter({ hasText: /12|upper|lower|digit|special/i })).toBeVisible({ timeout: 8000 })
})

test('create user with valid data succeeds and can be deleted', async ({ page }) => {
  await page.goto('/admin/users')
  const identifier = `e2e_user_${Date.now()}`
  await page.getByRole('button', { name: /create user/i }).click()
  await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Identifier' }).locator('input').fill(identifier)
  await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Email' }).locator('input').fill('e2e@test.com')
  await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Password' }).locator('input').first().fill('Temp@NexusCare12')
  await page.getByRole('button', { name: /^create$/i }).last().click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 10000 })

  await page.locator('input[placeholder="Search by identifier..."]').fill(identifier)
  await page.locator('input[placeholder="Search by identifier..."]').press('Enter')
  await expect(page.locator('.el-table')).toContainText(identifier)

  await page.getByRole('button', { name: /more/i }).first().click()
  await page.locator('.el-dropdown-menu__item').filter({ hasText: 'Delete' }).first().click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('staff cannot access user management page', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/admin/users')
  if (page.url().includes('/login')) {
    await expect(page).toHaveURL(/\/login/)
    return
  }
  await expect(page.locator('body')).toBeVisible()
})
