// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data234.dart';

part 'post_v1_pos_customer_accounts_uuid_members_bulk_response.g.dart';

@JsonSerializable()
class PostV1PosCustomerAccountsUuidMembersBulkResponse {
  const PostV1PosCustomerAccountsUuidMembersBulkResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosCustomerAccountsUuidMembersBulkResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosCustomerAccountsUuidMembersBulkResponseFromJson(json);

  final bool success;
  final String message;
  final Data234 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosCustomerAccountsUuidMembersBulkResponseToJson(this);
}
