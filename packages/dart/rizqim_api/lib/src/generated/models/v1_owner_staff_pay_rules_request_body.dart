// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_rules_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayRulesRequestBody {
  const V1OwnerStaffPayRulesRequestBody({
    required this.effectiveFrom,
    required this.components,
    this.branchId,
    this.userId,
    this.role,
    this.note,
  });

  factory V1OwnerStaffPayRulesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStaffPayRulesRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'user_id')
  final int? userId;
  final String? role;

  /// YYYY-MM-DD
  @JsonKey(name: 'effective_from')
  final String effectiveFrom;
  final List<Map<String, dynamic>> components;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayRulesRequestBodyToJson(this);
}
