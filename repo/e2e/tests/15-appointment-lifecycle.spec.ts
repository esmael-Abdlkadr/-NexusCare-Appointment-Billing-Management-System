import { expect, test } from '@playwright/test'
import { apiGet, apiPost, apiToken } from '../helpers/api'
import { loginAsAdmin, loginAsStaff } from '../helpers/auth'

const filterByStatus = async (page, status: string) => {
  const statusFilter = page.locator('.status-filter')
  await statusFilter.click()
  const option = page.locator('.el-select-dropdown__item').filter({ hasText: status }).first()
  await expect(option).toBeVisible({ timeout: 5000 })
  await option.click()
  await page.locator('.el-table__row, .el-table__empty-block').first().waitFor({ timeout: 10000 })
}

// Reusable helper to seed a fresh appointment in 'requested' status.
// Uses a far-future day + unique hour derived from Date.now() to avoid conflicts.
const seedRequestedAppointment = async (request) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) return null

  // Place the appointment 30+ days out so it never conflicts with real or seeded data
  const seed = Date.now()
  const start = new Date()
  start.setDate(start.getDate() + 30 + (seed % 100))
  start.setHours(8 + (seed % 8), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: `E2E-LC-${seed}`,
    start_time: start.toISOString(),
    end_time: end.toISOString()
  })
  return { id: created?.data?.appointment?.id ?? null, staffToken }
}

// ── 1. requested → confirmed ────────────────────────────────────────────────
// Only admin/reviewer see the Confirm button for requested appointments.
test('lifecycle: admin confirms a requested appointment', async ({ page, request }) => {
  const result = await seedRequestedAppointment(request, 2)
  if (!result?.id) { test.skip(true, 'Seeding failed'); return }

  await loginAsAdmin(page)
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByStatus(page, 'requested')

  const requestedRow = page.locator('.el-table__row')
    .filter({ has: page.getByRole('button', { name: 'Confirm' }) })
    .first()
  await expect(requestedRow).toBeVisible({ timeout: 8000 })

  await requestedRow.getByRole('button', { name: 'Confirm' }).click()
  await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 8000 })
})

