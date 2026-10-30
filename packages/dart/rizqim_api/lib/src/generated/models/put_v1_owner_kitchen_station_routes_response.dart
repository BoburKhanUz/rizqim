// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data108.dart';

part 'put_v1_owner_kitchen_station_routes_response.g.dart';

@JsonSerializable()
class PutV1OwnerKitchenStationRoutesResponse {
  const PutV1OwnerKitchenStationRoutesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerKitchenStationRoutesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerKitchenStationRoutesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data108> data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerKitchenStationRoutesResponseToJson(this);
}
