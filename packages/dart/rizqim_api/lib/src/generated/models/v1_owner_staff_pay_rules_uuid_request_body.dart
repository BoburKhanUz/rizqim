// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_rules_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayRulesUuidRequestBody {
  const V1OwnerStaffPayRulesUuidRequestBody({
    this.effectiveFrom,
    this.components,
    this.note,
  });

  factory V1OwnerStaffPayRulesUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayRulesUuidRequestBodyFromJson(json);

  /// YYYY-MM-DD
  @JsonKey(name: 'effective_from')
  final String? effectiveFrom;
  final List<Map<String, dynamic>>? components;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayRulesUuidRequestBodyToJson(this);
}
