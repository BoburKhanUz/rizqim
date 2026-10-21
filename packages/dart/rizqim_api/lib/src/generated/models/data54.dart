// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers.dart';
import 'product_rules.dart';

part 'data54.g.dart';

@JsonSerializable()
class Data54 {
  const Data54({
    required this.id,
    required this.uuid,
    required this.name,
    required this.nameI18n,
    required this.minSelect,
    required this.maxSelect,
    required this.isRequired,
    required this.sortOrder,
    required this.isActive,
    required this.modifiers,
    required this.productIds,
    required this.productRules,
  });

  factory Data54.fromJson(Map<String, Object?> json) => _$Data54FromJson(json);

  final int id;
  final String uuid;
  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'min_select')
  final int minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool isRequired;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Modifiers> modifiers;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;
  @JsonKey(name: 'product_rules')
  final ProductRules productRules;

  Map<String, Object?> toJson() => _$Data54ToJson(this);
}
