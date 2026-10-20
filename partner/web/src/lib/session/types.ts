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

// One of the staff accounts a phone holds (the same person in two stores), to choose from at sign-in
export interface AccountChoice {
  user_id: number;
  role: string;
  store: string;
  branch: string | null;
}
