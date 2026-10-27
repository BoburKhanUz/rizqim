// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data101.dart';

part 'patch_v1_owner_kitchen_stations_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerKitchenStationsIdResponse {
  const PatchV1OwnerKitchenStationsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerKitchenStationsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerKitchenStationsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data101 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerKitchenStationsIdResponseToJson(this);
}
