// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data67.dart';

part 'get_v1_owner_finance_response.g.dart';

@JsonSerializable()
class GetV1OwnerFinanceResponse {
  const GetV1OwnerFinanceResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFinanceResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerFinanceResponseFromJson(json);

  final bool success;
  final String message;
  final Data67 data;

  Map<String, Object?> toJson() => _$GetV1OwnerFinanceResponseToJson(this);
}
