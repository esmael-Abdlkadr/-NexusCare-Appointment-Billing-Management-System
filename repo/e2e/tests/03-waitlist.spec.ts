import { expect, test } from '@playwright/test'
import { apiGet, apiPost, apiToken } from '../helpers/api'
import { loginAsStaff } from '../helpers/auth'

test.beforeAll(async ({ request }) => {
  const token = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const users = await apiGet(request, token, '/users/search', { per_page: 1 })
  const clientId = users?.data?.[0]?.id

  if (!clientId) {
    return
  }

  const start = new Date()
  start.setDate(start.getDate() + 1)
  start.setHours(10, 0, 0, 0)

  const end = new Date(start)
  end.setHours(11, 0, 0, 0)

  await apiPost(request, token, '/waitlist', {
    client_id: clientId,
    service_type: 'E2E-Remove-Test',
    priority: 5,
    preferred_start: start.toISOString(),
    preferred_end: end.toISOString()
  })
})

test.beforeEach(async ({ page }) => {
  await loginAsStaff(page)
})

test('waitlist page loads', async ({ page }) => {
  await page.goto('/waitlist')
  await expect(page.getByRole('heading', { name: 'Waitlist' })).toBeVisible()
  const hasTable = await page.locator('.el-table').count()
  expect(hasTable).toBeGreaterThan(0)
})

test('add to waitlist dialog opens and submits when possible', async ({ page }) => {
  await page.goto('/waitlist')
  await page.getByRole('button', { name: /add waitlist entry|add to waitlist/i }).click()
  await expect(page.locator('.el-dialog')).toBeVisible()

  const clientSelect = page.locator('.el-dialog .el-select').first()
  await clientSelect.click()
  const option = page.locator('.el-select-dropdown__item').first()
  if ((await option.count()) > 0) {
    await option.click()
    await page.locator('.el-dialog .el-form-item').filter({ hasText: 'Service Type' }).locator('input').fill('General Consultation')
    await page.getByRole('button', { name: /^add$/i }).last().click()
  } else {
    await page.getByRole('button', { name: /^cancel$/i }).last().click()
  }
})

test('remove waitlist entry', async ({ page }) => {
  await page.goto('/waitlist')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  const removeButton = page.getByRole('button', { name: /remove/i }).first()
  await expect(removeButton).toBeVisible({ timeout: 8000 })
  await removeButton.click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})
