// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_categories_request_body.g.dart';

@JsonSerializable()
class V1OwnerCategoriesRequestBody {
  const V1OwnerCategoriesRequestBody({required this.name});

  factory V1OwnerCategoriesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerCategoriesRequestBodyFromJson(json);

  final String name;

  Map<String, Object?> toJson() => _$V1OwnerCategoriesRequestBodyToJson(this);
}
