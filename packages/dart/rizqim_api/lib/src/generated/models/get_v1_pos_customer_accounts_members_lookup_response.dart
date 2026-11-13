// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data227.dart';

part 'get_v1_pos_customer_accounts_members_lookup_response.g.dart';

@JsonSerializable()
class GetV1PosCustomerAccountsMembersLookupResponse {
  const GetV1PosCustomerAccountsMembersLookupResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosCustomerAccountsMembersLookupResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosCustomerAccountsMembersLookupResponseFromJson(json);

  final bool success;
  final String message;
  final Data227 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosCustomerAccountsMembersLookupResponseToJson(this);
}
