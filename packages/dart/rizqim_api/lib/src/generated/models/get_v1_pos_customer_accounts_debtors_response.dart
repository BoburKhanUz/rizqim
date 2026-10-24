// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data142.dart';

part 'get_v1_pos_customer_accounts_debtors_response.g.dart';

@JsonSerializable()
class GetV1PosCustomerAccountsDebtorsResponse {
  const GetV1PosCustomerAccountsDebtorsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosCustomerAccountsDebtorsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosCustomerAccountsDebtorsResponseFromJson(json);

  final bool success;
  final String message;
  final Data142 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosCustomerAccountsDebtorsResponseToJson(this);
}
