// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_uuid_members_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidMembersRequestBody {
  const V1PosCustomerAccountsUuidMembersRequestBody({
    required this.name,
    this.phone,
    this.code,
    this.dailyLimit,
    this.mealsPerDay,
    this.note,
  });

  factory V1PosCustomerAccountsUuidMembersRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidMembersRequestBodyFromJson(json);

  final String name;
  final String? phone;
  final String? code;
  @JsonKey(name: 'daily_limit')
  final num? dailyLimit;
  @JsonKey(name: 'meals_per_day')
  final int? mealsPerDay;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidMembersRequestBodyToJson(this);
}
