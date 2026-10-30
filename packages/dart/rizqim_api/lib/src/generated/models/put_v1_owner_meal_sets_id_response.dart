// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data99.dart';

part 'put_v1_owner_meal_sets_id_response.g.dart';

@JsonSerializable()
class PutV1OwnerMealSetsIdResponse {
  const PutV1OwnerMealSetsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerMealSetsIdResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerMealSetsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data99 data;

  Map<String, Object?> toJson() => _$PutV1OwnerMealSetsIdResponseToJson(this);
}
