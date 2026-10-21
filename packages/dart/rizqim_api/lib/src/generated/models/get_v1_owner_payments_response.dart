// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data85.dart';

part 'get_v1_owner_payments_response.g.dart';

@JsonSerializable()
class GetV1OwnerPaymentsResponse {
  const GetV1OwnerPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPaymentsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data85 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPaymentsResponseToJson(this);
}
