// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data69.dart';

part 'get_v1_owner_delivery_zones_id_response.g.dart';

@JsonSerializable()
class GetV1OwnerDeliveryZonesIdResponse {
  const GetV1OwnerDeliveryZonesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerDeliveryZonesIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerDeliveryZonesIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data69 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerDeliveryZonesIdResponseToJson(this);
}
