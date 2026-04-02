import axios from 'axios'

export const getReport = (type, params) => axios.get(`/reports/${type}`, { params, responseType: 'blob' })
