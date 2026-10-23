// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsRequestBody {
  const V1PosCustomerAccountsRequestBody({
    required this.name,
    this.phone,
    this.groupName,
    this.creditLimit,
    this.isBlocked,
    this.note,
    this.branchId,
    this.approvalToken,
  });

  factory V1PosCustomerAccountsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsRequestBodyFromJson(json);

  final String name;
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
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsRequestBodyToJson(this);
}
