// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers.dart';

part 'options.g.dart';

@JsonSerializable()
class Options {
  const Options({
    required this.id,
    required this.uuid,
    required this.variantId,
    required this.variantUuid,
    required this.productId,
    required this.name,
    required this.variantName,
    required this.quantity,
    required this.priceDelta,
    required this.modifiers,
    required this.isDefault,
    required this.label,
    required this.receiptLabel,
  });

  factory Options.fromJson(Map<String, Object?> json) =>
      _$OptionsFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'variant_id')
  final int variantId;
  @JsonKey(name: 'variant_uuid')
  final String variantUuid;
  @JsonKey(name: 'product_id')
  final int? productId;
  final String name;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final int quantity;
  @JsonKey(name: 'price_delta')
  final String priceDelta;
  final List<Modifiers> modifiers;
  @JsonKey(name: 'is_default')
  final bool isDefault;
  final String? label;
  @JsonKey(name: 'receipt_label')
  final String receiptLabel;

  Map<String, Object?> toJson() => _$OptionsToJson(this);
}
