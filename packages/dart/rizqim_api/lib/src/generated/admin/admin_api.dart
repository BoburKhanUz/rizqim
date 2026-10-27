// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_admin_auth_sessions_id_response.dart';
import '../models/get_v1_admin_audit_logs_response.dart';
import '../models/get_v1_admin_auth_me_response.dart';
import '../models/get_v1_admin_auth_sessions_response.dart';
import '../models/get_v1_admin_me_response.dart';
import '../models/get_v1_admin_roles_response.dart';
import '../models/get_v1_admin_search_response.dart';
import '../models/get_v1_admin_team_id_response.dart';
import '../models/get_v1_admin_team_response.dart';
import '../models/patch_v1_admin_team_id_response.dart';
import '../models/post_v1_admin_auth2fa_confirm_response.dart';
import '../models/post_v1_admin_auth2fa_recovery_codes_response.dart';
import '../models/post_v1_admin_auth2fa_setup_response.dart';
import '../models/post_v1_admin_auth2fa_verify_response.dart';
import '../models/post_v1_admin_auth_confirm_response.dart';
import '../models/post_v1_admin_auth_invite_accept_response.dart';
import '../models/post_v1_admin_auth_login_response.dart';
import '../models/post_v1_admin_auth_logout_response.dart';
import '../models/post_v1_admin_team_id_invite_response.dart';
import '../models/post_v1_admin_team_id_reset2fa_response.dart';
import '../models/post_v1_admin_team_response.dart';
import '../models/put_v1_admin_roles_response.dart';
import '../models/v1_admin_auth2fa_confirm_request_body.dart';
import '../models/v1_admin_auth2fa_setup_request_body.dart';
import '../models/v1_admin_auth2fa_verify_request_body.dart';
import '../models/v1_admin_auth_confirm_request_body.dart';
import '../models/v1_admin_auth_invite_accept_request_body.dart';
import '../models/v1_admin_auth_login_request_body.dart';
import '../models/v1_admin_roles_request_body.dart';
import '../models/v1_admin_team_id_request_body.dart';
import '../models/v1_admin_team_request_body.dart';

part 'admin_api.g.dart';

@RestApi()
abstract class AdminApi {
  factory AdminApi(Dio dio, {String? baseUrl}) = _AdminApi;

  /// Audit log (whole platform).
  ///
  /// Newest first, cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 50). Filters: `actor_id`,.
  /// `action` (a prefix: `admin.team` or `admin.team.*`; an exact action matches itself), `store_id`,.
  /// `subject_type` (Order, User, Store...) + `subject_id`, `from` / `to` (dates, inclusive), `q` (text in the action.
  /// and its details, at least 2 characters).
  @GET('/v1/admin/audit-logs')
  Future<GetV1AdminAuditLogsResponse> adminAuditLogs({
    @Query('actor_id') int? actorId,
    @Query('action') String? action,
    @Query('store_id') int? storeId,
    @Query('subject_type') String? subjectType,
    @Query('subject_id') int? subjectId,
    @Query('from') DateTime? from,
    @Query('to') DateTime? to,
    @Query('q') String? q,
    @Query('per_page') int? perPage,
    @Query('cursor') String? cursor,
  });

  /// Confirm the 2FA set-up.
  ///
  /// The first code from the authenticator app turns 2FA on and opens the session. `recovery_codes` (10) are shown.
  /// only here, once: each one signs in once when the phone is lost; only their hashes are kept.
  @POST('/v1/admin/auth/2fa/confirm')
  Future<PostV1AdminAuth2faConfirmResponse> adminAuth2faConfirm({
    @Body() required V1AdminAuth2faConfirmRequestBody body,
  });

  /// New recovery codes.
  ///
  /// Replaces every recovery code with 10 new ones, shown only in this answer. Needs a step-up confirmation.
  @POST('/v1/admin/auth/2fa/recovery-codes')
  Future<PostV1AdminAuth2faRecoveryCodesResponse>
  adminAuthRecoveryCodesRegenerated();

