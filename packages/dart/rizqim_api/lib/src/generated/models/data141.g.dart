// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data141.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data141 _$Data141FromJson(Map<String, dynamic> json) => Data141(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  perPersonPrice: json['per_person_price'] as String,
  minimumGuests: (json['minimum_guests'] as num).toInt(),
  included: (json['included'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  description: json['description'] as String?,
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data141ToJson(Data141 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'per_person_price': instance.perPersonPrice,
  'minimum_guests': instance.minimumGuests,
  'included': instance.included,
  'description': instance.description,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
  'items': instance.items,
};
