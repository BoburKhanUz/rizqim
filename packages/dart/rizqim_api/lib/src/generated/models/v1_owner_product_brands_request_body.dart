// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_product_brands_request_body.g.dart';

@JsonSerializable()
class V1OwnerProductBrandsRequestBody {
  const V1OwnerProductBrandsRequestBody({
    required this.brandId,
    required this.productIds,
  });

  factory V1OwnerProductBrandsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerProductBrandsRequestBodyFromJson(json);

  @JsonKey(name: 'brand_id')
  final int? brandId;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;

  Map<String, Object?> toJson() =>
      _$V1OwnerProductBrandsRequestBodyToJson(this);
}
