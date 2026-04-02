import axios from 'axios'

export const listRecycleBin = params => axios.get('/admin/recycle-bin', { params }).then(r => r.data)
export const restoreItem = (type, id) => axios.post(`/admin/recycle-bin/${type}/${id}/restore`).then(r => r.data)
export const deleteItem = (type, id) => axios.delete(`/admin/recycle-bin/${type}/${id}`).then(r => r.data)
export const bulkRestore = items => axios.post('/admin/recycle-bin/bulk-restore', { items }).then(r => r.data)
export const bulkDelete = items => axios.delete('/admin/recycle-bin/bulk', { data: { items } }).then(r => r.data)
