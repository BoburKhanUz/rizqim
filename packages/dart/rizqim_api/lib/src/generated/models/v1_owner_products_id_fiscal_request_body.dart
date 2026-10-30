// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind3.dart';
import 'variants2.dart';

part 'v1_owner_products_id_fiscal_request_body.g.dart';

@JsonSerializable()
class V1OwnerProductsIdFiscalRequestBody {
  const V1OwnerProductsIdFiscalRequestBody({
    this.kind,
    this.mxikCode,
    this.packageCode,
    this.vatRate,
    this.variants,
  });

  factory V1OwnerProductsIdFiscalRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerProductsIdFiscalRequestBodyFromJson(json);

  final Kind3? kind;
  @JsonKey(name: 'mxik_code')
  final String? mxikCode;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  @JsonKey(name: 'vat_rate')
  final num? vatRate;
  final List<Variants2>? variants;

  Map<String, Object?> toJson() =>
      _$V1OwnerProductsIdFiscalRequestBodyToJson(this);
}
