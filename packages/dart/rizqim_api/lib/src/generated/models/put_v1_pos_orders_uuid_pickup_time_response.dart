// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data244.dart';

part 'put_v1_pos_orders_uuid_pickup_time_response.g.dart';

@JsonSerializable()
class PutV1PosOrdersUuidPickupTimeResponse {
  const PutV1PosOrdersUuidPickupTimeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1PosOrdersUuidPickupTimeResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1PosOrdersUuidPickupTimeResponseFromJson(json);

  final bool success;
  final String message;
  final Data244 data;

  Map<String, Object?> toJson() =>
      _$PutV1PosOrdersUuidPickupTimeResponseToJson(this);
}
