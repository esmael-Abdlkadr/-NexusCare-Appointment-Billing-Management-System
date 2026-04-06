import { expect, test } from '@playwright/test'
import { apiDelete, apiPost, apiTokenAsAdmin } from '../helpers/api'
import { loginAsAdmin, requireEnv } from '../helpers/auth'

test.beforeAll(async ({ request }) => {
  const token = await apiTokenAsAdmin(request)
  const tempPass = requireEnv('E2E_TEMP_PASS')

  const createAndDelete = async (suffix: string) => {
    const identifier = `e2e-recycle-tmp-${suffix}-${Date.now()}`
    const created = await apiPost(request, token, '/admin/users', {
      identifier,
      password: tempPass,
      role: 'staff',
      site_id: 1,
      department_id: 1
    })

    const userId = created?.data?.user?.id || created?.data?.id
    if (userId) {
      await apiDelete(request, token, `/admin/users/${userId}`)
    }
  }

  await createAndDelete('restore')
  await createAndDelete('delete')
})

test.beforeEach(async ({ page }) => {
  await loginAsAdmin(page)
})

test('recycle bin page loads', async ({ page }) => {
  await page.goto('/admin/recycle')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible()
})

test('restore soft-deleted record', async ({ page }) => {
  await page.goto('/admin/recycle')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  const restoreBtn = page.getByRole('button', { name: /restore/i }).first()
  await expect(restoreBtn).toBeVisible({ timeout: 8000 })
  await restoreBtn.click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('permanently delete record', async ({ page }) => {
  await page.goto('/admin/recycle')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  const deleteBtn = page.getByRole('button', { name: /permanently delete/i }).first()
  await expect(deleteBtn).toBeVisible({ timeout: 8000 })
  await deleteBtn.click()
  const confirmBtn = page.getByRole('button', { name: /^delete$/i }).last()
  if (await confirmBtn.isVisible()) {
    await confirmBtn.click()
  }
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})
