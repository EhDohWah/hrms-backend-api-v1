# API Integration Guide - Frontend ↔ Backend

## Table of Contents
1. [Overview](#overview)
2. [API Service Architecture](#api-service-architecture)
3. [Authentication Flow](#authentication-flow)
4. [Service Layer Details](#service-layer-details)
5. [State Management Integration](#state-management-integration)
6. [Error Handling](#error-handling)
7. [Real-time Integration](#real-time-integration)
8. [Best Practices](#best-practices)

---

## Overview

This guide explains how the Vue 3 frontend integrates with the Laravel 11 backend API for user management operations. The integration uses:

- **HTTP Protocol**: REST API over HTTPS
- **Authentication**: Bearer token (Laravel Sanctum)
- **Data Format**: JSON (with FormData for file uploads)
- **Real-time**: Laravel Echo with Reverb WebSocket

---

## API Service Architecture

### Base API Service

**File**: `src/services/api.service.js`

**Purpose**: Core HTTP client for all API requests

**Configuration**:
```javascript
const API_CONFIG = {
  BASE_URL: process.env.VUE_APP_API_BASE_URL
    || 'https://hrms-backend-api-v1-main-wrhlmg.laravel.cloud/api/v1',
  TIMEOUT: 30000, // 30 seconds
  HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
}
```

**Core Methods**:

```javascript
class ApiService {
  constructor() {
    this.baseURL = API_CONFIG.BASE_URL
    this.headers = { ...API_CONFIG.HEADERS }
  }

  /**
   * Set authentication token
   * @param {string} token - Bearer token
   */
  setAuthToken(token) {
    if (token) {
      this.headers['Authorization'] = `Bearer ${token}`
    } else {
      delete this.headers['Authorization']
    }
  }

  /**
   * Generic request handler with error handling
   * @param {string} endpoint - API endpoint
   * @param {object} options - Fetch options
   * @returns {Promise} Response data
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`

    const config = {
      ...options,
      headers: {
        ...this.headers,
        ...options.headers
      }
    }

    try {
      const response = await fetch(url, config)

      // Handle non-JSON responses (like file downloads)
      const contentType = response.headers.get('content-type')

      if (contentType && contentType.includes('application/json')) {
        const data = await response.json()

        if (!response.ok) {
          // Handle 401 - Attempt token refresh
          if (response.status === 401) {
            const refreshed = await this.attemptRefreshToken()

            if (refreshed) {
              // Retry original request with new token
              return this.retryRequest(endpoint, options)
            } else {
              // Refresh failed, redirect to login
              this.handleAuthFailure()
            }
          }

          throw new Error(data.message || `HTTP ${response.status}`)
        }

        return data
      } else {
        // Return blob for file downloads
        return await response.blob()
      }

    } catch (error) {
      console.error('API Request Error:', error)
      throw error
    }
  }

  /**
   * GET request
   */
  async get(endpoint, params = {}) {
    const queryString = new URLSearchParams(params).toString()
    const url = queryString ? `${endpoint}?${queryString}` : endpoint

    return this.request(url, {
      method: 'GET'
    })
  }

  /**
   * POST request (JSON)
   */
  async post(endpoint, data = {}) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data)
    })
  }

  /**
   * POST request (FormData for file uploads)
   */
  async postFormData(endpoint, formData) {
    // Remove Content-Type header - browser will set it with boundary
    const headers = { ...this.headers }
    delete headers['Content-Type']

    return this.request(endpoint, {
      method: 'POST',
      headers,
      body: formData
    })
  }

  /**
   * PUT request
   */
  async put(endpoint, data = {}) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data)
    })
  }

  /**
   * DELETE request
   */
  async delete(endpoint, data = {}) {
    return this.request(endpoint, {
      method: 'DELETE',
      body: data ? JSON.stringify(data) : undefined
    })
  }

  /**
   * PATCH request
   */
  async patch(endpoint, data = {}) {
    return this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data)
    })
  }

  /**
   * Attempt to refresh expired token
   */
  async attemptRefreshToken() {
    try {
      const response = await this.post('/refresh-token')

      if (response.access_token) {
        // Update token in storage and headers
        localStorage.setItem('token', response.access_token)
        this.setAuthToken(response.access_token)

        return true
      }

      return false
    } catch (error) {
      console.error('Token refresh failed:', error)
      return false
    }
  }

  /**
   * Retry failed request with new token
   */
  async retryRequest(endpoint, options) {
    return this.request(endpoint, options)
  }

  /**
   * Handle authentication failure
   */
  handleAuthFailure() {
    // Clear auth data
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('userRole')
    localStorage.removeItem('permissions')

    // Redirect to login
    window.location.href = '/login'
  }
}

