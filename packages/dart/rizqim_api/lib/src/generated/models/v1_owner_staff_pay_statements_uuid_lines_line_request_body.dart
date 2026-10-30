// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_statements_uuid_lines_line_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayStatementsUuidLinesLineRequestBody {
  const V1OwnerStaffPayStatementsUuidLinesLineRequestBody({
    required this.adjustment,
    this.reason,
  });

  factory V1OwnerStaffPayStatementsUuidLinesLineRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayStatementsUuidLinesLineRequestBodyFromJson(json);

  final String adjustment;
  final String? reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayStatementsUuidLinesLineRequestBodyToJson(this);
}
