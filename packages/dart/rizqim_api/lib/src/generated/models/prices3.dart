// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel3.dart';

part 'prices3.g.dart';

@JsonSerializable()
class Prices3 {
  const Prices3({
    required this.channel,
    this.productVariantId,
    this.price,
    this.isHidden,
  });

  factory Prices3.fromJson(Map<String, Object?> json) =>
      _$Prices3FromJson(json);

  final Channel3 channel;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final num? price;
  @JsonKey(name: 'is_hidden')
  final bool? isHidden;

  Map<String, Object?> toJson() => _$Prices3ToJson(this);
}
