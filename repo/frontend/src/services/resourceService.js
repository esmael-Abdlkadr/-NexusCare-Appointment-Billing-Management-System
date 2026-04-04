import axios from 'axios'

export const listResources = params => axios.get('/resources', { params }).then(r => r.data)
