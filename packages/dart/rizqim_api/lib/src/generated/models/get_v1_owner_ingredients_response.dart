// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data106.dart';

part 'get_v1_owner_ingredients_response.g.dart';

@JsonSerializable()
class GetV1OwnerIngredientsResponse {
  const GetV1OwnerIngredientsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerIngredientsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerIngredientsResponseFromJson(json);

  final bool success;
  final String message;
  final Data106 data;

  Map<String, Object?> toJson() => _$GetV1OwnerIngredientsResponseToJson(this);
}
