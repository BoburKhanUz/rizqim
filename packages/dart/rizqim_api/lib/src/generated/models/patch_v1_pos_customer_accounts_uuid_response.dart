// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data142.dart';

part 'patch_v1_pos_customer_accounts_uuid_response.g.dart';

@JsonSerializable()
class PatchV1PosCustomerAccountsUuidResponse {
  const PatchV1PosCustomerAccountsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1PosCustomerAccountsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1PosCustomerAccountsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data142 data;

  Map<String, Object?> toJson() =>
      _$PatchV1PosCustomerAccountsUuidResponseToJson(this);
}