export const apiService = new ApiService()
```

---

### Authentication Service

**File**: `src/services/auth.service.js`

**Purpose**: Authentication-specific API calls

```javascript
import { apiService } from './api.service'

class AuthService {
  /**
   * Login user
   * @param {object} credentials - {email, password}
   * @returns {Promise} User data with token
   */
  async login(credentials) {
    const response = await apiService.post('/login', credentials)

    // Backend returns: { access_token, token_type, expires_in, user }
    return response
  }

  /**
   * Logout user
   * @returns {Promise}
   */
  async logout() {
    const response = await apiService.post('/logout')

    // Clear token from API service
    apiService.setAuthToken(null)

    return response
  }

  /**
   * Refresh authentication token
   * @returns {Promise} New token data
   */
  async refreshToken() {
    const response = await apiService.post('/refresh-token')

    // Update token in API service
    if (response.access_token) {
      apiService.setAuthToken(response.access_token)
    }

    return response
  }

  /**
   * Get current authenticated user
   * @returns {Promise} User data with roles and permissions
   */
  async getCurrentUser() {
    return apiService.get('/user/user')
  }

  /**
   * Register new user (if enabled)
   * @param {object} userData
   * @returns {Promise}
   */
  async register(userData) {
    return apiService.post('/register', userData)
  }

  /**
   * Request password reset
   * @param {string} email
   * @returns {Promise}
   */
  async forgotPassword(email) {
    return apiService.post('/forgot-password', { email })
  }

  /**
   * Reset password with token
   * @param {object} resetData - {token, email, password, password_confirmation}
   * @returns {Promise}
   */
  async resetPassword(resetData) {
    return apiService.post('/reset-password', resetData)
  }

  /**
   * Verify email with token
   * @param {string} token
   * @returns {Promise}
   */
  async verifyEmail(token) {
    return apiService.post('/verify-email', { token })
  }

  /**
   * Check if user is authenticated
   * @returns {boolean}
   */
  isAuthenticated() {
    const token = localStorage.getItem('token')
    const expiration = localStorage.getItem('tokenExpiration')

    if (!token || !expiration) return false

    // Check if token is expired
    return new Date().getTime() < parseInt(expiration)
  }

  /**
   * Get stored token
   * @returns {string|null}
   */
  getToken() {
    return localStorage.getItem('token')
  }

  /**
   * Get current user from storage
   * @returns {object|null}
   */
  getCurrentUserFromStorage() {
    const userStr = localStorage.getItem('user')
    return userStr ? JSON.parse(userStr) : null
  }

  /**
   * Get current user role
   * @returns {string|null}
   */
  getCurrentRole() {
    return localStorage.getItem('userRole')
  }

  /**
   * Get redirect path based on role
   * @param {string} role
   * @returns {string}
   */
  getRedirectPath(role) {
    const roleRoutes = {
      'admin': '/dashboard/admin-dashboard',
      'hr-manager': '/dashboard/hr-manager-dashboard',
      'hr-assistant-senior': '/dashboard/hr-assistant-senior-dashboard',
      'hr-assistant-junior': '/dashboard/hr-assistant-junior-dashboard',
      'site-admin': '/dashboard/site-admin-dashboard'
    }

    return roleRoutes[role] || '/dashboard'
  }
}

export const authService = new AuthService()
```

---

### Admin Service

**File**: `src/services/admin.service.js`

**Purpose**: Admin and user management API calls

```javascript
import { apiService } from './api.service'

class AdminService {
  /**
   * Get all users
   * @returns {Promise<Array>}
   */
  async getAllUsers() {
    return apiService.get('/admin/users')
  }

  /**
   * Get specific user details
   * @param {number} userId
   * @returns {Promise<object>}
   */
  async getUserDetails(userId) {
    return apiService.get(`/admin/users/${userId}`)
  }

