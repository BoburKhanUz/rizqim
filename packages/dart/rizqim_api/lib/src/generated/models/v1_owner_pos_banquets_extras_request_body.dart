// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'banquet_extra_pricing.dart';

part 'v1_owner_pos_banquets_extras_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsExtrasRequestBody {
  const V1OwnerPosBanquetsExtrasRequestBody({
    required this.name,
    required this.price,
    required this.pricing,
    this.isActive,
    this.sortOrder,
  });

  factory V1OwnerPosBanquetsExtrasRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsExtrasRequestBodyFromJson(json);

  final String name;
  final num price;
  final BanquetExtraPricing pricing;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsExtrasRequestBodyToJson(this);
}
