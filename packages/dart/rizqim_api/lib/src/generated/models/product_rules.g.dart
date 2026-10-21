// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_rules.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProductRules _$ProductRulesFromJson(Map<String, dynamic> json) => ProductRules(
  minSelect: (json['min_select'] as num?)?.toInt(),
  maxSelect: (json['max_select'] as num?)?.toInt(),
  isRequired: json['is_required'] as bool?,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$ProductRulesToJson(ProductRules instance) =>
    <String, dynamic>{
      'min_select': instance.minSelect,
      'max_select': instance.maxSelect,
      'is_required': instance.isRequired,
      'sort_order': instance.sortOrder,
    };
