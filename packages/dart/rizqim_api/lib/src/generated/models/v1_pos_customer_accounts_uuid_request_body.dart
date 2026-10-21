// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_uuid_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidRequestBody {
  const V1PosCustomerAccountsUuidRequestBody({
    this.name,
    this.phone,
    this.groupName,
    this.creditLimit,
    this.isBlocked,
    this.note,
    this.branchId,
  });

  factory V1PosCustomerAccountsUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidRequestBodyFromJson(json);

  final String? name;
  final String? phone;
  @JsonKey(name: 'group_name')
  final String? groupName;
  @JsonKey(name: 'credit_limit')
  final num? creditLimit;
  @JsonKey(name: 'is_blocked')
  final bool? isBlocked;
  final String? note;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidRequestBodyToJson(this);
}
