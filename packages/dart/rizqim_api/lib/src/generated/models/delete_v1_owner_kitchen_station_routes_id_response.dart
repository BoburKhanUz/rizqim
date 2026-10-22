// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_kitchen_station_routes_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerKitchenStationRoutesIdResponse {
  const DeleteV1OwnerKitchenStationRoutesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerKitchenStationRoutesIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerKitchenStationRoutesIdResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerKitchenStationRoutesIdResponseToJson(this);
}
