'use client';

import { AuthLayout } from './auth/AuthLayout';
import { LoginFlow } from './auth/LoginFlow';

// /invite/<token>: a new member chooses a password, sets up 2FA and signs in. The token is the one-time link of the invite.
export function InviteScreen({ token }: { token: string }) {
  return (
    <AuthLayout>
      <LoginFlow inviteToken={token} />
    </AuthLayout>
  );
}
