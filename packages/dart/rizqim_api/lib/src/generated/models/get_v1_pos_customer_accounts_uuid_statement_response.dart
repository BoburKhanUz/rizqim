// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data147.dart';

part 'get_v1_pos_customer_accounts_uuid_statement_response.g.dart';

@JsonSerializable()
class GetV1PosCustomerAccountsUuidStatementResponse {
  const GetV1PosCustomerAccountsUuidStatementResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosCustomerAccountsUuidStatementResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosCustomerAccountsUuidStatementResponseFromJson(json);

  final bool success;
  final String message;
  final Data147 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosCustomerAccountsUuidStatementResponseToJson(this);
}
