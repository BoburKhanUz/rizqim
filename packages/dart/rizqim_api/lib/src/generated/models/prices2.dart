// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'prices2.g.dart';

@JsonSerializable()
class Prices2 {
  const Prices2({
    required this.channel,
    required this.productVariantId,
    required this.price,
    required this.isHidden,
  });

  factory Prices2.fromJson(Map<String, Object?> json) =>
      _$Prices2FromJson(json);

  final String channel;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String? price;
  @JsonKey(name: 'is_hidden')
  final bool isHidden;

  Map<String, Object?> toJson() => _$Prices2ToJson(this);
}
