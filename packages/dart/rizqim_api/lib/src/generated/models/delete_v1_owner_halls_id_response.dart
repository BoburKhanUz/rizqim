// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data86.dart';

part 'delete_v1_owner_halls_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerHallsIdResponse {
  const DeleteV1OwnerHallsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerHallsIdResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1OwnerHallsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data86 data;

  Map<String, Object?> toJson() => _$DeleteV1OwnerHallsIdResponseToJson(this);
}
