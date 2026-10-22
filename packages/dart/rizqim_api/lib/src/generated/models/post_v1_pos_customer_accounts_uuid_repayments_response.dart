// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data143.dart';

part 'post_v1_pos_customer_accounts_uuid_repayments_response.g.dart';

@JsonSerializable()
class PostV1PosCustomerAccountsUuidRepaymentsResponse {
  const PostV1PosCustomerAccountsUuidRepaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosCustomerAccountsUuidRepaymentsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosCustomerAccountsUuidRepaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data143 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosCustomerAccountsUuidRepaymentsResponseToJson(this);
}
