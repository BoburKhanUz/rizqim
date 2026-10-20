// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifier_group.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ModifierGroup _$ModifierGroupFromJson(Map<String, dynamic> json) =>
    ModifierGroup(
      id: (json['id'] as num).toInt(),
      uuid: json['uuid'] as String,
      storeId: (json['store_id'] as num).toInt(),
      name: json['name'] as String,
      nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String),
      ),
      minSelect: (json['min_select'] as num).toInt(),
      maxSelect: (json['max_select'] as num?)?.toInt(),
      isRequired: json['is_required'] as bool,
      sortOrder: (json['sort_order'] as num).toInt(),
      isActive: json['is_active'] as bool,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      deletedAt: json['deleted_at'] == null
          ? null
          : DateTime.parse(json['deleted_at'] as String),
      store: json['store'] == null
          ? null
          : Store.fromJson(json['store'] as Map<String, dynamic>),
      modifiers: (json['modifiers'] as List<dynamic>?)
          ?.map((e) => Modifier.fromJson(e as Map<String, dynamic>))
          .toList(),
      products: (json['products'] as List<dynamic>?)
          ?.map((e) => Product.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$ModifierGroupToJson(ModifierGroup instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'store_id': instance.storeId,
      'name': instance.name,
      'name_i18n': instance.nameI18n,
      'min_select': instance.minSelect,
      'max_select': instance.maxSelect,
      'is_required': instance.isRequired,
      'sort_order': instance.sortOrder,
      'is_active': instance.isActive,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'deleted_at': instance.deletedAt?.toIso8601String(),
      'store': instance.store,
      'modifiers': instance.modifiers,
      'products': instance.products,
    };
