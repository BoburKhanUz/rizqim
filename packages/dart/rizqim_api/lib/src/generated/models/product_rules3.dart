// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'product_rules3.g.dart';

@JsonSerializable()
class ProductRules3 {
  const ProductRules3({
    required this.minSelect,
    required this.maxSelect,
    required this.isRequired,
    required this.sortOrder,
  });

  factory ProductRules3.fromJson(Map<String, Object?> json) =>
      _$ProductRules3FromJson(json);

  @JsonKey(name: 'min_select')
  final int? minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool? isRequired;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$ProductRules3ToJson(this);
}
