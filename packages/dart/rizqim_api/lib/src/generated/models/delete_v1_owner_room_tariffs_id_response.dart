// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data135.dart';

part 'delete_v1_owner_room_tariffs_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerRoomTariffsIdResponse {
  const DeleteV1OwnerRoomTariffsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerRoomTariffsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerRoomTariffsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data135 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerRoomTariffsIdResponseToJson(this);
}
