// The auth half of the API layer is injected: the Mini App keeps its Telegram token and re-login, the partner web its
// access token in memory and its cookie refresh. Both hand this module a `fetch` that signs requests, or build one
// with createAuthFetch from a small adapter.

// The fetch of an openapi-fetch client: it is given one ready Request.
export type RequestFetch = (request: Request) => Promise<Response>;

export interface AuthAdapter {
  // The access token to sign a request with now; none when signed out.
  getToken: () => string | null | undefined | Promise<string | null | undefined>;
  // Called after a 401. `sentWith` is the token the failed request carried. Returns a token to replay the request
  // with, or null when the session cannot be renewed. Single-flight and cool-downs belong to the adapter: many
  // requests fail at once when a token expires.
  renewToken: (sentWith: string | null) => Promise<string | null>;
  // The session is over (renewal failed, or the replayed request was refused again)
  onSignedOut?: () => void;
}

// A fetch that adds `Authorization: Bearer <token>` and, on a 401, renews the session once and replays the request
// (never twice: a replay that is refused again signs out). `baseFetch` is looked up on every call by default, so a
// test (or a polyfill) that replaces the global fetch is honoured.
export function createAuthFetch(adapter: AuthAdapter, baseFetch?: RequestFetch): RequestFetch {
  const send: RequestFetch = baseFetch ?? ((request) => globalThis.fetch(request));

  return async (request) => {
    // The body is read once: keep a copy for the replay
    const replay = request.clone();
    const sentWith = (await adapter.getToken()) || null;
    if (sentWith) request.headers.set('Authorization', `Bearer ${sentWith}`);

    const response = await send(request);
    if (response.status !== 401) return response;

    const token = await adapter.renewToken(sentWith);
    if (!token) {
      adapter.onSignedOut?.();
      return response;
    }

    replay.headers.set('Authorization', `Bearer ${token}`);
    const second = await send(replay);
    if (second.status === 401) adapter.onSignedOut?.();
    return second;
  };
}
