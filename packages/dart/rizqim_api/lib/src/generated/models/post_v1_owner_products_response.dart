// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'product.dart';

part 'post_v1_owner_products_response.g.dart';

@JsonSerializable()
class PostV1OwnerProductsResponse {
  const PostV1OwnerProductsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerProductsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerProductsResponseFromJson(json);

  final bool success;
  final String message;
  final Product data;

  Map<String, Object?> toJson() => _$PostV1OwnerProductsResponseToJson(this);
}
