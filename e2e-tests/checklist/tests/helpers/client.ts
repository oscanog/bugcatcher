import { APIRequestContext, APIResponse } from "@playwright/test";

export type ApiEnvelope<T> =
  | { ok: true; data: T }
  | { ok: false; error: { code: string; message: string; details?: unknown } };

export async function parseJson<T>(res: APIResponse): Promise<T> {
  const text = await res.text();
  try {
    return JSON.parse(text) as T;
  } catch (error) {
    throw new Error(`Expected JSON response. Status=${res.status()} Body=${text}`);
  }
}

export async function apiGet<T>(
  request: APIRequestContext,
  url: string
): Promise<{ res: APIResponse; body: ApiEnvelope<T> }> {
  const res = await request.get(url);
  const body = await parseJson<ApiEnvelope<T>>(res);
  return { res, body };
}

export async function apiPostJson<T>(
  request: APIRequestContext,
  url: string,
  payload: unknown
): Promise<{ res: APIResponse; body: ApiEnvelope<T> }> {
  const res = await request.post(url, { data: payload });
  const body = await parseJson<ApiEnvelope<T>>(res);
  return { res, body };
}

export async function apiPatchJson<T>(
  request: APIRequestContext,
  url: string,
  payload: unknown
): Promise<{ res: APIResponse; body: ApiEnvelope<T> }> {
  const res = await request.fetch(url, {
    method: "PATCH",
    data: payload,
  });
  const body = await parseJson<ApiEnvelope<T>>(res);
  return { res, body };
}

export async function apiDelete<T>(
  request: APIRequestContext,
  url: string
): Promise<{ res: APIResponse; body: ApiEnvelope<T> }> {
  const res = await request.fetch(url, {
    method: "DELETE",
  });
  const body = await parseJson<ApiEnvelope<T>>(res);
  return { res, body };
}

