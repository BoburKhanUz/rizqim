// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data164.dart';

part 'get_v1_owner_staff_pay_statements_response.g.dart';

@JsonSerializable()
class GetV1OwnerStaffPayStatementsResponse {
  const GetV1OwnerStaffPayStatementsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerStaffPayStatementsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerStaffPayStatementsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data164> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerStaffPayStatementsResponseToJson(this);
}
