// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_audit_logs_response.dart';
import '../models/subject_type.dart';

part 'owner_audit_log_api.g.dart';

@RestApi()
abstract class OwnerAuditLogApi {
  factory OwnerAuditLogApi(Dio dio, {String? baseUrl}) = _OwnerAuditLogApi;

  /// Audit log.
  ///
  /// The store's entries, newest first, Laravel pages (`per_page` 1–100, default 50). Filters: `action` (exact, or a.
  /// prefix such as `order.*`), `user_id`, `device_uuid`, `branch_id`, `subject_type` + `subject_id`, `from` / `to`.
  /// (dates). PINs, tokens, codes and card data never reach the log and phone numbers are masked (AuditRedactor);.
  /// `ip` is shown to the owner only. An action of the RIZQIM team on the store (Boshqaruv markazi) keeps its action,.
  /// subject and before/after, but its actor is `{id: null, name: "RIZQIM jamoasi", role: "platform"}`, without the.
  /// member's IP or the team's internal reason and notes; `user_id` never finds a team member's entries.
  @GET('/v1/owner/audit-logs')
  Future<GetV1OwnerAuditLogsResponse> auditLogIndex({
    @Query('action') String? action,
    @Query('user_id') int? userId,
    @Query('device_uuid') String? deviceUuid,
    @Query('branch_id') int? branchId,
    @Query('subject_type') SubjectType? subjectType,
    @Query('subject_id') int? subjectId,
    @Query('from') DateTime? from,
    @Query('to') DateTime? to,
    @Query('per_page') int? perPage,
  });
}
