// The shapes the session route handlers answer with (shared by the handlers and the browser code)
export interface SessionUser {
  id: number;
  name: string;
  role: string;
}

export interface SessionPayload {
  access_token: string;
  expires_at: string | null;
  user: SessionUser | null;
}
