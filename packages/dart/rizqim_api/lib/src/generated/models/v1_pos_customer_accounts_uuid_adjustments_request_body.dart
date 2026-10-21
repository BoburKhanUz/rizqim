// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_uuid_adjustments_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidAdjustmentsRequestBody {
  const V1PosCustomerAccountsUuidAdjustmentsRequestBody({
    required this.amount,
    required this.note,
  });

  factory V1PosCustomerAccountsUuidAdjustmentsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidAdjustmentsRequestBodyFromJson(json);

  final num amount;
  final String note;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidAdjustmentsRequestBodyToJson(this);
}
