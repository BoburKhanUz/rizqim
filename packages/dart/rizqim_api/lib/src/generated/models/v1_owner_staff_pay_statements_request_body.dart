// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_statements_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPayStatementsRequestBody {
  const V1OwnerStaffPayStatementsRequestBody({this.branchId, this.date});

  factory V1OwnerStaffPayStatementsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPayStatementsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// YYYY-MM-DD: a business date in the period.
  final String? date;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPayStatementsRequestBodyToJson(this);
}