  /**
   * Create new user
   * @param {FormData|object} userData
   * @returns {Promise}
   */
  async createUser(userData) {
    // Check if already FormData
    if (userData instanceof FormData) {
      return apiService.postFormData('/admin/users', userData)
    }

    // Convert object to FormData for file upload support
    const formData = new FormData()

    Object.keys(userData).forEach(key => {
      if (key === 'permissions' && Array.isArray(userData[key])) {
        // Append array items
        userData[key].forEach((perm, index) => {
          formData.append(`permissions[${index}]`, perm)
        })
      } else if (userData[key] !== null && userData[key] !== undefined) {
        formData.append(key, userData[key])
      }
    })

    return apiService.postFormData('/admin/users', formData)
  }

  /**
   * Update existing user
   * @param {number} userId
   * @param {FormData|object} userData
   * @returns {Promise}
   */
  async updateUser(userId, userData) {
    // Laravel requires _method=PUT for FormData
    if (userData instanceof FormData) {
      userData.append('_method', 'PUT')
      return apiService.postFormData(`/admin/users/${userId}`, userData)
    }

    const formData = new FormData()
    formData.append('_method', 'PUT')

    Object.keys(userData).forEach(key => {
      if (key === 'permissions' && Array.isArray(userData[key])) {
        userData[key].forEach((perm, index) => {
          formData.append(`permissions[${index}]`, perm)
        })
      } else if (userData[key] !== null && userData[key] !== undefined) {
        formData.append(key, userData[key])
      }
    })

    return apiService.postFormData(`/admin/users/${userId}`, formData)
  }

  /**
   * Delete user
   * @param {number} userId
   * @returns {Promise}
   */
  async deleteUser(userId) {
    return apiService.delete(`/admin/users/${userId}`)
  }

  /**
   * Get all roles
   * @returns {Promise<Array>}
   */
  async getAllRoles() {
    return apiService.get('/admin/roles')
  }

  /**
   * Get all permissions
   * @returns {Promise<Array>}
   */
  async getAllPermissions() {
    return apiService.get('/admin/permissions')
  }

  /**
   * Update role permissions
   * @param {number} roleId
   * @param {Array} permissions
   * @returns {Promise}
   */
  async updateRolePermissions(roleId, permissions) {
    return apiService.put(`/admin/roles/${roleId}/permissions`, {
      permissions
    })
  }
}

export const adminService = new AdminService()
```

---

## Authentication Flow

### Complete Login Sequence

```
┌─────────────────────────────────────────────────────────────┐
│                    1. User Submits Login Form              │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          2. Component calls authStore.login()               │
│          login-index.vue                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          3. Store calls authService.login()                 │
│          authStore.js → authService.js                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          4. Service calls apiService.post('/login')         │
│          authService.js → api.service.js                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          5. HTTP POST to Laravel Backend                    │
│          POST /api/v1/login                                 │
│          {email, password}                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          6. Backend validates and creates token             │
│          AuthController@login                               │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          7. Backend returns response                        │
│          {                                                  │
│            access_token,                                    │
│            token_type: "Bearer",                            │
│            expires_in: 21600,                               │
│            user: {id, name, email, roles, permissions}      │
│          }                                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          8. Store processes response                        │
│          authStore.setAuthData(response)                    │
│          - Save token to localStorage                       │
│          - Save user to localStorage                        │
│          - Save role to localStorage                        │
│          - Save permissions to localStorage                 │
│          - Calculate expiration timestamp                   │
│          - Set token in apiService headers                  │
│          - Start auto-logout timer                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          9. Initialize real-time connection                 │
│          initEcho(token)                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│          10. Redirect to dashboard                          │
│          router.replace(redirectPath)                       │
└─────────────────────────────────────────────────────────────┘
```

### Token Storage

```javascript
// authStore.js - setAuthData method

setAuthData(response) {
  const { access_token, expires_in, user } = response

  // Store token
  this.token = access_token
  localStorage.setItem('token', access_token)

  // Store user
  this.user = user
  localStorage.setItem('user', JSON.stringify(user))

  // Store role (first role in array)
  this.userRole = user.roles[0]?.name || null
  localStorage.setItem('userRole', this.userRole)

  // Store permissions (array of permission names)
  const permissions = user.permissions.map(p => p.name)
  this.permissions = permissions
  localStorage.setItem('permissions', JSON.stringify(permissions))

  // Calculate expiration timestamp (default 24 hours if not provided)
  const expiresIn = expires_in || 86400 // seconds
  const expirationTime = new Date().getTime() + (expiresIn * 1000)
  this.tokenExpiration = expirationTime
  localStorage.setItem('tokenExpiration', expirationTime.toString())

  // Set token in API service for all future requests
  apiService.setAuthToken(access_token)

  // Set auto-logout timer
  this.setTokenTimer(expiresIn * 1000)
}
```

### Auto-Logout Timer

```javascript
// authStore.js

