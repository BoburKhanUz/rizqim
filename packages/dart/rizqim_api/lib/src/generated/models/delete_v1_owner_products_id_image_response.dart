// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'product.dart';

part 'delete_v1_owner_products_id_image_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerProductsIdImageResponse {
  const DeleteV1OwnerProductsIdImageResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerProductsIdImageResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerProductsIdImageResponseFromJson(json);

  final bool success;
  final String message;
  final Product data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerProductsIdImageResponseToJson(this);
}
