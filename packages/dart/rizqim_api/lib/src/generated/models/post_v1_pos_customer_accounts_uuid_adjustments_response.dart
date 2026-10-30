// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data197.dart';

part 'post_v1_pos_customer_accounts_uuid_adjustments_response.g.dart';

@JsonSerializable()
class PostV1PosCustomerAccountsUuidAdjustmentsResponse {
  const PostV1PosCustomerAccountsUuidAdjustmentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosCustomerAccountsUuidAdjustmentsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosCustomerAccountsUuidAdjustmentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data197 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosCustomerAccountsUuidAdjustmentsResponseToJson(this);
}
