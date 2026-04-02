import axios from 'axios'

export const listAppointments = params => axios.get('/appointments', { params }).then(r => r.data)
export const checkConflict = params => axios.get('/appointments', { params }).then(r => r.data)
export const createAppointment = payload => axios.post('/appointments', payload).then(r => r.data)
export const updateAppointmentStatus = (id, payload) => axios.patch(`/appointments/${id}/status`, payload).then(r => r.data)
export const rescheduleAppointment = (id, payload) => axios.patch(`/appointments/${id}`, payload).then(r => r.data)
export const getAppointmentVersions = id => axios.get(`/appointments/${id}/versions`).then(r => r.data)
