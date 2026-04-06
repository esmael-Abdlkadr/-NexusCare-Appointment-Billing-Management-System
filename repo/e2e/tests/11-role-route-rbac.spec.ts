/**
 * Role-based route access control — direct URL enforcement layer.
 *
 * These tests verify that navigating to a restricted URL directly (bypassing the
 * sidebar UI) results in a /forbidden redirect. They test the navigation-guard
 * enforcement path, not sidebar visibility.
 *
 * All tests log in before navigating, so the router guard must redirect to /forbidden
 * (not /login — that redirect is for unauthenticated users only).
 *
 * See also: 14-coverage-gaps.spec.ts (tests sidebar menu visibility per role).
 * The two suites are complementary: this file covers enforcement; 14 covers presentation.
 */
import { expect, test } from '@playwright/test'
import { loginAsAdmin, loginAsReviewer, loginAsStaff } from '../helpers/auth'

test('staff cannot directly navigate to /admin/users — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/admin/users')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('staff cannot directly navigate to /ledger — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/ledger')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('staff cannot directly navigate to /audit-logs — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/audit-logs')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /admin/users — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/admin/users')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer can navigate to /audit-logs', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/audit-logs')
  await expect(page).not.toHaveURL(/\/forbidden|\/login/)
  await expect(page.locator('.el-table')).toBeVisible({ timeout: 8000 })
})

test('staff cannot directly navigate to /reports — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/reports')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('staff cannot directly navigate to /admin/moderation — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/admin/moderation')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('staff cannot directly navigate to /admin/recycle — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/admin/recycle')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('staff cannot directly navigate to /reconciliation/import — gets forbidden', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/reconciliation/import')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /ledger — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/ledger')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /admin/moderation — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/admin/moderation')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /admin/recycle — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/admin/recycle')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /waitlist — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/waitlist')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /payments/post — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/payments/post')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('reviewer cannot directly navigate to /appointments/create — gets forbidden', async ({ page }) => {
  await loginAsReviewer(page)
  await page.goto('/appointments/create')
  await expect(page).toHaveURL(/\/forbidden/)
})

test('admin can navigate to all restricted pages', async ({ page }) => {
  await loginAsAdmin(page)

  for (const path of [
    '/admin/users', '/admin/moderation', '/admin/recycle',
    '/ledger', '/audit-logs', '/reports',
    '/reconciliation/import', '/reconciliation/exceptions', '/reconciliation/anomalies'
  ]) {
    await page.goto(path)
    await expect(page).not.toHaveURL(/\/forbidden|\/login/)
  }
})
