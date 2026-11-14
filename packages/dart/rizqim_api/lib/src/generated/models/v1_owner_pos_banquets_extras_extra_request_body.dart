// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'banquet_extra_pricing.dart';

part 'v1_owner_pos_banquets_extras_extra_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsExtrasExtraRequestBody {
  const V1OwnerPosBanquetsExtrasExtraRequestBody({
    this.name,
    this.price,
    this.pricing,
    this.isActive,
    this.sortOrder,
  });

  factory V1OwnerPosBanquetsExtrasExtraRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsExtrasExtraRequestBodyFromJson(json);

  final String? name;
  final num? price;
  final BanquetExtraPricing? pricing;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsExtrasExtraRequestBodyToJson(this);
}
