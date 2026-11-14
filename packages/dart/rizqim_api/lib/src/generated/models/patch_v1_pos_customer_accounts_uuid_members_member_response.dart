// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data286.dart';

part 'patch_v1_pos_customer_accounts_uuid_members_member_response.g.dart';

@JsonSerializable()
class PatchV1PosCustomerAccountsUuidMembersMemberResponse {
  const PatchV1PosCustomerAccountsUuidMembersMemberResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1PosCustomerAccountsUuidMembersMemberResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1PosCustomerAccountsUuidMembersMemberResponseFromJson(json);

  final bool success;
  final String message;
  final Data286 data;

  Map<String, Object?> toJson() =>
      _$PatchV1PosCustomerAccountsUuidMembersMemberResponseToJson(this);
}
