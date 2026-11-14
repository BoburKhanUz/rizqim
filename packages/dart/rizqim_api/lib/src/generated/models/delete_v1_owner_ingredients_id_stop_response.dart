// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data108.dart';

part 'delete_v1_owner_ingredients_id_stop_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerIngredientsIdStopResponse {
  const DeleteV1OwnerIngredientsIdStopResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerIngredientsIdStopResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerIngredientsIdStopResponseFromJson(json);

  final bool success;
  final String message;
  final Data108 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerIngredientsIdStopResponseToJson(this);
}
