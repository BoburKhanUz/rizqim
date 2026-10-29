// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_statements_uuid_lines_line_pay_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody {
  const V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody({
    required this.method,
    this.shiftUuid,
    this.note,
  });

  factory V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayStatementsUuidLinesLinePayRequestBodyFromJson(json);

  /// cash_drawer | other
  final String method;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayStatementsUuidLinesLinePayRequestBodyToJson(this);
}
