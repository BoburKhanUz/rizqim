// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_tips_uuid_corrections_request_body.g.dart';

@JsonSerializable()
class V1PosTipsUuidCorrectionsRequestBody {
  const V1PosTipsUuidCorrectionsRequestBody({
    required this.mode,
    required this.reason,
    this.amount,
    this.staffId,
    this.approvalToken,
  });

  factory V1PosTipsUuidCorrectionsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosTipsUuidCorrectionsRequestBodyFromJson(json);

  /// The tip's amount now (so'm; null: unchanged).
  final String? amount;

  /// personal or pool.
  final String mode;

  /// Whom a personal tip goes to (null: unassigned).
  @JsonKey(name: 'staff_id')
  final int? staffId;

  /// Why (required).
  final String reason;

  /// A manager's approval for tips.manage.
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosTipsUuidCorrectionsRequestBodyToJson(this);
}
