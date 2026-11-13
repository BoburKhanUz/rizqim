// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_uuid_members_member_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidMembersMemberRequestBody {
  const V1PosCustomerAccountsUuidMembersMemberRequestBody({
    this.name,
    this.phone,
    this.code,
    this.dailyLimit,
    this.mealsPerDay,
    this.isActive,
    this.note,
    this.regenerateCode,
  });

  factory V1PosCustomerAccountsUuidMembersMemberRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidMembersMemberRequestBodyFromJson(json);

  final String? name;
  final String? phone;
  final String? code;
  @JsonKey(name: 'daily_limit')
  final num? dailyLimit;
  @JsonKey(name: 'meals_per_day')
  final int? mealsPerDay;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  final String? note;
  @JsonKey(name: 'regenerate_code')
  final bool? regenerateCode;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidMembersMemberRequestBodyToJson(this);
}
