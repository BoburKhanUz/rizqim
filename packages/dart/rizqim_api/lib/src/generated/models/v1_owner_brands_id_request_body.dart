// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_brands_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerBrandsIdRequestBody {
  const V1OwnerBrandsIdRequestBody({
    this.name,
    this.shortCode,
    this.color,
    this.logoUrl,
    this.isActive,
    this.sortOrder,
  });

  factory V1OwnerBrandsIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerBrandsIdRequestBodyFromJson(json);

  final String? name;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String? color;
  @JsonKey(name: 'logo_url')
  final String? logoUrl;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() => _$V1OwnerBrandsIdRequestBodyToJson(this);
}
