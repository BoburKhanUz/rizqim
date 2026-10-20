// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_rules2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProductRules2 _$ProductRules2FromJson(Map<String, dynamic> json) =>
    ProductRules2(
      minSelect: json['min_select'] as String,
      maxSelect: json['max_select'] as String,
      isRequired: json['is_required'] as String,
      sortOrder: (json['sort_order'] as num).toInt(),
    );

Map<String, dynamic> _$ProductRules2ToJson(ProductRules2 instance) =>
    <String, dynamic>{
      'min_select': instance.minSelect,
      'max_select': instance.maxSelect,
      'is_required': instance.isRequired,
      'sort_order': instance.sortOrder,
    };
