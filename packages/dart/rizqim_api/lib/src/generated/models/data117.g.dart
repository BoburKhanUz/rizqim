// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data117.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data117 _$Data117FromJson(Map<String, dynamic> json) => Data117(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  minSelect: (json['min_select'] as num).toInt(),
  maxSelect: (json['max_select'] as num?)?.toInt(),
  isRequired: json['is_required'] as bool,
  displayMode: json['display_mode'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => Modifiers6.fromJson(e as Map<String, dynamic>))
      .toList(),
  productIds: (json['product_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  productRules: ProductRules3.fromJson(
    json['product_rules'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Data117ToJson(Data117 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'name_i18n': instance.nameI18n,
  'min_select': instance.minSelect,
  'max_select': instance.maxSelect,
  'is_required': instance.isRequired,
  'display_mode': instance.displayMode,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'modifiers': instance.modifiers,
  'product_ids': instance.productIds,
  'product_rules': instance.productRules,
};
