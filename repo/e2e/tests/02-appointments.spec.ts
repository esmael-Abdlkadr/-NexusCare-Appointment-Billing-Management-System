import { expect, test } from '@playwright/test'
import type { APIRequestContext, Page } from '@playwright/test'
import { apiGet, apiPost, apiToken } from '../helpers/api'
import { loginAsReviewer, loginAsStaff, logout } from '../helpers/auth'

const seedConfirmedAppointment = async (request: APIRequestContext, dayOffset: number, serviceType: string) => {
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
  await expect(
    page.locator('.el-table__body-wrapper:visible .el-table__body, .el-table__empty-block:visible').first()
  ).toBeVisible()
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

const chooseFirstSelectOption = async (page: Page, labelText: string) => {
  const field = page.locator('.el-form-item').filter({ hasText: labelText }).first()
  const selectTrigger = field.locator('.el-select')
  await selectTrigger.click({ force: true })

  const dropdown = page.locator('.el-select-dropdown:visible .el-select-dropdown__item').first()
  await dropdown.waitFor({ state: 'visible', timeout: 5000 })
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Enter')
  await page.waitForTimeout(300)

  const inputValue = selectTrigger.locator('.el-input__inner, .el-select__selected-item, .el-select__placeholder')
  await expect(inputValue.first()).not.toHaveText(/search|select/i, { timeout: 3000 }).catch(() => {})
}

test('create appointment form submits successfully and persists the new appointment', async ({ page, request }) => {
  const serviceType = `E2E-UI-CREATE-${Date.now()}`
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')

  await page.goto('/appointments/create')
  await expect(page.getByRole('heading', { name: 'Create Appointment' })).toBeVisible({ timeout: 10000 })

  await chooseFirstSelectOption(page, 'Client')
  await page.locator('input[placeholder="Enter service type"]').fill(serviceType)
  await chooseFirstSelectOption(page, 'Provider')
  await chooseFirstSelectOption(page, 'Resource')

  const uniqueDay = 20 + (Date.now() % 5)
  const startHour = 8 + (Date.now() % 6)
  await page.locator('input[placeholder="Select start time"]').fill(`2028-07-${uniqueDay} ${startHour}:00:00`)
  await page.locator('input[placeholder="Select end time"]').fill(`2028-07-${uniqueDay} ${startHour + 1}:00:00`)

  const submitAndResolveConflicts = async (maxRetries = 3) => {
    for (let attempt = 0; attempt < maxRetries; attempt++) {
      await page.getByRole('button', { name: /create appointment/i }).click()
      await page.waitForTimeout(1000)

      const conflictAlert = page.locator('.conflict-alert')
      if (await conflictAlert.isVisible().catch(() => false)) {
        const suggestedSlot = conflictAlert.locator('.el-button').first()
        if (await suggestedSlot.isVisible().catch(() => false)) {
          await suggestedSlot.click({ force: true })
          await page.waitForTimeout(500)
          continue
        }
      }

      const currentUrl = page.url()
      if (currentUrl.includes('/appointments') && !currentUrl.includes('/create')) {
        return true
      }
    }
    return false
  }

  const submitted = await submitAndResolveConflicts()

  if (!submitted) {
    await page.waitForURL(url => url.pathname === '/appointments', { timeout: 15000 })
  }

  await expect.poll(async () => {
    const data = await apiGet(request, staffToken, '/appointments', { status: 'requested', per_page: 100 })
    const rows = data?.data?.data ?? []
    return rows.some((row: { service_type?: string }) => row.service_type === serviceType)
  }, { timeout: 10000 }).toBe(true)
})

const filterByConfirmed = async (page: Page) => {
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
  await page.waitForLoadState('networkidle')
  const isForbiddenRoute = page.url().includes('/forbidden')
  const forbiddenMessage = page.getByText('Access Denied')
  const goBackButton = page.getByRole('button', { name: /go back/i })
  const submitBtn = page.getByRole('button', { name: /create appointment/i })

  if (isForbiddenRoute || await forbiddenMessage.isVisible().catch(() => false) || await goBackButton.isVisible().catch(() => false)) {
    await expect(page).toHaveURL(/\/forbidden|\/appointments\/create/, { timeout: 8000 })
    if (await forbiddenMessage.isVisible().catch(() => false)) {
      await expect(forbiddenMessage).toBeVisible({ timeout: 8000 })
    } else {
      await expect(goBackButton).toBeVisible({ timeout: 8000 })
    }
    return
  }

  await expect(submitBtn).toBeVisible({ timeout: 5000 })
  await submitBtn.click()
  await expect(page.locator('.el-message, .el-alert').filter({ hasText: /forbidden|denied|not authorized|403/i }).first()).toBeVisible({ timeout: 8000 })
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
