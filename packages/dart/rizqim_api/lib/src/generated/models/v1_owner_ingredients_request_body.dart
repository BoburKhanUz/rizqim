// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_ingredients_request_body.g.dart';

@JsonSerializable()
class V1OwnerIngredientsRequestBody {
  const V1OwnerIngredientsRequestBody({required this.name, this.unit});

  factory V1OwnerIngredientsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerIngredientsRequestBodyFromJson(json);

  final String name;
  final String? unit;

  Map<String, Object?> toJson() => _$V1OwnerIngredientsRequestBodyToJson(this);
}
