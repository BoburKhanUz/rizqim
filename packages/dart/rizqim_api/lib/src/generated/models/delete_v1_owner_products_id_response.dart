// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_products_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerProductsIdResponse {
  const DeleteV1OwnerProductsIdResponse({
    required this.success,
    required this.message,
  });

  factory DeleteV1OwnerProductsIdResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1OwnerProductsIdResponseFromJson(json);

  final bool success;
  final String message;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerProductsIdResponseToJson(this);
}
