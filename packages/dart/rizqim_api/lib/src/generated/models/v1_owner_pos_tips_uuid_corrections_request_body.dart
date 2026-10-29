// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_tips_uuid_corrections_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosTipsUuidCorrectionsRequestBody {
  const V1OwnerPosTipsUuidCorrectionsRequestBody({
    required this.mode,
    required this.reason,
    this.amount,
    this.staffId,
  });

  factory V1OwnerPosTipsUuidCorrectionsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosTipsUuidCorrectionsRequestBodyFromJson(json);

  /// The tip's amount now (so'm; null: unchanged).
  final String? amount;

  /// personal or pool.
  final String mode;

  /// Whom a personal tip goes to (null: unassigned).
  @JsonKey(name: 'staff_id')
  final int? staffId;

  /// Why (required).
  final String reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosTipsUuidCorrectionsRequestBodyToJson(this);
}
