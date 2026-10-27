// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data120.dart';

part 'get_v1_owner_room_tariffs_response.g.dart';

@JsonSerializable()
class GetV1OwnerRoomTariffsResponse {
  const GetV1OwnerRoomTariffsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerRoomTariffsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerRoomTariffsResponseFromJson(json);

  final bool success;
  final String message;
  final Data120 data;

  Map<String, Object?> toJson() => _$GetV1OwnerRoomTariffsResponseToJson(this);
}
