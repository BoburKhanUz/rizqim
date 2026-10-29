// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data55.dart';

part 'patch_v1_owner_delivery_zones_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerDeliveryZonesIdResponse {
  const PatchV1OwnerDeliveryZonesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerDeliveryZonesIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerDeliveryZonesIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data55 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerDeliveryZonesIdResponseToJson(this);
}