setTokenTimer(duration) {
  // Clear existing timer
  if (this.tokenTimer) {
    clearTimeout(this.tokenTimer)
  }

  // Set new timer to logout when token expires
  this.tokenTimer = setTimeout(() => {
    console.log('Token expired - logging out')
    this.logout()
  }, duration)
}
```

---

## State Management Integration

### Auth Store Actions

**File**: `src/stores/authStore.js`

```javascript
import { defineStore } from 'pinia'
import { authService } from '@/services/auth.service'
import { apiService } from '@/services/api.service'
import { initEcho } from '@/plugins/echo'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    userRole: localStorage.getItem('userRole') || null,
    permissions: JSON.parse(localStorage.getItem('permissions') || '[]'),
    tokenExpiration: parseInt(localStorage.getItem('tokenExpiration') || '0'),
    loading: false,
    error: null,
    tokenTimer: null,
    justLoggedIn: localStorage.getItem('justLoggedIn') === 'true'
  }),

  getters: {
    isAuthenticated: (state) => {
      if (!state.token || !state.user) return false

      // Check expiration
      const now = new Date().getTime()
      return now < state.tokenExpiration
    },

    currentUser: (state) => state.user,

    userPermissions: (state) => state.permissions,

    isAdmin: (state) => state.userRole === 'admin',

    isHRManager: (state) => state.userRole === 'hr-manager',

    isHRAssistantSenior: (state) => state.userRole === 'hr-assistant-senior',

    isHRAssistantJunior: (state) => state.userRole === 'hr-assistant-junior',

    isSiteAdmin: (state) => state.userRole === 'site-admin'
  },

  actions: {
    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const response = await authService.login(credentials)

        this.setAuthData(response)

        // Set just logged in flag
        this.justLoggedIn = true
        localStorage.setItem('justLoggedIn', 'true')

        return response
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await authService.logout()
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.clearAuthData()

        // Reset all stores
        this.resetAllStores()

        // Redirect to login
        window.location.href = '/login'
      }
    },

    async updateUserData() {
      try {
        const user = await authService.getCurrentUser()

        this.user = user
        localStorage.setItem('user', JSON.stringify(user))

        // Update role and permissions
        this.userRole = user.roles[0]?.name || null
        localStorage.setItem('userRole', this.userRole)

        const permissions = user.permissions.map(p => p.name)
        this.permissions = permissions
        localStorage.setItem('permissions', JSON.stringify(permissions))

      } catch (error) {
        console.error('Failed to update user data:', error)
        throw error
      }
    },

    async refreshToken() {
      try {
        const response = await authService.refreshToken()

        // Update token
        this.token = response.access_token
        localStorage.setItem('token', response.access_token)

        // Update expiration
        const expiresIn = response.expires_in || 86400
        const expirationTime = new Date().getTime() + (expiresIn * 1000)
        this.tokenExpiration = expirationTime
        localStorage.setItem('tokenExpiration', expirationTime.toString())

        // Reset timer
        this.setTokenTimer(expiresIn * 1000)

        return response
      } catch (error) {
        console.error('Token refresh failed:', error)
        this.logout()
        throw error
      }
    },

    setAuthData(response) {
      const { access_token, expires_in, user } = response

      this.token = access_token
      localStorage.setItem('token', access_token)

      this.user = user
      localStorage.setItem('user', JSON.stringify(user))

      this.userRole = user.roles[0]?.name || null
      localStorage.setItem('userRole', this.userRole)

      const permissions = user.permissions.map(p => p.name)
      this.permissions = permissions
      localStorage.setItem('permissions', JSON.stringify(permissions))

      const expiresIn = expires_in || 86400
      const expirationTime = new Date().getTime() + (expiresIn * 1000)
      this.tokenExpiration = expirationTime
      localStorage.setItem('tokenExpiration', expirationTime.toString())

      apiService.setAuthToken(access_token)

      this.setTokenTimer(expiresIn * 1000)

      // Initialize Echo for real-time
      initEcho(access_token)
    },

    clearAuthData() {
      this.token = null
      this.user = null
      this.userRole = null
      this.permissions = []
      this.tokenExpiration = 0
      this.justLoggedIn = false

      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('userRole')
      localStorage.removeItem('permissions')
      localStorage.removeItem('tokenExpiration')
      localStorage.removeItem('justLoggedIn')

      apiService.setAuthToken(null)

      if (this.tokenTimer) {
        clearTimeout(this.tokenTimer)
        this.tokenTimer = null
      }
    },

    checkAuth() {
      // Verify stored token is still valid
      if (this.token && this.user) {
        const now = new Date().getTime()

        if (now >= this.tokenExpiration) {
          // Token expired
          this.clearAuthData()
          return false
        }

        // Set token in API service
        apiService.setAuthToken(this.token)

        // Reset timer for remaining time
        const remainingTime = this.tokenExpiration - now
        this.setTokenTimer(remainingTime)

        // Initialize Echo
        initEcho(this.token)

        return true
      }

      return false
    },

    getRedirectPath() {
      return authService.getRedirectPath(this.userRole)
    },

    resetAllStores() {
      // Import and reset other Pinia stores
      const { useAdminStore } = require('./adminStore')
      const adminStore = useAdminStore()
      adminStore.$reset()
    },

    setTokenTimer(duration) {
      if (this.tokenTimer) {
        clearTimeout(this.tokenTimer)
      }

      this.tokenTimer = setTimeout(() => {
        console.log('Token expired - auto logout')
        this.logout()
      }, duration)
    }
  }
})
```

---

### Admin Store Actions

**File**: `src/stores/adminStore.js`

```javascript
import { defineStore } from 'pinia'
import { adminService } from '@/services/admin.service'

