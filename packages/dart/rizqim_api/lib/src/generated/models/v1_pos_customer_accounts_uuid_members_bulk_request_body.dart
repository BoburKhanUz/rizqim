// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_customer_accounts_uuid_members_bulk_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidMembersBulkRequestBody {
  const V1PosCustomerAccountsUuidMembersBulkRequestBody({
    required this.lines,
    this.dailyLimit,
    this.mealsPerDay,
  });

  factory V1PosCustomerAccountsUuidMembersBulkRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidMembersBulkRequestBodyFromJson(json);

  final String lines;
  @JsonKey(name: 'daily_limit')
  final num? dailyLimit;
  @JsonKey(name: 'meals_per_day')
  final int? mealsPerDay;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidMembersBulkRequestBodyToJson(this);
}
