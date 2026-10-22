// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data173.dart';

part 'get_v1_staff_orders_uuid_response.g.dart';

@JsonSerializable()
class GetV1StaffOrdersUuidResponse {
  const GetV1StaffOrdersUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1StaffOrdersUuidResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1StaffOrdersUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data173 data;

  Map<String, Object?> toJson() => _$GetV1StaffOrdersUuidResponseToJson(this);
}
