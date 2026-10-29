// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_statements_uuid_reopen_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayStatementsUuidReopenRequestBody {
  const V1OwnerStaffPayStatementsUuidReopenRequestBody({required this.reason});

  factory V1OwnerStaffPayStatementsUuidReopenRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayStatementsUuidReopenRequestBodyFromJson(json);

  final String reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayStatementsUuidReopenRequestBodyToJson(this);
}
