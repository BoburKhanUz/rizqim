// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data59.dart';

part 'get_v1_owner_payouts_response.g.dart';

@JsonSerializable()
class GetV1OwnerPayoutsResponse {
  const GetV1OwnerPayoutsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPayoutsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPayoutsResponseFromJson(json);

  final bool success;
  final String message;
  final Data59 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPayoutsResponseToJson(this);
}
