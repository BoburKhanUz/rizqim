// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'brand.dart';
import 'order_channel.dart';

part 'brand_channel.g.dart';

@JsonSerializable()
class BrandChannel {
  const BrandChannel({
    required this.id,
    required this.storeId,
    required this.brandId,
    required this.channel,
    required this.isHidden,
    required this.pricePercent,
    required this.createdAt,
    required this.updatedAt,
    this.brand,
  });

  factory BrandChannel.fromJson(Map<String, Object?> json) =>
      _$BrandChannelFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'brand_id')
  final int brandId;
  final OrderChannel channel;
  @JsonKey(name: 'is_hidden')
  final bool isHidden;
  @JsonKey(name: 'price_percent')
  final String? pricePercent;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Brand? brand;

  Map<String, Object?> toJson() => _$BrandChannelToJson(this);
}
