// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'patch_v1_owner_ingredients_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerIngredientsIdResponse {
  const PatchV1OwnerIngredientsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerIngredientsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerIngredientsIdResponseFromJson(json);

  final bool success;
  final String message;
  final String data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerIngredientsIdResponseToJson(this);
}