export const useAdminStore = defineStore('admin', {
  state: () => ({
    users: [],
    currentUser: null,
    roles: [],
    permissions: [],
    loading: false,
    error: null,
    statistics: {
      totalUsers: 0,
      activeUsers: 0,
      inactiveUsers: 0
    }
  }),

  getters: {
    getUserById: (state) => (id) => {
      return state.users.find(user => user.id === id)
    },

    getActiveUsers: (state) => {
      return state.users.filter(user => user.status === 'active')
    },

    getInactiveUsers: (state) => {
      return state.users.filter(user => user.status !== 'active')
    },

    getUsersByRole: (state) => (roleId) => {
      return state.users.filter(user =>
        user.roles.some(role => role.id === roleId)
      )
    },

    getUsersWithRoleName: (state) => {
      return state.users.map(user => ({
        ...user,
        roleName: user.roles[0]?.name || 'No Role'
      }))
    }
  },

  actions: {
    async fetchUsers() {
      this.loading = true
      this.error = null

      try {
        const users = await adminService.getAllUsers()
        this.users = users
        this.updateStatistics()

        return users
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async getUserDetails(id) {
      this.loading = true
      this.error = null

      try {
        const user = await adminService.getUserDetails(id)
        this.currentUser = user

        return user
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async createUser(userData) {
      this.loading = true
      this.error = null

      try {
        const response = await adminService.createUser(userData)

        // Refresh user list
        await this.fetchUsers()

        return response
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateUser(id, userData) {
      this.loading = true
      this.error = null

      try {
        const response = await adminService.updateUser(id, userData)

        // Refresh user list
        await this.fetchUsers()

        return response
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteUser(id) {
      this.loading = true
      this.error = null

      try {
        const response = await adminService.deleteUser(id)

        // Remove from local state
        this.users = this.users.filter(user => user.id !== id)
        this.updateStatistics()

        return response
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchRoles() {
      try {
        const roles = await adminService.getAllRoles()
        this.roles = roles

        return roles
      } catch (error) {
        this.error = error.message
        throw error
      }
    },

    async fetchPermissions() {
      try {
        const permissions = await adminService.getAllPermissions()
        this.permissions = permissions

        return permissions
      } catch (error) {
        this.error = error.message
        throw error
      }
    },

    setCurrentUser(user) {
      this.currentUser = user
    },

    updateStatistics() {
      this.statistics.totalUsers = this.users.length
      this.statistics.activeUsers = this.getActiveUsers.length
      this.statistics.inactiveUsers = this.getInactiveUsers.length
    },

    getRoleName(user) {
      return user.roles[0]?.name || 'No Role'
    }
  }
})
```

---

## Error Handling

### API Error Response Format

```javascript
// Backend returns errors in this format:
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

### Frontend Error Handling

**In Services**:
```javascript
async createUser(userData) {
  try {
    return await apiService.postFormData('/admin/users', userData)
  } catch (error) {
    // Error is already formatted from apiService
    throw error
  }
}
```

**In Stores**:
```javascript
async createUser(userData) {
  this.loading = true
  this.error = null

  try {
    const response = await adminService.createUser(userData)
    await this.fetchUsers()
    return response
  } catch (error) {
    // Store error message
    this.error = error.message

    // Re-throw for component handling
    throw error
  } finally {
    this.loading = false
  }
}
```

**In Components**:
```javascript
async submitNewUser() {
  this.loading = true

  try {
    await adminStore.createUser(this.formData)

    this.showAlert('User created successfully', 'success')
    this.closeModal()
    this.$emit('user-added')

  } catch (error) {
    // Display user-friendly error
    const message = error.message || 'Failed to create user'
    this.showAlert(message, 'danger')

  } finally {
    this.loading = false
  }
}
```

---

## Real-time Integration

### Laravel Echo Configuration

**File**: `src/plugins/echo.js`

```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

let echoInstance = null

export function initEcho(token) {
  if (!token) {
    console.warn('No token provided for Echo initialization')
    return null
  }

  // Disconnect existing instance
  if (echoInstance) {
    echoInstance.disconnect()
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: process.env.VUE_APP_REVERB_APP_KEY,
    wsHost: process.env.VUE_APP_REVERB_HOST,
    wsPort: process.env.VUE_APP_REVERB_PORT || 8080,
    wssPort: process.env.VUE_APP_REVERB_PORT || 8080,
    forceTLS: (process.env.VUE_APP_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
      headers: {
        Authorization: `Bearer ${token}`
      }
    }
  })

  return echoInstance
}

export function getEcho() {
  return echoInstance
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}
```

### Listening to User Events

```javascript
import { getEcho } from '@/plugins/echo'

// In component or store
mounted() {
  const echo = getEcho()

  if (echo) {
    // Listen for user updates
    echo.private(`user.${this.user.id}`)
      .listen('UserUpdated', (event) => {
        console.log('User updated:', event)
        this.refreshUserData()
      })
      .listen('RoleChanged', (event) => {
        console.log('Role changed:', event)
        this.handleRoleChange(event)
      })
  }
}

beforeUnmount() {
  const echo = getEcho()

  if (echo) {
    echo.leave(`user.${this.user.id}`)
  }
}
```

---

## Best Practices

### 1. Token Management

**DO**:
- Store token in localStorage for persistence
- Set token in API service headers on login
- Clear token on logout
- Implement auto-refresh before expiration
- Use auto-logout timer for expired tokens

**DON'T**:
- Store token in cookies (backend uses Sanctum)
- Store sensitive data unencrypted
- Ignore token expiration

### 2. Error Handling

**DO**:
- Catch errors at every layer (service → store → component)
- Display user-friendly error messages
- Log errors for debugging
- Handle network errors separately from API errors
- Implement retry logic for failed requests

**DON'T**:
- Display raw error messages to users
- Ignore error responses
- Let errors crash the app

### 3. State Management

**DO**:
- Use Pinia stores for global state
- Keep component state local when possible
- Normalize data structures in stores
- Use getters for derived state
- Reset stores on logout

**DON'T**:
- Duplicate data between stores and components
- Mutate state directly (use actions)
- Store computed values in state

### 4. API Calls

**DO**:
- Use service layer for all API calls
- Show loading indicators during requests
- Implement request cancellation for component unmount
- Use FormData for file uploads
- Handle pagination on backend

**DON'T**:
- Make API calls directly from components
- Ignore loading/error states
- Send files as JSON

### 5. Security

**DO**:
- Validate all input on frontend AND backend
- Sanitize user input
- Use HTTPS in production
- Implement CORS properly
- Check permissions before rendering UI elements

**DON'T**:
- Trust client-side validation alone
- Store passwords or sensitive data
- Expose API keys in frontend code

---

**Last Updated**: 2025-12-17
**API Version**: v1
**Framework**: Vue 3 + Laravel 11
