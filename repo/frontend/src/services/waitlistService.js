import axios from 'axios'

export const listWaitlist = (pageOrParams = 1, perPage = 20) => {
  const params = typeof pageOrParams === 'object'
    ? pageOrParams
    : { page: pageOrParams, per_page: perPage }
  return axios.get('/waitlist', { params }).then(r => r.data)
}
export const addWaitlistEntry = payload => axios.post('/waitlist', payload).then(r => r.data)
export const confirmBackfill = (id, payload) => axios.post(`/waitlist/${id}/confirm-backfill`, payload).then(r => r.data)
export const removeWaitlistEntry = id => axios.delete(`/waitlist/${id}`).then(r => r.data)