  /// Start the 2FA set-up.
  ///
  /// Only for a `setup_required` challenge. Returns the new secret (for manual entry), the `otpauth://` URI and the.
  /// QR code as inline SVG. The same challenge gives the same secret again; it is saved only when `2fa/confirm`.
  /// accepts a code from it, and never shown again afterwards. 409 `two_factor_already_enabled` once 2FA is on.
  @POST('/v1/admin/auth/2fa/setup')
  Future<PostV1AdminAuth2faSetupResponse> adminAuth2faSetup({
    @Body() required V1AdminAuth2faSetupRequestBody body,
  });

  /// Second step: the 2FA code.
  ///
  /// Send `code` (6 digits from the app) or `recovery_code` (one of the saved recovery codes: it is burnt). A code.
  /// already used, or one older than the last used, is refused (replay). Opens the session: `token` (Bearer, the.
  /// admin API only), absolute `expires_at` (12 hours); 60 minutes without a request also end it.
  @POST('/v1/admin/auth/2fa/verify')
  Future<PostV1AdminAuth2faVerifyResponse> adminAuth2faVerify({
    @Body() required V1AdminAuth2faVerifyRequestBody body,
  });

  /// Step-up: confirm with a fresh code.
  ///
  /// A current TOTP code marks this session confirmed for 10 minutes. Actions behind it (team and role changes,.
  /// 2FA resets, new recovery codes, revoking another session; later money and order interventions) answer 403.
  /// `reauth_required` without it: ask for the code, call this, retry once.
  @POST('/v1/admin/auth/confirm')
  Future<PostV1AdminAuthConfirmResponse> adminAuthReauthenticated({
    @Body() required V1AdminAuthConfirmRequestBody body,
  });

  /// Accept an invite.
  ///
  /// The invite link's token and the new password (at least 12 characters, not the email). Answers with a.
  /// `setup_required` challenge: the member goes straight on to the 2FA set-up. 422 `invite_invalid` for an.
  /// unknown, used, re-issued or expired (72 hours) link.
  @POST('/v1/admin/auth/invite/accept')
  Future<PostV1AdminAuthInviteAcceptResponse> adminAuthInviteAccept({
    @Body() required V1AdminAuthInviteAcceptRequestBody body,
  });

  /// Admin sign-in: email and password.
  ///
  /// Returns a challenge for the second step (5 minutes, single use): `otp_required` (send the app's code to.
  /// `2fa/verify`) or `setup_required` (2FA not set up yet: `2fa/setup`, then `2fa/confirm`). An unknown email and a.
  /// wrong password get the same 422 `invalid_credentials`; a disabled member 403 `member_disabled`; too many bad.
  /// tries 429 `locked`.
  @POST('/v1/admin/auth/login')
  Future<PostV1AdminAuthLoginResponse> adminAuthLogin({
    @Body() required V1AdminAuthLoginRequestBody body,
  });

  /// Log out (this session)
  @POST('/v1/admin/auth/logout')
  Future<PostV1AdminAuthLogoutResponse> adminAuthLoggedOut();

  /// The signed-in member.
  ///
  /// Who is signed in, their platform role and effective admin permissions (the admin app shows only the sections.
  /// these allow), 2FA state and the current session (`reauthenticated_until`: the step-up confirmation, if any).
  @GET('/v1/admin/auth/me')
  Future<GetV1AdminAuthMeResponse> adminAuthMe();

  /// My admin sessions.
  ///
  /// Live sessions only (not past their 12 hours, used in the last 60 minutes), newest first; `current` is this one.
  @GET('/v1/admin/auth/sessions')
  Future<GetV1AdminAuthSessionsResponse> adminAuthSessions();

  /// End one of my sessions.
  ///
  /// Ending this session is a log-out; ending another one needs a step-up confirmation (403 `reauth_required`).
  @DELETE('/v1/admin/auth/sessions/{id}')
  Future<DeleteV1AdminAuthSessionsIdResponse> adminAuthSessionRevoked({
    @Path('id') required int id,
  });

