import axios from 'axios'

export const searchUsers = params => axios.get('/users/search', { params }).then(r => r.data)
export const listAdminUsers = params => axios.get('/admin/users', { params }).then(r => r.data)
export const createAdminUser = payload => axios.post('/admin/users', payload).then(r => r.data)
export const updateAdminUser = (id, payload) => axios.patch(`/admin/users/${id}`, payload).then(r => r.data)
export const deleteAdminUser = id => axios.delete(`/admin/users/${id}`).then(r => r.data)
export const bulkUserAction = payload => axios.post('/admin/users/bulk', payload).then(r => r.data)
export const resetUserPassword = (id, payload) => axios.post(`/admin/users/${id}/reset-password`, payload).then(r => r.data)
export const unlockUser = id => axios.post(`/admin/users/${id}/unlock`).then(r => r.data)
