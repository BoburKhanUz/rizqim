// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data282.dart';

part 'get_v1_pos_customer_accounts_uuid_corporate_statement_response.g.dart';

@JsonSerializable()
class GetV1PosCustomerAccountsUuidCorporateStatementResponse {
  const GetV1PosCustomerAccountsUuidCorporateStatementResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosCustomerAccountsUuidCorporateStatementResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosCustomerAccountsUuidCorporateStatementResponseFromJson(json);

  final bool success;
  final String message;
  final Data282 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosCustomerAccountsUuidCorporateStatementResponseToJson(this);
}
