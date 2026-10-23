// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'product.dart';

part 'patch_v1_owner_products_id_fiscal_response.g.dart';

@JsonSerializable()
class PatchV1OwnerProductsIdFiscalResponse {
  const PatchV1OwnerProductsIdFiscalResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerProductsIdFiscalResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerProductsIdFiscalResponseFromJson(json);

  final bool success;
  final String message;
  final Product data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerProductsIdFiscalResponseToJson(this);
}