  /// The signed-in member (alias of GET /admin/auth/me)
  @GET('/v1/admin/me')
  Future<GetV1AdminMeResponse> adminMe();

  /// Roles and permissions.
  ///
  /// `roles` in display order (`editable`: false for superadmin), `permissions` with their section (`group`),.
  /// `grants`: role => the permissions it holds now, and `defaults`: role => the built-in set before the.
  /// superadmin's changes (a cell differing from it is "O'zgartirilgan"; PUT with `allowed: null` resets it).
  @GET('/v1/admin/roles')
  Future<GetV1AdminRolesResponse> adminRoles();

  /// Change a role's permission.
  ///
  /// `allowed`: true grants, false takes away, null resets to the role's default. The superadmin role cannot be.
  /// changed (422 `role_not_editable`). Needs step-up. Answers with the whole matrix.
  @PUT('/v1/admin/roles')
  Future<PutV1AdminRolesResponse> adminRolesUpdated({
    @Body() required V1AdminRolesRequestBody body,
  });

  /// Search everything.
  ///
  /// `q` at least 2 characters. Each hit: `type` (store, order, user, member), `id`, `title`, `subtitle` and `href`,.
  /// the admin app path to open (`/stores/{id}`, `/orders/{uuid}`, `/users/{id}`, `/team/{id}`). Types the member.
  /// has no permission for (stores.view, orders.view, users.view, team.manage) are left out.
  @GET('/v1/admin/search')
  Future<GetV1AdminSearchResponse> adminSearch({@Query('q') required String q});

  /// The team.
  ///
  /// Every team member: role, `status` (`invited`: has not accepted the invite yet, `active`, `disabled`), 2FA, last.
  /// sign-in and live sessions.
  @GET('/v1/admin/team')
  Future<GetV1AdminTeamResponse> adminTeam();

  /// Invite a team member.
  ///
  /// Creates the member (they cannot sign in until they accept) and a one-time invite link valid 72 hours:.
  /// `invite_url` is shown only in this answer. Only a superadmin may invite a superadmin. Needs step-up.
  @POST('/v1/admin/team')
  Future<PostV1AdminTeamResponse> adminTeamInvited({
    @Body() required V1AdminTeamRequestBody body,
  });

  /// One team member
  @GET('/v1/admin/team/{id}')
  Future<GetV1AdminTeamIdResponse> adminTeamMember({
    @Path('id') required int id,
  });

  /// Change a member's role or disable / enable them.
  ///
  /// `platform_role` and/or `disabled`. Disabling ends all of the member's sessions. Nobody changes their own role or.
  /// disables themselves (`cannot_change_self`), the last active superadmin stays (`last_superadmin`), and only a.
  /// superadmin changes a superadmin or makes one (`superadmin_only`). Needs step-up.
  @PATCH('/v1/admin/team/{id}')
  Future<PatchV1AdminTeamIdResponse> adminTeamUpdated({
    @Path('id') required int id,
    @Body() V1AdminTeamIdRequestBody? body,
  });

  /// Re-issue the invite.
  ///
  /// A new link for a member who has not accepted yet (409 `invite_already_accepted` otherwise); the older links stop.
  /// working. Needs step-up.
  @POST('/v1/admin/team/{id}/invite')
  Future<PostV1AdminTeamIdInviteResponse> adminTeamInviteReissued({
    @Path('id') required int id,
  });

  /// Reset a member's 2FA.
  ///
  /// For a lost phone: 2FA and the recovery codes are removed and every session of the member ends; they set 2FA up.
  /// again at their next sign-in. Not for yourself. Needs step-up.
  @POST('/v1/admin/team/{id}/reset-2fa')
  Future<PostV1AdminTeamIdReset2faResponse> adminTeam2faReset({
    @Path('id') required int id,
  });
}
