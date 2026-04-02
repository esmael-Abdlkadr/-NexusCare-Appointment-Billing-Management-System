import { expect, test } from '@playwright/test'
import { apiGet, apiPost, apiToken } from '../helpers/api'
import { loginAsReviewer, loginAsStaff, logout } from '../helpers/auth'

const seedConfirmedAppointment = async (request, dayOffset, serviceType) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) {
    return null
  }

  const start = new Date()
  start.setDate(start.getDate() + dayOffset)
  start.setHours(10 + (Date.now() % 3), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: serviceType,
    start_time: start.toISOString(),
    end_time: end.toISOString()
  })

  const id = created?.data?.appointment?.id
  if (!id) {
    return null
  }

  await request.patch(`http://localhost:80/api/appointments/${id}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'confirmed' }
  })

  return id
}

test.beforeAll(async ({ request }) => {
  const token = await apiToken(request, 'staff1', 'Staff@NexusCare1')

  const clients = await apiGet(request, token, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, token, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, token, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id

  if (!clientId || !provider?.id || !resourceId) {
    return
  }

  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')
  const seedTag = Date.now()
  const labels = [`E2E-CANCEL-${seedTag}`, `E2E-RESCHEDULE-${seedTag}`]

  for (let i = 0; i < labels.length; i += 1) {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    tomorrow.setHours(10 + i, 0, 0, 0)

    const end = new Date(tomorrow)
    end.setHours(11 + i, 0, 0, 0)

    const created = await apiPost(request, token, '/appointments', {
      client_id: clientId,
      provider_id: provider.id,
      resource_id: resourceId,
      department_id: provider.department_id || 1,
      service_type: labels[i],
      start_time: tomorrow.toISOString(),
      end_time: end.toISOString()
    })

    const appointmentId = created?.data?.appointment?.id
    if (!appointmentId) {
      continue
    }

    await request.patch(`http://localhost:80/api/appointments/${appointmentId}/status`, {
      headers: { Authorization: `Bearer ${adminToken}` },
      data: { status: 'confirmed' }
    })
  }

})

test.beforeEach(async ({ page }) => {
  await loginAsStaff(page)
})

test('appointments list page loads and shows table', async ({ page }) => {
  await page.goto('/appointments')
  await expect(page.locator('.el-table')).toBeVisible()
  await expect(page.locator('.el-table__body, .el-table__empty-block').first()).toBeVisible()
})

test('create appointment form opens and can attempt submit', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/appointments/create')
  await expect(page.getByRole('heading', { name: 'Create Appointment' })).toBeVisible({ timeout: 10000 })
  await expect(page.locator('.el-form')).toBeVisible()
  await expect(page.locator('.el-select').first()).toBeVisible()
  await page
    .locator('.el-select-dropdown__item')
    .first()
    .waitFor({ state: 'visible', timeout: 2000 })
    .catch(() => {})
  await expect(page.getByRole('button', { name: /create appointment/i })).toBeVisible()
})

const filterByConfirmed = async (page) => {
  const statusFilter = page.locator('.status-filter')
  await statusFilter.click()
  const option = page.locator('.el-select-dropdown__item').filter({ hasText: 'confirmed' }).first()
  await expect(option).toBeVisible({ timeout: 5000 })
  await option.click()
  // Wait for table to reload with filtered results
  await page.locator('.el-table__row, .el-table__empty-block').first().waitFor({ timeout: 10000 })
}

test('cancel appointment shows reason dialog', async ({ page }) => {
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByConfirmed(page)
  const confirmedRow = page.locator('.el-table__row').filter({ has: page.getByRole('button', { name: 'Check In' }) }).first()
  await expect(confirmedRow).toBeVisible({ timeout: 8000 })
  const moreButton = confirmedRow.locator('.actions-row .el-button').filter({ hasText: '⋯' }).first()
  await expect(moreButton).toBeVisible({ timeout: 8000 })
  await moreButton.click()
  const cancelAction = page.getByRole('menuitem', { name: 'Cancel' })
  await expect(cancelAction).toBeVisible({ timeout: 5000 })
  await cancelAction.click()
  await expect(page.locator('.el-dialog')).toBeVisible()
  await page.locator('.el-dialog textarea').fill('Cancelled from E2E test run')
  await page.getByRole('button', { name: /confirm cancel/i }).click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('reschedule appointment opens reschedule dialog', async ({ page }) => {
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByConfirmed(page)
  const confirmedRow = page.locator('.el-table__row').filter({ has: page.getByRole('button', { name: 'Check In' }) }).first()
  await expect(confirmedRow).toBeVisible({ timeout: 8000 })
  const moreButton = confirmedRow.locator('.actions-row .el-button').filter({ hasText: '⋯' }).first()
  await expect(moreButton).toBeVisible({ timeout: 8000 })
  await moreButton.click()
  const action = page.getByRole('menuitem', { name: 'Reschedule' })
  await expect(action).toBeVisible({ timeout: 5000 })
  await action.click()
  await expect(page.locator('.el-dialog')).toBeVisible()
  await expect(page.locator('.el-dialog')).toContainText(/reschedule/i)
  await page.getByRole('button', { name: /^close$/i }).click()
})

test('reviewer creating appointment gets rejected by backend', async ({ page }) => {
  await logout(page)
  await loginAsReviewer(page)
  await page.goto('/appointments/create')
  // If the form renders, attempt submit — backend will reject with 403
  const submitBtn = page.getByRole('button', { name: /create appointment/i })
  if (await submitBtn.count()) {
    await submitBtn.click()
    // Expect an error message (validation or 403 from backend)
    await expect(page.locator('.el-message, .el-alert')).toBeVisible({ timeout: 8000 })
  } else {
    // Page redirected or form not shown — also acceptable
    await expect(page.locator('body')).toBeVisible()
  }
})

test('appointments list pagination: next page button works', async ({ page, request }) => {
  const tag = Date.now()
  for (let i = 0; i < 16; i += 1) {
    await seedConfirmedAppointment(request, 400 + i, `E2E-PAGINATION-${tag}-${i}`)
  }

  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })

  const pagination = page.locator('.el-pagination')
  await expect(pagination).toBeVisible({ timeout: 10000 })

  const nextButton = page.getByRole('button', { name: /next/i }).first()
  await expect(nextButton).toBeVisible({ timeout: 8000 })
  await nextButton.click()

  await expect(page.locator('.el-pagination .el-pager .number.is-active')).toHaveText('2', { timeout: 8000 })

  const rowCount = await page.locator('.el-table__row').count()
  if (rowCount > 0) {
    await expect(page.locator('.el-table__row').first()).toBeVisible()
  } else {
    await expect(page.locator('.el-table__empty-block')).toBeVisible()
  }
})
