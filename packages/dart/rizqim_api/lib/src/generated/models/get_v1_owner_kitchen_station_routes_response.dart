// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data90.dart';

part 'get_v1_owner_kitchen_station_routes_response.g.dart';

@JsonSerializable()
class GetV1OwnerKitchenStationRoutesResponse {
  const GetV1OwnerKitchenStationRoutesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerKitchenStationRoutesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerKitchenStationRoutesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data90> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerKitchenStationRoutesResponseToJson(this);
}
