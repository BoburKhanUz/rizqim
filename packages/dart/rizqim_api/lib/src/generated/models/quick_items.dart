// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'source2.dart';

part 'quick_items.g.dart';

@JsonSerializable()
class QuickItems {
  const QuickItems({
    required this.productId,
    required this.variantId,
    required this.variantUuid,
    required this.name,
    required this.variantName,
    required this.source,
    required this.hallId,
    required this.quantityPerGuest,
    required this.sortOrder,
  });

  factory QuickItems.fromJson(Map<String, Object?> json) =>
      _$QuickItemsFromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'variant_id')
  final int variantId;
  @JsonKey(name: 'variant_uuid')
  final String variantUuid;
  final String name;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final Source2 source;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'quantity_per_guest')
  final String? quantityPerGuest;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$QuickItemsToJson(this);
}
