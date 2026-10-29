// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data97.dart';

part 'get_v1_owner_meal_sets_response.g.dart';

@JsonSerializable()
class GetV1OwnerMealSetsResponse {
  const GetV1OwnerMealSetsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerMealSetsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerMealSetsResponseFromJson(json);

  final bool success;
  final String message;
  final Data97 data;

  Map<String, Object?> toJson() => _$GetV1OwnerMealSetsResponseToJson(this);
}
