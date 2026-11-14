// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_owner_brands_response.g.dart';

@JsonSerializable()
class PostV1OwnerBrandsResponse {
  const PostV1OwnerBrandsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBrandsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerBrandsResponseFromJson(json);

  final bool success;
  final String message;
  final String data;

  Map<String, Object?> toJson() => _$PostV1OwnerBrandsResponseToJson(this);
}
