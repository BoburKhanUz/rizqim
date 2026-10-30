// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_pickup_time_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidPickupTimeRequestBody {
  const V1PosOrdersUuidPickupTimeRequestBody({
    required this.version,
    required this.pickupAt,
  });

  factory V1PosOrdersUuidPickupTimeRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidPickupTimeRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'pickup_at')
  final DateTime? pickupAt;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidPickupTimeRequestBodyToJson(this);
}
