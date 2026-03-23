import { execFileSync } from 'node:child_process'
import { File } from 'node:buffer'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import cors from 'cors'
import express from 'express'
import multer from 'multer'
import { UTApi } from 'uploadthing/server'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

function readPhpConfig() {
  const script = path.join(__dirname, 'read-config.php')
  const raw = execFileSync('php', [script], {
    cwd: __dirname,
    encoding: 'utf8',
    env: process.env,
  })
  return JSON.parse(raw.replace(/^\uFEFF/, ''))
}

function parseBool(value, fallback) {
  if (value === undefined || value === null || value === '') {
    return fallback
  }

  const normalized = String(value).trim().toLowerCase()
  if (['1', 'true', 'yes', 'on'].includes(normalized)) {
    return true
  }
  if (['0', 'false', 'no', 'off'].includes(normalized)) {
    return false
  }
  return fallback
}

function loadConfig() {
  const phpConfig = readPhpConfig()
  return {
    enabled: parseBool(process.env.UPLOADTHING_ENABLED, phpConfig.enabled === true),
    token: process.env.UPLOADTHING_TOKEN || phpConfig.token || '',
    host: process.env.UPLOADTHING_BRIDGE_HOST || phpConfig.host || '127.0.0.1',
    port: Number.parseInt(process.env.UPLOADTHING_BRIDGE_PORT || phpConfig.port || '8091', 10),
    internalSharedSecret:
      process.env.UPLOADTHING_BRIDGE_INTERNAL_SHARED_SECRET ||
      phpConfig.internal_shared_secret ||
      'bugcatcher-uploadthing-dev-secret',
  }
}

const config = loadConfig()
const app = express()
const upload = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: 60 * 1024 * 1024,
    files: 8,
  },
})

const utapi = new UTApi({
  token: config.token,
})

function sendJson(response, statusCode, body) {
  response.status(statusCode).json(body)
}

function requireInternalAuth(request, response, next) {
  const header = String(request.headers.authorization || '')
  if (header !== `Bearer ${config.internalSharedSecret}`) {
    sendJson(response, 401, {
      ok: false,
      error: { code: 'unauthorized', message: 'Invalid UploadThing bridge shared secret.' },
    })
    return
  }
  next()
}

function normalizeScope(value) {
  const input = String(value || '').trim().toLowerCase()
  if (input === '') {
    return 'misc'
  }
  return input.replace(/[^a-z0-9._-]+/g, '-').slice(0, 60) || 'misc'
}

app.use(cors())
app.use(express.json({ limit: '1mb' }))

app.get('/health', (_request, response) => {
  sendJson(response, 200, {
    ok: true,
    data: {
      status: config.enabled && config.token ? 'ok' : 'disabled',
    },
  })
})

app.post('/internal/upload', requireInternalAuth, upload.array('files[]', 8), async (request, response) => {
  if (!config.enabled || !config.token) {
    sendJson(response, 503, {
      ok: false,
      error: { code: 'uploadthing_disabled', message: 'UploadThing is not configured.' },
    })
    return
  }

  const files = Array.isArray(request.files) ? request.files : []
  if (!files.length) {
    sendJson(response, 422, {
      ok: false,
      error: { code: 'missing_files', message: 'At least one file is required.' },
    })
    return
  }

  const scope = normalizeScope(request.body.scope)

  try {
    const uploadFiles = files.map((file) => new File([file.buffer], file.originalname, { type: file.mimetype }))
    const results = await utapi.uploadFiles(uploadFiles)
    const normalized = Array.isArray(results) ? results : [results]
    const failures = normalized.filter((entry) => entry?.error)
    if (failures.length > 0) {
      const message = failures[0]?.error?.message || 'UploadThing failed to store one or more files.'
      sendJson(response, 502, {
        ok: false,
        error: { code: 'upload_failed', message },
      })
      return
    }

    sendJson(response, 201, {
      ok: true,
      data: {
        files: normalized.map((entry, index) => ({
          key: entry.data.key,
          url: entry.data.url,
          name: entry.data.name || files[index].originalname,
          size: entry.data.size || files[index].size,
          type: files[index].mimetype,
          scope,
        })),
      },
    })
  } catch (error) {
    sendJson(response, 500, {
      ok: false,
      error: {
        code: 'upload_exception',
        message: error instanceof Error ? error.message : 'UploadThing upload failed.',
      },
    })
  }
})

app.delete('/internal/files', requireInternalAuth, async (request, response) => {
  const keys = Array.isArray(request.body?.keys)
    ? request.body.keys.map((key) => String(key || '').trim()).filter(Boolean)
    : []

  if (!keys.length) {
    sendJson(response, 422, {
      ok: false,
      error: { code: 'missing_keys', message: 'At least one storage key is required.' },
    })
    return
  }

  try {
    await utapi.deleteFiles(keys)
    sendJson(response, 200, {
      ok: true,
      data: { deleted: keys.length },
    })
  } catch (error) {
    sendJson(response, 500, {
      ok: false,
      error: {
        code: 'delete_exception',
        message: error instanceof Error ? error.message : 'UploadThing delete failed.',
      },
    })
  }
})

app.listen(config.port, config.host, () => {
  process.stdout.write(`[bugcatcher-uploadthing] Listening on http://${config.host}:${config.port} (enabled=${config.enabled})\n`)
})