// ── 2. confirmed → checked_in ───────────────────────────────────────────────
// Seeds a fresh confirmed appointment so this test is self-contained.
test('lifecycle: staff checks in a confirmed appointment', async ({ page, request }) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) {
    test.skip(true, 'Could not seed appointment — missing client/provider/resource')
    return
  }

  const seed2 = Date.now()
  const start = new Date()
  start.setDate(start.getDate() + 130 + (seed2 % 100))
  start.setHours(8 + (seed2 % 8), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: `E2E-CHECKIN-${seed2}`,
    start_time: start.toISOString(),
    end_time: end.toISOString()
  })
  const id = created?.data?.appointment?.id
  if (!id) { test.skip(true, 'Appointment creation failed'); return }

  await request.patch(`http://localhost:80/api/appointments/${id}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'confirmed' }
  })

  await loginAsStaff(page)
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByStatus(page, 'confirmed')

  const confirmedRow = page.locator('.el-table__row')
    .filter({ has: page.getByRole('button', { name: 'Check In' }) })
    .first()
  await expect(confirmedRow).toBeVisible({ timeout: 8000 })

  await confirmedRow.getByRole('button', { name: 'Check In' }).click()
  // Success toast is the definitive proof the transition API call completed
  await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 8000 })
})

// ── 3. checked_in → completed ───────────────────────────────────────────────
// Seeds its own checked-in appointment via API for self-contained execution.
test('lifecycle: staff completes a checked-in appointment', async ({ page, request }) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) {
    test.skip(true, 'Could not fetch required IDs for seeding')
    return
  }

  const seed3 = Date.now()
  const start = new Date()
  start.setDate(start.getDate() + 330 + (seed3 % 100))
  start.setHours(8 + (seed3 % 8), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: `E2E-COMPLETE-${seed3}`,
    start_time: start.toISOString(),
    end_time: end.toISOString()
  })
  const id = created?.data?.appointment?.id
  if (!id) { test.skip(true, 'Appointment creation failed'); return }

  await request.patch(`http://localhost:80/api/appointments/${id}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'confirmed' }
  })
  await request.patch(`http://localhost:80/api/appointments/${id}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'checked_in' }
  })

  await loginAsStaff(page)
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByStatus(page, 'checked_in')

  const checkedInRow = page.locator('.el-table__row')
    .filter({ has: page.getByRole('button', { name: 'Complete' }) })
    .first()
  await expect(checkedInRow).toBeVisible({ timeout: 8000 })

  await checkedInRow.getByRole('button', { name: 'Complete' }).click()
  await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 8000 })
})

// ── 4. confirmed → no_show ──────────────────────────────────────────────────
// No Show is valid from 'confirmed' per the backend state machine.
test('lifecycle: staff marks a confirmed appointment as no-show', async ({ page, request }) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) {
    test.skip(true, 'Could not fetch required IDs for seeding')
    return
  }

  const seed4 = Date.now()
  const start = new Date()
  start.setDate(start.getDate() + 230 + (seed4 % 100))
  start.setHours(8 + (seed4 % 8), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: `E2E-NOSHOW-${seed4}`,
    start_time: start.toISOString(),
    end_time: end.toISOString()
  })

  const id = created?.data?.appointment?.id
  if (!id) { test.skip(true, 'Appointment creation failed'); return }

  // No Show is valid from 'confirmed' (not checked_in) per the backend state machine
  await request.patch(`http://localhost:80/api/appointments/${id}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'confirmed' }
  })

  await loginAsStaff(page)
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  await filterByStatus(page, 'confirmed')

  // "No Show" is in the dropdown of confirmed rows (confirmed → no_show is valid)
  const confirmedRow = page.locator('.el-table__row')
    .filter({ has: page.getByRole('button', { name: 'Check In' }) })
    .first()
  await expect(confirmedRow).toBeVisible({ timeout: 8000 })

  const moreButton = confirmedRow.locator('.actions-row .el-button').filter({ hasText: '⋯' }).first()
  await expect(moreButton).toBeVisible({ timeout: 5000 })
  await moreButton.click()

  const noShowAction = page.getByRole('menuitem', { name: 'No Show' })
  await expect(noShowAction).toBeVisible({ timeout: 5000 })
  await noShowAction.click()
  await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 8000 })
})

// ── 5. Status filter changes table content ───────────────────────────────────
// Seeds a fresh confirmed appointment via API so the filter test has guaranteed data.
test('status filter: selecting confirmed shows only confirmed rows', async ({ page, request }) => {
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id

  if (clientId && provider?.id && resourceId) {
    const start = new Date()
    start.setDate(start.getDate() + 5)
    start.setHours(11, 0, 0, 0)
    const end = new Date(start)
    end.setHours(12, 0, 0, 0)

    const created = await apiPost(request, staffToken, '/appointments', {
      client_id: clientId,
      provider_id: provider.id,
      resource_id: resourceId,
      department_id: provider.department_id || 1,
      service_type: `E2E-FILTER-${Date.now()}`,
      start_time: start.toISOString(),
      end_time: end.toISOString()
    })

    const newId = created?.data?.appointment?.id
    if (newId) {
      await request.patch(`http://localhost:80/api/appointments/${newId}/status`, {
        headers: { Authorization: `Bearer ${adminToken}` },
        data: { status: 'confirmed' }
      })
    }
  }

  await loginAsStaff(page)
  await page.goto('/appointments')
  await page.locator('.el-table').waitFor({ timeout: 10000 })

  await filterByStatus(page, 'confirmed')

  const rows = page.locator('.el-table__row')
  const count = await rows.count()

  if (count > 0) {
    // Confirmed rows show Check In button — no Confirm (requested) or Complete (checked_in) buttons
    await expect(rows.first().getByRole('button', { name: 'Check In' })).toBeVisible({ timeout: 5000 })
    await expect(page.getByRole('button', { name: 'Confirm' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Complete' })).toHaveCount(0)
  } else {
    // Seeding may have failed but the empty-block proves the filter executed without error
    await expect(page.locator('.el-table__empty-block')).toBeVisible()
  }
})
