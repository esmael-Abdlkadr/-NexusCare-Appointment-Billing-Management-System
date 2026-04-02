import axios from 'axios'

export const listWaitlist = (page = 1, perPage = 20) =>
  axios.get('/waitlist', { params: { page, per_page: perPage } }).then(r => r.data)
export const addWaitlistEntry = payload => axios.post('/waitlist', payload).then(r => r.data)
export const confirmBackfill = (id, payload) => axios.post(`/waitlist/${id}/confirm-backfill`, payload).then(r => r.data)
export const removeWaitlistEntry = id => axios.delete(`/waitlist/${id}`).then(r => r.data)
